<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Execution;

use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunState;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Throwable;

final class CommandRunner
{
    public function __construct(
        private readonly ArgvBuilder $argvBuilder,
        private readonly ProcessFactory $processFactory,
        private readonly RunObserver $observer,
        private readonly Cancellation $cancellation,
    ) {}

    /**
     * Run a command synchronously and return its terminal Run.
     *
     * Passing a run id opts into live streaming: output goes to the buffer,
     * progress is parsed, and a cancellation request stops the process. Without
     * one, the run is invisible until it finishes — which is exactly what a
     * short synchronous run wants.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): void)|null  $onOutput
     */
    public function run(
        CommandDefinition $definition,
        array $input,
        int|string|null $userId = null,
        ?callable $onOutput = null,
        ?string $runId = null,
    ): Run {
        $argv = $this->argvBuilder->build($definition, $input);
        $process = $this->processFactory->make($definition, $argv);

        $run = Run::start($definition, $this->redact($definition, $input), $argv, $userId);

        if ($runId !== null) {
            $run = $run->withId($runId);
        }

        $observe = $runId === null ? null : $this->observer->for($runId);

        $buffer = '';
        $cancelled = false;

        try {
            $process->run(function (string $type, string $chunk) use (
                &$buffer,
                &$cancelled,
                $onOutput,
                $observe,
                $runId,
                $process,
            ): void {
                $buffer .= $chunk;

                if ($observe !== null) {
                    $observe($chunk);
                }

                if ($onOutput !== null) {
                    $onOutput($chunk);
                }

                // Checked per chunk rather than on a timer: a command that has
                // stopped talking cannot be interrupted by us anyway, and its
                // own timeout is what bounds that case.
                if ($runId !== null && ! $cancelled && $this->cancellation->requested($runId)) {
                    $cancelled = true;
                    $process->stop(timeout: 10);
                }
            });
        } catch (ProcessTimedOutException) {
            return $run->timeout($buffer);
        } catch (Throwable $exception) {
            return $run->withOutput($buffer)->fail($exception->getMessage());
        }

        if ($cancelled) {
            return $run->cancel($buffer);
        }

        $run = $run->finish($process->getExitCode() ?? 1, $buffer);

        // A command that reports a problem in words while exiting zero is
        // recorded as failed, keeping the real exit code so the record does not
        // claim the process said something it did not.
        $reported = $definition->reportedFailureIn($buffer);

        if ($run->state === RunState::Succeeded && $reported !== null) {
            return $run->fail(sprintf(
                'The command exited zero but its output contains "%s", which this command treats as a failure.',
                $reported,
            ));
        }

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
