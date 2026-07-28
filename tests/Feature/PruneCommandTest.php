<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunRecord;
use Bityukov\CommandCenter\Runs\RunStore;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config()->set('command-center.history.driver', 'database');
    app()->forgetScopedInstances();
});

function runFinishedDaysAgo(int $days, string $key = 'old'): Run
{
    $definition = Command::make($key)->run('x:run')->toDefinition(30);

    $run = Run::start($definition, [], ['x:run'], userId: 1)->finish(0, 'out');

    app(RunStore::class)->put($run);

    RunRecord::query()->whereKey($run->id)->update([
        'started_at' => CarbonImmutable::now()->subDays($days),
        'finished_at' => CarbonImmutable::now()->subDays($days),
    ]);

    return $run;
}

it('deletes runs older than the cutoff', function (): void {
    $old = runFinishedDaysAgo(40, 'old');
    $fresh = runFinishedDaysAgo(2, 'fresh');

    $this->artisan('command-center:prune', ['--days' => 30])->assertExitCode(0);

    expect(app(RunStore::class)->find($old->id))->toBeNull()
        ->and(app(RunStore::class)->find($fresh->id))->not->toBeNull();
});

it('reports how many runs it deleted', function (): void {
    runFinishedDaysAgo(40, 'one');
    runFinishedDaysAgo(40, 'two');

    $this->artisan('command-center:prune', ['--days' => 30])
        ->expectsOutputToContain('Deleted 2 run')
        ->assertExitCode(0);
});

it('keeps a run that has not finished regardless of age', function (): void {
    $definition = Command::make('running')->run('x:run')->toDefinition(30);
    $run = Run::queued($definition, [], [], 1);

    app(RunStore::class)->put($run);

    RunRecord::query()->whereKey($run->id)->update([
        'started_at' => CarbonImmutable::now()->subDays(90),
    ]);

    $this->artisan('command-center:prune', ['--days' => 30])->assertExitCode(0);

    expect(app(RunStore::class)->find($run->id))->not->toBeNull();
});

it('refuses on the cache driver and says why', function (): void {
    config()->set('command-center.history.driver', 'cache');
    app()->forgetScopedInstances();

    $this->artisan('command-center:prune', ['--days' => 30])
        ->expectsOutputToContain('cache driver')
        ->assertExitCode(1);
});

it('rejects a non-positive number of days', function (): void {
    $this->artisan('command-center:prune', ['--days' => 0])->assertExitCode(1);
});
