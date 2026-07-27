<?php

declare(strict_types=1);

namespace Bityukov\CommandCenter\Execution;

use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Definitions\CommandType;
use Bityukov\CommandCenter\Exceptions\ShellDisabledException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

final class ProcessFactory
{
    /**
     * Build a process from an argument vector.
     *
     * Process is always constructed with an array — this package never builds a
     * command string. On Unix, Symfony passes the array straight to the
     * underlying process-opening primitive, which execs the binary with no
     * shell. On sigchild-enabled PHP builds, on Windows, and in Symfony's
     * array-to-string fallback, it converts the array to a command string with
     * each element escaped individually. Either way, no user value is ever a
     * structural part of a line we assembled.
     *
     * @param  array<int, string>  $argv
     */
    public function make(CommandDefinition $definition, array $argv): Process
    {
        if ($definition->type === CommandType::Shell && ! config('command-center.shell.enabled', false)) {
            throw ShellDisabledException::for($definition->key);
        }

        $command = $definition->type === CommandType::Artisan
            ? array_merge([$this->phpBinary(), 'artisan'], $argv)
            : $argv;

        $process = new Process(
            command: $command,
            cwd: config('command-center.working_directory') ?? base_path(),
        );

        $process->setTimeout((float) $definition->timeout);

        return $process;
    }

    private function phpBinary(): string
    {
        $configured = config('command-center.php_binary');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (new PhpExecutableFinder)->find() ?: 'php';
    }
}
