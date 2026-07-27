<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Execution;

use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Runs\Run;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Throwable;

final class CommandRunner
{
    public function __construct(
        private readonly ArgvBuilder $argvBuilder,
        private readonly ProcessFactory $processFactory,
    ) {}

    /**
     * Run a command synchronously and return its terminal Run.
     *
     * @param  array<string, mixed>  $input
     * @param  (callable(string): void)|null  $onOutput
     */
    public function run(
        CommandDefinition $definition,
        array $input,
        int|string|null $userId = null,
        ?callable $onOutput = null,
    ): Run {
        $argv = $this->argvBuilder->build($definition, $input);
        $process = $this->processFactory->make($definition, $argv);

        $run = Run::start($definition, $this->redact($definition, $input), $argv, $userId);

        $buffer = '';

        try {
            $process->run(function (string $type, string $chunk) use (&$buffer, $onOutput): void {
                $buffer .= $chunk;

                if ($onOutput !== null) {
                    $onOutput($chunk);
                }
            });
        } catch (ProcessTimedOutException) {
            return $run->timeout($buffer);
        } catch (Throwable $exception) {
            return $run->withOutput($buffer)->fail($exception->getMessage());
        }

        return $run->finish($process->getExitCode() ?? 1, $buffer);
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
