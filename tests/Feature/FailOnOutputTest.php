<?php

declare(strict_types=1);

use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Execution\CommandRunner;
use Bityukov\CommandCenter\Runs\RunState;
use Symfony\Component\Process\PhpExecutableFinder;

function failPhp(): string
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

function scriptPrinting(string $text): string
{
    $path = sys_get_temp_dir().'/cc-fail-'.md5($text).'.php';

    file_put_contents($path, '<?php echo '.var_export($text, true).';');

    return failPhp().' '.$path;
}

beforeEach(function (): void {
    config()->set('command-center.shell.enabled', true);
});

it('succeeds on a zero exit code when nothing is configured', function (): void {
    $definition = Command::make('x')->shell()->run(scriptPrinting('ERROR something'))->toDefinition(30);

    expect(app(CommandRunner::class)->run($definition, [])->state)->toBe(RunState::Succeeded);
});

it('fails when the output contains a phrase the command declared as a failure', function (): void {
    $definition = Command::make('x')
        ->shell()
        ->run(scriptPrinting('ERROR The [public/storage] link already exists.'))
        ->failIfOutputContains('ERROR')
        ->toDefinition(30);

    $run = app(CommandRunner::class)->run($definition, []);

    expect($run->state)->toBe(RunState::Failed)
        ->and($run->error)->toContain('ERROR')
        // The real exit code is kept: the process did return zero, and the
        // record should not claim otherwise.
        ->and($run->exitCode)->toBe(0);
});

it('still succeeds when the phrase is absent', function (): void {
    $definition = Command::make('x')
        ->shell()
        ->run(scriptPrinting('The [public/storage] link has been connected.'))
        ->failIfOutputContains('ERROR')
        ->toDefinition(30);

    expect(app(CommandRunner::class)->run($definition, [])->state)->toBe(RunState::Succeeded);
});

it('accepts several phrases', function (): void {
    $definition = Command::make('x')
        ->shell()
        ->run(scriptPrinting('WARN nothing to do'))
        ->failIfOutputContains(['ERROR', 'WARN'])
        ->toDefinition(30);

    expect(app(CommandRunner::class)->run($definition, [])->state)->toBe(RunState::Failed);
});

it('reads the phrases from array config', function (): void {
    config()->set('command-center.commands', [
        'linky' => [
            'run' => scriptPrinting('ERROR already exists'),
            'type' => 'shell',
            'fail_if_output_contains' => 'ERROR',
        ],
    ]);
    app()->forgetScopedInstances();

    $definition = app(CommandRegistry::class)->findOrFail('linky');

    expect(app(CommandRunner::class)->run($definition, [])->state)->toBe(RunState::Failed);
});

it('leaves a non-zero exit code alone', function (): void {
    $path = sys_get_temp_dir().'/cc-fail-exit.php';
    file_put_contents($path, '<?php echo "nothing alarming"; exit(3);');

    $definition = Command::make('x')
        ->shell()
        ->run(failPhp().' '.$path)
        ->failIfOutputContains('ERROR')
        ->toDefinition(30);

    $run = app(CommandRunner::class)->run($definition, []);

    expect($run->state)->toBe(RunState::Failed)
        ->and($run->exitCode)->toBe(3);
});
