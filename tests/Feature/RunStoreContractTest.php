<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunState;
use Bityukov\CommandCenter\Runs\RunStore;

/*
 | Every driver must satisfy this. Task 2 adds 'database' to the dataset; if a
 | driver needs a special case here, that is a signal the contract is wrong, not
 | that the driver deserves an exception.
 */
dataset('runStoreDrivers', ['cache', 'database']);

function contractStore(string $driver): RunStore
{
    config()->set('command-center.history.driver', $driver);

    app()->forgetScopedInstances();

    return app(RunStore::class);
}

function contractRun(string $key = 'backup-db', string $output = 'done'): Run
{
    $definition = Command::make($key)->label(ucfirst($key))->run('backup:run')->toDefinition(30);

    return Run::start($definition, ['path' => '/tmp'], ['backup:run'], userId: 7)->finish(0, $output);
}

it('round-trips every field of a run', function (string $driver): void {
    $store = contractStore($driver);
    $run = contractRun();

    $store->put($run);
    $found = $store->find($run->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($run->id)
        ->and($found->commandKey)->toBe($run->commandKey)
        ->and($found->label)->toBe($run->label)
        ->and($found->userId)->toBe(7)
        ->and($found->input)->toBe(['path' => '/tmp'])
        ->and($found->argv)->toBe(['backup:run'])
        ->and($found->state)->toBe(RunState::Succeeded)
        ->and($found->exitCode)->toBe(0)
        ->and($found->output)->toBe('done')
        ->and($found->startedAt?->toIso8601String())->toBe($run->startedAt?->toIso8601String())
        ->and($found->finishedAt?->toIso8601String())->toBe($run->finishedAt?->toIso8601String())
        ->and($found->durationMs)->toBe($run->durationMs);
})->with('runStoreDrivers');

it('returns null for an unknown id', function (string $driver): void {
    expect(contractStore($driver)->find('no-such-run'))->toBeNull();
})->with('runStoreDrivers');

it('lists runs newest first', function (string $driver): void {
    $store = contractStore($driver);

    $store->put(contractRun('one'));
    $store->put(contractRun('two'));

    expect(array_map(fn (Run $run): string => $run->commandKey, $store->recent()))->toBe(['two', 'one']);
})->with('runStoreDrivers');

it('honours the limit argument', function (string $driver): void {
    $store = contractStore($driver);

    $store->put(contractRun('one'));
    $store->put(contractRun('two'));

    expect($store->recent(limit: 1))->toHaveCount(1);
})->with('runStoreDrivers');

it('replaces a run stored under the same id', function (string $driver): void {
    $store = contractStore($driver);

    $run = contractRun();
    $store->put($run);
    $store->put($run->withOutput('updated'));

    expect($store->recent())->toHaveCount(1)
        ->and($store->find($run->id)?->output)->toBe('updated');
})->with('runStoreDrivers');

it('forgets one run without touching the others', function (string $driver): void {
    $store = contractStore($driver);

    $keep = contractRun('keep');
    $drop = contractRun('drop');

    $store->put($keep);
    $store->put($drop);
    $store->forget($drop->id);

    expect($store->find($drop->id))->toBeNull()
        ->and($store->find($keep->id))->not->toBeNull();
})->with('runStoreDrivers');

it('flushes everything', function (string $driver): void {
    $store = contractStore($driver);

    $store->put(contractRun('one'));
    $store->put(contractRun('two'));
    $store->flush();

    expect($store->recent())->toBe([]);
})->with('runStoreDrivers');

it('preserves a redacted input value as stored', function (string $driver): void {
    $store = contractStore($driver);

    $definition = Command::make('x')->run('x:run')->toDefinition(30);
    $run = Run::start($definition, ['token' => '[redacted]'], ['x:run'], userId: 1)->finish(0, '');

    $store->put($run);

    expect($store->find($run->id)?->input)->toBe(['token' => '[redacted]']);
})->with('runStoreDrivers');

it('stores a run that never started', function (string $driver): void {
    $store = contractStore($driver);

    $definition = Command::make('x')->run('x:run')->toDefinition(30);
    $run = Run::rejected($definition, 'Rate limited.', userId: 1);

    $store->put($run);

    expect($store->find($run->id)?->state)->toBe(RunState::Rejected)
        ->and($store->find($run->id)?->error)->toBe('Rate limited.');
})->with('runStoreDrivers');
