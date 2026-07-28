<?php

declare(strict_types=1);

use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Execution\Cancellation;
use Bityukov\CommandCenter\Jobs\RunCommandJob;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunState;
use Bityukov\CommandCenter\Runs\RunStore;
use Symfony\Component\Process\PhpExecutableFinder;

function cancelPhp(): string
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

function cancelScript(string $php): string
{
    $path = sys_get_temp_dir().'/cc-cancel-'.md5($php).'.php';

    file_put_contents($path, '<?php '.$php);

    return cancelPhp().' '.$path;
}

beforeEach(function (): void {
    config()->set('command-center.shell.enabled', true);
    config()->set('command-center.commands', [
        'marker' => [
            // Writes a file: proof of whether the process actually ran.
            'run' => cancelScript('file_put_contents("'.sys_get_temp_dir().'/cc-cancel-marker", "ran");'),
            'type' => 'shell',
            'label' => 'Marker',
            'queue' => true,
        ],
    ]);

    @unlink(sys_get_temp_dir().'/cc-cancel-marker');
});

it('does not run a queued command that was cancelled before the worker picked it up', function (): void {
    $definition = app(CommandRegistry::class)->findOrFail('marker');

    $run = Run::queued($definition, [], [], 1)->withId('cancel-before-start');
    app(RunStore::class)->put($run);

    app(Cancellation::class)->request($run->id);

    app()->call([new RunCommandJob($run->id, 'marker', [], 1), 'handle']);

    expect(file_exists(sys_get_temp_dir().'/cc-cancel-marker'))->toBeFalse()
        ->and(app(RunStore::class)->find($run->id)?->state)->toBe(RunState::Cancelled);
});
