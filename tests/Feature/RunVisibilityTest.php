<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Filament\Pages\History;
use Bityukov\CommandCenter\Filament\Pages\RunView;
use Bityukov\CommandCenter\Runs\Run;
use Bityukov\CommandCenter\Runs\RunStore;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Livewire\livewire;

function privilegedRun(): Run
{
    $definition = Command::make('backup-db')
        ->label('Backup database')
        ->run('backup:run')
        ->ability('run-backups')
        ->toDefinition(30);

    $run = Run::start($definition, [], ['backup:run'], userId: 1)->finish(0, 'SECRET OUTPUT');

    app(RunStore::class)->put($run);

    return $run;
}

beforeEach(function (): void {
    config()->set('command-center.commands', [
        'backup-db' => [
            'run' => 'backup:run',
            'label' => 'Backup database',
            'ability' => 'run-backups',
        ],
    ]);

    $this->actingAs(new TestUser(['name' => 'Ada']));
});

it('hides a run of a command the user may not run', function (): void {
    Gate::define('run-backups', fn (): bool => false);

    $run = privilegedRun();

    (new RunView)->mount($run->id);
})->throws(NotFoundHttpException::class);

it('shows a run of a command the user may run', function (): void {
    Gate::define('run-backups', fn (): bool => true);

    $run = privilegedRun();

    livewire(RunView::class, ['run' => $run->id])
        ->assertOk()
        ->assertSee('SECRET OUTPUT');
});

it('omits unauthorized runs from history', function (): void {
    Gate::define('run-backups', fn (): bool => false);

    privilegedRun();

    livewire(History::class)->assertCountTableRecords(0);
});

it('lists authorized runs in history', function (): void {
    Gate::define('run-backups', fn (): bool => true);

    privilegedRun();

    livewire(History::class)->assertCountTableRecords(1);
});

it('hides the row delete action when the prune ability is denied', function (): void {
    Gate::define('run-backups', fn (): bool => true);
    Gate::define('command-center:prune-history', fn (): bool => false);

    $run = privilegedRun();

    livewire(History::class)->assertTableActionHidden('delete', $run->id);
});

it('refuses a row delete when the prune ability is denied', function (): void {
    Gate::define('run-backups', fn (): bool => true);
    Gate::define('command-center:prune-history', fn (): bool => false);

    $run = privilegedRun();

    try {
        livewire(History::class)->callTableAction('delete', $run->id);
    } catch (Throwable) {
        // The action is hidden, so Filament may refuse to call it at all.
    }

    expect(app(RunStore::class)->find($run->id))->not->toBeNull();
});
