<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\CommandDefinition;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunState;

function runDefinition(): CommandDefinition
{
    return Command::make('backup-db')->label('Backup database')->run('backup:run')->toDefinition(60);
}

it('starts a run in the running state', function (): void {
    $run = Run::start(runDefinition(), ['a' => 'b'], ['backup:run'], userId: 7);

    expect($run->id)->toBeString()->not->toBeEmpty()
        ->and($run->commandKey)->toBe('backup-db')
        ->and($run->label)->toBe('Backup database')
        ->and($run->userId)->toBe(7)
        ->and($run->input)->toBe(['a' => 'b'])
        ->and($run->argv)->toBe(['backup:run'])
        ->and($run->state)->toBe(RunState::Running)
        ->and($run->startedAt)->not->toBeNull()
        ->and($run->finishedAt)->toBeNull()
        ->and($run->output)->toBe('');
});

it('creates a queued run without a start time', function (): void {
    $run = Run::queued(runDefinition(), [], ['backup:run'], userId: null);

    expect($run->state)->toBe(RunState::Queued)
        ->and($run->startedAt)->toBeNull();
});

it('marks a queued run as running', function (): void {
    $run = Run::queued(runDefinition(), [], ['backup:run'], userId: null)->markRunning();

    expect($run->state)->toBe(RunState::Running)
        ->and($run->startedAt)->not->toBeNull();
});

it('succeeds on exit code zero', function (): void {
    $run = Run::start(runDefinition(), [], [], null)->finish(0, 'done');

    expect($run->state)->toBe(RunState::Succeeded)
        ->and($run->exitCode)->toBe(0)
        ->and($run->output)->toBe('done')
        ->and($run->finishedAt)->not->toBeNull()
        ->and($run->durationMs)->toBeGreaterThanOrEqual(0);
});

it('fails on a non zero exit code', function (): void {
    expect(Run::start(runDefinition(), [], [], null)->finish(1, 'boom')->state)->toBe(RunState::Failed);
});

it('records timeout, cancel and start failure', function (): void {
    $base = Run::start(runDefinition(), [], [], null);

    expect($base->timeout('partial')->state)->toBe(RunState::TimedOut)
        ->and($base->cancel('partial')->state)->toBe(RunState::Cancelled)
        ->and($base->fail('binary missing')->state)->toBe(RunState::Failed)
        ->and($base->fail('binary missing')->error)->toBe('binary missing');
});

it('creates a rejected run', function (): void {
    $run = Run::rejected(runDefinition(), 'Rate limit exceeded', userId: 7);

    expect($run->state)->toBe(RunState::Rejected)
        ->and($run->error)->toBe('Rate limit exceeded')
        ->and($run->state->isTerminal())->toBeTrue();
});

it('knows which states are terminal', function (): void {
    expect(RunState::Queued->isTerminal())->toBeFalse()
        ->and(RunState::Running->isTerminal())->toBeFalse()
        ->and(RunState::Succeeded->isTerminal())->toBeTrue()
        ->and(RunState::Failed->isTerminal())->toBeTrue()
        ->and(RunState::TimedOut->isTerminal())->toBeTrue()
        ->and(RunState::Cancelled->isTerminal())->toBeTrue()
        ->and(RunState::Rejected->isTerminal())->toBeTrue();
});

it('round trips through an array', function (): void {
    $run = Run::start(runDefinition(), ['a' => 'b'], ['backup:run'], 7)->finish(0, 'done');

    $restored = Run::fromArray($run->toArray());

    expect($restored->id)->toBe($run->id)
        ->and($restored->commandKey)->toBe($run->commandKey)
        ->and($restored->state)->toBe($run->state)
        ->and($restored->exitCode)->toBe(0)
        ->and($restored->output)->toBe('done')
        ->and($restored->startedAt?->toIso8601String())->toBe($run->startedAt?->toIso8601String());
});
