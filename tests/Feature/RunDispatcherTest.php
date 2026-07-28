<?php

declare(strict_types=1);

use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Execution\ConcurrencyLock;
use Bityukov\CommandCenter\Execution\RunDispatcher;
use Bityukov\CommandCenter\Jobs\RunCommandJob;
use Bityukov\CommandCenter\Runs\RunState;
use Bityukov\CommandCenter\Runs\RunStore;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\PhpExecutableFinder;

function dispatcherPhp(): string
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

function dispatcherScript(string $php): string
{
    $path = sys_get_temp_dir().'/cc-dispatch-'.md5($php).'.php';

    file_put_contents($path, '<?php '.$php);

    return dispatcherPhp().' '.$path;
}

beforeEach(function (): void {
    config()->set('command-center.shell.enabled', true);
    config()->set('command-center.commands', [
        'sync-one' => [
            'run' => dispatcherScript('echo "sync";'),
            'type' => 'shell',
            'label' => 'Sync one',
        ],
        'queued-one' => [
            'run' => dispatcherScript('echo "queued";'),
            'type' => 'shell',
            'label' => 'Queued one',
            'queue' => 'long-running',
        ],
    ]);
});

function definitionFor(string $key): CommandDefinition
{
    return app(CommandRegistry::class)->findOrFail($key);
}

it('runs a non-queued command inline and records it', function (): void {
    $run = app(RunDispatcher::class)->dispatch(definitionFor('sync-one'), [], userId: 1);

    expect($run->state)->toBe(RunState::Succeeded)
        ->and($run->output)->toContain('sync')
        ->and(app(RunStore::class)->find($run->id))->not->toBeNull();
});

it('queues a queued command instead of running it inline', function (): void {
    Queue::fake();

    $run = app(RunDispatcher::class)->dispatch(definitionFor('queued-one'), [], userId: 1);

    expect($run->state)->toBe(RunState::Queued);

    Queue::assertPushed(RunCommandJob::class, fn (RunCommandJob $job): bool => $job->runId === $run->id);
});

it('records the queued run before the job is picked up', function (): void {
    Queue::fake();

    $run = app(RunDispatcher::class)->dispatch(definitionFor('queued-one'), [], userId: 1);

    expect(app(RunStore::class)->find($run->id)?->state)->toBe(RunState::Queued);
});

it('dispatches onto the queue named by the definition', function (): void {
    Queue::fake();

    app(RunDispatcher::class)->dispatch(definitionFor('queued-one'), [], userId: 1);

    Queue::assertPushedOn('long-running', RunCommandJob::class);
});

it('rejects and records a run that exceeds its rate limit', function (): void {
    config()->set('command-center.rate_limit.global', ['attempts' => 1, 'minutes' => 60]);

    app(RunDispatcher::class)->dispatch(definitionFor('sync-one'), [], userId: 1);

    $second = app(RunDispatcher::class)->dispatch(definitionFor('sync-one'), [], userId: 1);

    expect($second->state)->toBe(RunState::Rejected)
        ->and($second->error)->toContain('rate limit')
        ->and(app(RunStore::class)->find($second->id))->not->toBeNull();
});

it('rejects a run whose command is already at its concurrency limit', function (): void {
    config()->set('command-center.commands.sync-one.concurrency', 1);
    app()->forgetScopedInstances();

    app(ConcurrencyLock::class)->acquire(definitionFor('sync-one'));

    $run = app(RunDispatcher::class)->dispatch(definitionFor('sync-one'), [], userId: 1);

    expect($run->state)->toBe(RunState::Rejected)
        ->and($run->error)->toContain('already');
});

it('releases the concurrency lock after an inline run', function (): void {
    config()->set('command-center.commands.sync-one.concurrency', 1);
    app()->forgetScopedInstances();

    app(RunDispatcher::class)->dispatch(definitionFor('sync-one'), [], userId: 1);

    $second = app(RunDispatcher::class)->dispatch(definitionFor('sync-one'), [], userId: 1);

    expect($second->state)->toBe(RunState::Succeeded);
});

it('redacts a redacted value in the recorded queued run', function (): void {
    Queue::fake();

    config()->set('command-center.commands.queued-one.run', dispatcherScript('echo "queued";').' {secret}');
    config()->set('command-center.commands.queued-one.variables', [
        'secret' => ['type' => 'text', 'redact' => true],
    ]);
    app()->forgetScopedInstances();

    $run = app(RunDispatcher::class)->dispatch(
        definitionFor('queued-one'),
        ['secret' => 'super-secret'],
        userId: 1,
    );

    expect($run->input['secret'])->toBe('[redacted]');
});

it('refuses to dispatch a command the user is not authorized for', function (): void {
    config()->set('command-center.commands.sync-one.ability', 'run-sync');
    app()->forgetScopedInstances();
    Gate::define('run-sync', fn (): bool => false);

    $run = app(RunDispatcher::class)->dispatch(definitionFor('sync-one'), [], userId: 1);

    expect($run->state)->toBe(RunState::Rejected)
        ->and($run->error)->toContain('authorized');
});
