<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\Variables\TextVariable;
use Bityukov\CommandCenter\Exceptions\ShellDisabledException;
use Bityukov\CommandCenter\Execution\CommandRunner;
use Bityukov\CommandCenter\Runs\RunState;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * A shell definition that runs the fixture script through the PHP binary,
 * so we can inspect exactly which argv elements the process received.
 */
function fixtureCommand(string $template): Command
{
    $php = (new PhpExecutableFinder)->find() ?: 'php';
    $script = __DIR__.'/../Fixtures/echo-argv.php';

    // The php binary is passed through a token, not concatenated literally, so
    // that a path containing spaces (e.g. Herd's "Application Support" install
    // path on macOS) still resolves to exactly one argv element after
    // ArgvBuilder splits the literal parts of the template on whitespace.
    return Command::make('fixture')
        ->shell()
        ->variables([TextVariable::make('phpBinary')->default($php)])
        ->run('{phpBinary} '.$script.' '.$template);
}

beforeEach(function (): void {
    config()->set('command-center.shell.enabled', true);
});

it('runs a command and records success', function (): void {
    $run = app(CommandRunner::class)->run(
        fixtureCommand('hello')->toDefinition(60),
        [],
    );

    expect($run->state)->toBe(RunState::Succeeded)
        ->and($run->exitCode)->toBe(0)
        ->and($run->output)->toContain('hello')
        ->and($run->durationMs)->toBeGreaterThanOrEqual(0);
});

it('passes a hostile value as exactly one argv element', function (): void {
    $definition = fixtureCommand('{payload}')
        ->variables([TextVariable::make('payload')])
        ->toDefinition(60);

    $run = app(CommandRunner::class)->run($definition, ['payload' => '; rm -rf / ; echo pwned']);

    // The fixture prints one line per argv element it received, after the script
    // path. A hostile value must produce exactly one line, proving it was never
    // split into extra arguments or interpreted by a shell.
    $lines = array_values(array_filter(explode(PHP_EOL, trim($run->output))));

    expect($lines)->toHaveCount(1)
        ->and($lines[0])->toBe('0:; rm -rf / ; echo pwned');
});

it('records a non zero exit code as failed', function (): void {
    $run = app(CommandRunner::class)->run(fixtureCommand('--exit=3')->toDefinition(60), []);

    expect($run->state)->toBe(RunState::Failed)
        ->and($run->exitCode)->toBe(3);
});

it('streams output through the callback', function (): void {
    $chunks = [];

    app(CommandRunner::class)->run(
        fixtureCommand('one two')->toDefinition(60),
        [],
        onOutput: function (string $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        },
    );

    expect(implode('', $chunks))->toContain('0:one')
        ->and(implode('', $chunks))->toContain('1:two');
});

it('redacts marked input on the stored run but still passes the real value', function (): void {
    $definition = fixtureCommand('{secret}')
        ->variables([TextVariable::make('secret')->redact()])
        ->toDefinition(60);

    $run = app(CommandRunner::class)->run($definition, ['secret' => 'hunter2']);

    expect($run->input['secret'])->toBe('[redacted]')
        ->and($run->output)->toContain('hunter2');
});

it('refuses shell commands when shell mode is disabled', function (): void {
    config()->set('command-center.shell.enabled', false);

    app(CommandRunner::class)->run(fixtureCommand('hello')->toDefinition(60), []);
})->throws(ShellDisabledException::class, 'fixture');

it('records a failure when the process cannot start', function (): void {
    $definition = Command::make('broken')->shell()->run('/nonexistent/binary/xyz')->toDefinition(60);

    $run = app(CommandRunner::class)->run($definition, []);

    // On this platform, Symfony's array-command proc_open call fails silently
    // for a missing binary and falls back to a `sh -c 'exec ...'` wrapper. The
    // shell itself starts fine, so no PHP exception is thrown; the failure
    // surfaces as a non-zero exit code with the shell's diagnostic on stderr,
    // which CommandRunner merges into the recorded output.
    expect($run->state)->toBe(RunState::Failed)
        ->and($run->exitCode)->not->toBeNull()
        ->and($run->output)->toContain('No such file or directory');
});

it('marks a run that exceeds its timeout', function (): void {
    $php = (new PhpExecutableFinder)->find() ?: 'php';
    $definition = Command::make('slow')
        ->shell()
        ->variables([TextVariable::make('phpBinary')->default($php)])
        ->run('{phpBinary} -r sleep(5);')
        ->timeout(1)
        ->toDefinition(60);

    $run = app(CommandRunner::class)->run($definition, []);

    expect($run->state)->toBe(RunState::TimedOut);
});
