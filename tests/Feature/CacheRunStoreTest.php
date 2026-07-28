<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Runs\CacheRunStore;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunStore;

function storedRun(string $key = 'backup-db'): Run
{
    $definition = Command::make($key)->run('backup:run')->toDefinition(30);

    return Run::start($definition, [], ['backup:run'], userId: 1)->finish(0, 'done');
}

it('is bound to the cache driver by default', function (): void {
    expect(app(RunStore::class))->toBeInstanceOf(CacheRunStore::class);
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

it('skips index entries whose run key has expired', function (): void {
    $store = app(RunStore::class);

    $run = storedRun();
    $store->put($run);

    cache()->forget('cc:run:'.$run->id);

    expect($store->recent())->toBe([]);
});
