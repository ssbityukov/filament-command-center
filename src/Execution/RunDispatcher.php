<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Execution;

use Bityukov\CommandCenter\Authorization\Authorizer;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Jobs\RunCommandJob;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunStore;
use Illuminate\Support\Str;

/**
 * The single entry point from any UI into execution.
 *
 * The order is fixed because each guard answers a different question: the rate
 * limit is about one user hammering a command, the concurrency lock is about one
 * command running twice at once, and the queue decision is about where the work
 * belongs. A rejection is recorded like any other run — a refusal is part of the
 * audit trail, not a gap in it.
 */
final class RunDispatcher
{
    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly RunRateLimiter $rateLimiter,
        private readonly ConcurrencyLock $lock,
        private readonly CommandRunner $runner,
        private readonly RunStore $store,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function dispatch(CommandDefinition $definition, array $input, int|string|null $userId): Run
    {
        // Checked here as well as in the calling UI. This class is the entry
        // point every caller goes through, so the guarantee belongs on it
        // rather than on each caller remembering.
        if (! $this->authorizer->allows($definition)) {
            return $this->reject($definition, $userId, 'You are not authorized to run this command.');
        }

        $retryAfter = $this->rateLimiter->check($definition, $userId);

        if ($retryAfter !== null) {
            return $this->reject($definition, $userId, sprintf(
                'This command has hit its rate limit. Try again in %d seconds.',
                $retryAfter,
            ));
        }

        $owner = $this->lock->acquire($definition);

        if ($owner === null) {
            return $this->reject(
                $definition,
                $userId,
                'Another run of this command is already in flight.',
            );
        }

        if ($definition->isQueued()) {
            // The worker acquires its own slot, so this one is handed back
            // immediately rather than held across the queue wait.
            $this->lock->release($definition, $owner);

            return $this->queue($definition, $input, $userId);
        }

        try {
            $run = $this->runner->run($definition, $input, $userId, runId: Str::uuid()->toString());
        } finally {
            $this->lock->release($definition, $owner);
        }

        $this->store->put($run);

        return $run;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function queue(CommandDefinition $definition, array $input, int|string|null $userId): Run
    {
        $run = Run::queued($definition, $this->redact($definition, $input), [], $userId);

        $this->store->put($run);

        $job = new RunCommandJob($run->id, $definition->key, $input, $userId);

        $queue = $definition->queueName();

        $queue === null ? dispatch($job) : dispatch($job)->onQueue($queue);

        return $run;
    }

    private function reject(CommandDefinition $definition, int|string|null $userId, string $reason): Run
    {
        $run = Run::rejected($definition, $reason, $userId);

        $this->store->put($run);

        return $run;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function redact(CommandDefinition $definition, array $input): array
    {
        foreach ($input as $name => $value) {
            if ($definition->variable((string) $name)?->redact === true) {
                $input[$name] = '[redacted]';
            }
        }

        return $input;
    }
}
