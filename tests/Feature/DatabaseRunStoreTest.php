<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Runs\DatabaseRunStore;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunRecord;
use Bityukov\CommandCenter\Runs\RunStore;

beforeEach(function (): void {
    config()->set('command-center.history.driver', 'database');
    app()->forgetScopedInstances();
});

function databaseRun(string $key = 'backup-db'): Run
{
    $definition = Command::make($key)->run('backup:run')->toDefinition(30);

    return Run::start($definition, [], ['backup:run'], userId: 1)->finish(0, 'out');
}

it('is bound when the driver is database', function (): void {
    expect(app(RunStore::class))->toBeInstanceOf(DatabaseRunStore::class);
});

it('writes one row per run', function (): void {
    app(RunStore::class)->put(databaseRun());

    expect(RunRecord::query()->count())->toBe(1);
});

it('updates the existing row rather than inserting a second', function (): void {
    $run = databaseRun();

    app(RunStore::class)->put($run);
    app(RunStore::class)->put($run->withOutput('changed'));

    expect(RunRecord::query()->count())->toBe(1)
        ->and(RunRecord::query()->first()?->output)->toBe('changed');
});

it('survives a cache flush', function (): void {
    $run = databaseRun();

    app(RunStore::class)->put($run);
    cache()->flush();

    expect(app(RunStore::class)->find($run->id))->not->toBeNull();
});

it('is not capped the way the cache driver is', function (): void {
    config()->set('command-center.history.max', 2);

    $store = app(RunStore::class);

    foreach (range(1, 5) as $i) {
        $store->put(databaseRun('run-'.$i));
    }

    expect(RunRecord::query()->count())->toBe(5);
});
