<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Runs\CacheRunStore;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunState;
use Bityukov\CommandCenter\Runs\RunStore;

function storedRun(string $key = 'backup-db'): Run
{
    $definition = Command::make($key)->run('backup:run')->toDefinition(30);

    return Run::start($definition, [], ['backup:run'], userId: 1)->finish(0, 'done');
}

it('is bound to the cache driver by default', function (): void {
    expect(app(RunStore::class))->toBeInstanceOf(CacheRunStore::class);
});

it('stores and reads a run back verbatim', function (): void {
    $run = storedRun();

    app(RunStore::class)->put($run);

    $found = app(RunStore::class)->find($run->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($run->id)
        ->and($found->commandKey)->toBe('backup-db')
        ->and($found->state)->toBe(RunState::Succeeded)
        ->and($found->output)->toBe('done')
        ->and($found->exitCode)->toBe(0);
});

it('returns null for an unknown id', function (): void {
    expect(app(RunStore::class)->find('missing'))->toBeNull();
});

it('lists runs newest first', function (): void {
    $store = app(RunStore::class);

    $store->put(storedRun('one'));
    $store->put(storedRun('two'));

    expect(array_map(fn (Run $run): string => $run->commandKey, $store->recent()))
        ->toBe(['two', 'one']);
});

it('caps the index at the configured maximum and drops the oldest run', function (): void {
    config()->set('command-center.history.max', 2);

    $store = app(RunStore::class);

    $oldest = storedRun('one');
    $store->put($oldest);
    $store->put(storedRun('two'));
    $store->put(storedRun('three'));

    expect($store->recent())->toHaveCount(2)
        ->and($store->find($oldest->id))->toBeNull();
});

it('honours the limit argument of recent', function (): void {
    $store = app(RunStore::class);

    $store->put(storedRun('one'));
    $store->put(storedRun('two'));

    expect($store->recent(limit: 1))->toHaveCount(1);
});

it('replaces a run stored under the same id without duplicating the index entry', function (): void {
    $store = app(RunStore::class);

    $run = storedRun();
    $store->put($run);
    $store->put($run->withOutput('updated'));

    expect($store->recent())->toHaveCount(1)
        ->and($store->find($run->id)->output)->toBe('updated');
});

it('forgets a single run', function (): void {
    $store = app(RunStore::class);

    $run = storedRun();
    $store->put($run);
    $store->forget($run->id);

    expect($store->find($run->id))->toBeNull()
        ->and($store->recent())->toBe([]);
});

it('flushes every run', function (): void {
    $store = app(RunStore::class);

    $store->put(storedRun('one'));
    $store->put(storedRun('two'));
    $store->flush();

    expect($store->recent())->toBe([]);
});

it('skips index entries whose run key has expired', function (): void {
    $store = app(RunStore::class);

    $run = storedRun();
    $store->put($run);

    cache()->forget('cc:run:'.$run->id);

    expect($store->recent())->toBe([]);
});
