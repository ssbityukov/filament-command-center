<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\Variables\TextVariable;
use Bityukov\CommandCenter\Exceptions\ShellDisabledException;
use Bityukov\CommandCenter\Exceptions\UnsafeValueException;
use Bityukov\CommandCenter\Execution\CommandRunner;
use Bityukov\CommandCenter\Runs\RunState;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * A path to the PHP binary that contains no whitespace.
 *
 * The command position of a run template must be a literal, and ArgvBuilder
 * splits literal text on whitespace, so a PHP binary installed under a path
 * with spaces — Herd's "Application Support" location on macOS — cannot be
 * named directly. A symlink in the temp directory gives it a nameable alias.
 */
function whitespaceFreePhpBinary(): string
{
    $php = (new PhpExecutableFinder)->find() ?: 'php';

    if (preg_match('/\s/', $php) !== 1) {
        return $php;
    }

    $link = sys_get_temp_dir().'/cc-test-php-'.md5($php);

    if (! file_exists($link)) {
        symlink($php, $link);
    }

    return $link;
}

/**
 * A shell definition that runs the fixture script through the PHP binary,
 * so we can inspect exactly which argv elements the process received.
 */
function fixtureCommand(string $template): Command
{
    $script = __DIR__.'/../Fixtures/echo-argv.php';

    return Command::make('fixture')
        ->shell()
        ->run(whitespaceFreePhpBinary().' '.$script.' '.$template);
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

    // Symfony's array-command proc_open call fails silently for a missing
    // binary and falls back to a `sh -c 'exec ...'` wrapper. The shell itself
    // starts fine, so no PHP exception is thrown; the failure surfaces as a
    // non-zero exit code with the shell's diagnostic on stderr, which
    // CommandRunner merges into the recorded output.
    //
    // The assertion names the binary rather than the diagnostic: the wording is
    // the shell's, and it differs by platform — bash on macOS says "No such file
    // or directory" where dash on Ubuntu says "not found". What the package
    // guarantees is that the run fails with a reason attached, never silently.
    expect($run->state)->toBe(RunState::Failed)
        ->and($run->exitCode)->not->toBeNull()
        ->and($run->exitCode)->not->toBe(0)
        ->and($run->output)->toContain('/nonexistent/binary/xyz')
        ->and(trim($run->output))->not->toBe('');
});

it('marks a run that exceeds its timeout', function (): void {
    $definition = Command::make('slow')
        ->shell()
        ->run(whitespaceFreePhpBinary().' -r sleep(5);')
        ->timeout(1)
        ->toDefinition(60);

    $run = app(CommandRunner::class)->run($definition, []);

    expect($run->state)->toBe(RunState::TimedOut);
});

it('delivers each hostile value to a real process as exactly one verbatim argv element', function (string $hostile): void {
    $definition = fixtureCommand('{payload}')
        ->variables([TextVariable::make('payload')])
        ->toDefinition(60);

    $run = app(CommandRunner::class)->run($definition, ['payload' => $hostile]);

    if (str_contains($hostile, "\0")) {
        // proc_open refuses an argument containing a null byte outright, so this
        // one value never reaches the process at all. That is failing safe, not
        // verbatim delivery, and it is the only input in the set that does not
        // round-trip — asserted here rather than papered over.
        expect($run->state)->toBe(RunState::Failed)
            ->and(strtolower((string) $run->error))->toContain('null byte');

        return;
    }

    // The fixture prints "<index>:<value>" per received argv element. Exactly
    // one line, with index 0 and byte-identical content, proves the value was
    // neither split, escaped, expanded, nor interpreted.
    expect($run->state)->toBe(RunState::Succeeded)
        ->and(rtrim($run->output, "\r\n"))->toBe('0:'.$hostile);
})->with('hostile values');

it('delivers a leading dash value verbatim to a real process when embedded', function (string $hostile): void {
    $definition = fixtureCommand('--path={payload}')
        ->variables([TextVariable::make('payload')])
        ->toDefinition(60);

    $run = app(CommandRunner::class)->run($definition, ['payload' => $hostile]);

    expect($run->state)->toBe(RunState::Succeeded)
        ->and(rtrim($run->output, "\r\n"))->toBe('0:--path='.$hostile);
})->with('leading dash values');

it('rejects a leading dash value before any process is started', function (): void {
    $definition = fixtureCommand('{payload}')
        ->variables([TextVariable::make('payload')])
        ->toDefinition(60);

    expect(fn () => app(CommandRunner::class)->run($definition, ['payload' => '--version']))
        ->toThrow(UnsafeValueException::class);
});
