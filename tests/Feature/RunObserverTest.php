<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Execution\Cancellation;
use Bityukov\CommandCenter\Execution\CommandRunner;
use Bityukov\CommandCenter\Execution\OutputBuffer;
use Bityukov\CommandCenter\Execution\RunProgress;
use Bityukov\CommandCenter\Runs\RunState;
use Symfony\Component\Process\PhpExecutableFinder;

function observerPhpBinary(): string
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
 * A run template is split on whitespace before token substitution, so a PHP
 * snippet cannot be passed inline with -r. It goes to a whitespace-free temp
 * file instead — the same reason the Plan 1 fixture is a file.
 */
function scriptCommand(string $php): Command
{
    $path = sys_get_temp_dir().'/cc-script-'.md5($php).'.php';

    file_put_contents($path, '<?php '.$php);

    return Command::make('script')
        ->shell()
        ->run(observerPhpBinary().' '.$path);
}

beforeEach(function (): void {
    config()->set('command-center.shell.enabled', true);
});

it('streams output into the buffer under the given run id', function (): void {
    $definition = scriptCommand('echo "hello";')->toDefinition(30);

    $run = app(CommandRunner::class)->run($definition, [], runId: 'run-1');

    expect($run->id)->toBe('run-1')
        ->and(app(OutputBuffer::class)->all('run-1'))->toContain('hello');
});

it('records progress from the sentinel while running', function (): void {
    $definition = scriptCommand('echo "##CC_PROGRESS:60##";')->toDefinition(30);

    app(CommandRunner::class)->run($definition, [], runId: 'run-2');

    expect(app(RunProgress::class)->get('run-2'))->toBe(60);
});

it('copies the buffered output onto the finished run', function (): void {
    $definition = scriptCommand('echo "final";')->toDefinition(30);

    $run = app(CommandRunner::class)->run($definition, [], runId: 'run-3');

    expect($run->output)->toContain('final')
        ->and($run->state)->toBe(RunState::Succeeded);
});

it('stops a running process when cancellation is requested', function (): void {
    app(Cancellation::class)->request('run-4');

    $definition = scriptCommand('for ($i = 0; $i < 200; $i++) { echo "tick\n"; usleep(20000); }')
        ->timeout(20)
        ->toDefinition(30);

    $run = app(CommandRunner::class)->run($definition, [], runId: 'run-4');

    expect($run->state)->toBe(RunState::Cancelled);
});

it('leaves the synchronous path untouched when no run id is given', function (): void {
    $definition = scriptCommand('echo "plain";')->toDefinition(30);

    $run = app(CommandRunner::class)->run($definition, []);

    expect($run->state)->toBe(RunState::Succeeded)
        ->and($run->output)->toContain('plain')
        ->and(app(OutputBuffer::class)->all($run->id))->toBe('');
});
