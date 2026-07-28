<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Filament\Pages\History;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunStore;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

use function Pest\Livewire\livewire;

function historyRun(string $key, int $exitCode = 0): Run
{
    $definition = Command::make($key)->label(ucfirst($key))->run('route:list')->toDefinition(30);

    $run = Run::start($definition, [], ['route:list'], userId: 1)->finish($exitCode, 'out');

    app(RunStore::class)->put($run);

    return $run;
}

beforeEach(function (): void {
    $this->actingAs(new TestUser(['name' => 'Ada']));
});

it('renders', function (): void {
    livewire(History::class)->assertOk();
});

it('lists stored runs newest first', function (): void {
    historyRun('one');
    historyRun('two');

    livewire(History::class)
        ->assertCountTableRecords(2)
        ->assertSeeInOrder(['Two', 'One']);
});

it('shows an empty table when nothing has run', function (): void {
    livewire(History::class)->assertCountTableRecords(0);
});

/*
 | These assert record counts, not page text: a filter's own select renders
 | every command label as an option, so assertDontSee() would fail on the
 | dropdown even when the row is correctly filtered out.
 */
it('filters by state', function (): void {
    historyRun('ok');
    historyRun('bad', exitCode: 1);

    livewire(History::class)
        ->assertCountTableRecords(2)
        ->filterTable('state', 'failed')
        ->assertCountTableRecords(1);
});

it('filters by command key', function (): void {
    historyRun('ok');
    historyRun('bad');

    livewire(History::class)
        ->assertCountTableRecords(2)
        ->filterTable('command_key', 'bad')
        ->assertCountTableRecords(1);
});

it('deletes a single run', function (): void {
    $run = historyRun('ok');

    livewire(History::class)->callTableAction('delete', $run->id);

    expect(app(RunStore::class)->find($run->id))->toBeNull();
});

it('hides the prune action when the ability is denied', function (): void {
    Gate::define('command-center:prune-history', fn (): bool => false);

    livewire(History::class)->assertActionHidden('prune');
});

it('prunes all history when the ability is granted', function (): void {
    Gate::define('command-center:prune-history', fn (): bool => true);

    historyRun('ok');

    livewire(History::class)->callAction('prune');

    expect(app(RunStore::class)->recent())->toBe([]);
});
