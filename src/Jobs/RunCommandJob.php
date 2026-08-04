<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Jobs;

use Bityukov\CommandCenter\Authorization\Authorizer;
use Bityukov\CommandCenter\Authorization\UserResolver;
use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Execution\Cancellation;
use Bityukov\CommandCenter\Execution\CommandRunner;
use Bityukov\CommandCenter\Execution\ConcurrencyLock;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class RunCommandJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * One attempt, deliberately.
     *
     * A privileged command must never be silently run twice because a worker
     * blinked. If a run fails, a human decides whether to run it again.
     */
    public int $tries = 1;

    public int $timeout;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly string $runId,
        public readonly string $commandKey,
        public readonly array $input,
        public readonly int|string|null $userId,
        public readonly ?string $authGuard = null,
    ) {
        // The worker must outlive the process, not race it: if the worker were
        // killed first the process would be orphaned with nothing recording its
        // outcome.
        $this->timeout = $this->processTimeout() + 30;
    }

    public function handle(
        CommandRegistry $registry,
        Authorizer $authorizer,
        CommandRunner $runner,
        RunStore $store,
        ConcurrencyLock $lock,
        Cancellation $cancellation,
    ): void {
        $stored = $store->find($this->runId);
        $definition = $registry->find($this->commandKey);

        if ($definition === null) {
            $this->reject($store, $stored, 'The command no longer exists.');

            return;
        }

        // Re-checked here, not trusted from dispatch: a gate can be revoked
        // between the click and the worker picking the job up.
        if (! $authorizer->allows($definition, $this->user())) {
            $this->reject($store, $stored, 'Authorization was revoked before the command ran.');

            return;
        }

        // Cancelling a run that is still queued has to stop it here. The runner
        // only notices a cancellation between chunks of output, which is no use
        // before a process exists — and a user who cancelled a privileged
        // command must never watch it run anyway.
        if ($cancellation->requested($this->runId)) {
            if ($stored !== null) {
                $store->put($stored->cancel($stored->output));
            }

            return;
        }

        $owner = $lock->acquire($definition);

        if ($owner === null) {
            $this->reject($store, $stored, 'Another run of this command is already in flight.');

            return;
        }

        try {
            if ($stored !== null) {
                $store->put($stored->markRunning());
            }

            $store->put($runner->run($definition, $this->input, $this->userId, runId: $this->runId));
        } catch (Throwable $exception) {
            if ($stored !== null) {
                $store->put($stored->fail($exception->getMessage()));
            }

            throw $exception;
        } finally {
            $lock->release($definition, $owner);
        }
    }

    private function user(): ?Authenticatable
    {
        if ($this->userId === null) {
            return null;
        }

        return UserResolver::find($this->userId, $this->authGuard);
    }

    private function reject(RunStore $store, ?Run $stored, string $reason): void
    {
        if ($stored === null) {
            return;
        }

        $store->put($stored->reject($reason));
    }

    private function processTimeout(): int
    {
        $definition = app(CommandRegistry::class)->find($this->commandKey);

        return $definition === null
            ? (int) config('command-center.default_timeout', 30)
            : $definition->timeout;
    }
}
