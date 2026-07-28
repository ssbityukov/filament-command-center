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

it('deletes a single run when the prune ability is granted', function (): void {
    Gate::define('command-center:prune-history', fn (): bool => true);

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

it('shows who ran a command rather than a bare id', function (): void {
    $ada = TestUser::create(['name' => 'Ada Lovelace', 'email' => 'ada@test.dev', 'password' => 'x']);

    $definition = Command::make('backup')->label('Backup')->run('route:list')->toDefinition(30);
    $run = Run::start($definition, [], ['route:list'], userId: $ada->id)->finish(0, 'out');

    app(RunStore::class)->put($run);

    // Only the positive assertion: an id like "1" appears all over the markup,
    // so asserting its absence would fail regardless of the column.
    livewire(History::class)->assertSee('Ada Lovelace');
});

it('falls back to the raw id when the user is gone', function (): void {
    $definition = Command::make('backup')->label('Backup')->run('route:list')->toDefinition(30);
    $run = Run::start($definition, [], ['route:list'], userId: 4242)->finish(0, 'out');

    app(RunStore::class)->put($run);

    livewire(History::class)->assertSee('4242');
});

it('deletes several runs at once when the prune ability is granted', function (): void {
    Gate::define('command-center:prune-history', fn (): bool => true);

    $one = historyRun('one');
    $two = historyRun('two');
    $keep = historyRun('keep');

    livewire(History::class)->callTableBulkAction('delete', [$one->id, $two->id]);

    expect(app(RunStore::class)->find($one->id))->toBeNull()
        ->and(app(RunStore::class)->find($two->id))->toBeNull()
        ->and(app(RunStore::class)->find($keep->id))->not->toBeNull();
});

it('hides the bulk delete when the prune ability is denied', function (): void {
    Gate::define('command-center:prune-history', fn (): bool => false);

    historyRun('one');

    livewire(History::class)->assertTableBulkActionHidden('delete');
});
