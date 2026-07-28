<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Filament\Pages\Commands;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    config()->set('command-center.commands', [
        'route-list' => [
            'run' => 'route:list --path={path}',
            'label' => 'List routes',
            'group' => 'Diagnostics',
            'help' => 'Show the route table',
            'variables' => ['path' => ['type' => 'text']],
        ],
        'backup-db' => [
            'run' => 'backup:run',
            'label' => 'Backup database',
            'group' => 'Maintenance',
            'ability' => 'run-backups',
        ],
    ]);

    $this->actingAs(new TestUser(['name' => 'Ada']));
});

it('renders', function (): void {
    Gate::define('run-backups', fn (): bool => true);

    livewire(Commands::class)->assertOk();
});

it('lists commands grouped by their group', function (): void {
    Gate::define('run-backups', fn (): bool => true);

    $groups = livewire(Commands::class)->instance()->groups();

    expect(array_keys($groups))->toBe(['Diagnostics', 'Maintenance'])
        ->and($groups['Diagnostics'][0]->key)->toBe('route-list');
});

it('omits a denied command from the payload entirely', function (): void {
    Gate::define('run-backups', fn (): bool => false);

    livewire(Commands::class)
        ->assertSee('List routes')
        ->assertDontSee('Backup database');
});

it('does not expose a denied command through the page state', function (): void {
    Gate::define('run-backups', fn (): bool => false);

    $groups = livewire(Commands::class)->instance()->groups();

    expect(array_keys($groups))->toBe(['Diagnostics']);
});

it('filters by label', function (): void {
    Gate::define('run-backups', fn (): bool => true);

    $groups = livewire(Commands::class)->set('search', 'backup')->instance()->groups();

    expect(array_keys($groups))->toBe(['Maintenance']);
});

it('filters by key and help text as well as label', function (): void {
    Gate::define('run-backups', fn (): bool => true);

    expect(array_keys(livewire(Commands::class)->set('search', 'route-list')->instance()->groups()))
        ->toBe(['Diagnostics'])
        ->and(array_keys(livewire(Commands::class)->set('search', 'route table')->instance()->groups()))
        ->toBe(['Diagnostics']);
});

it('falls back to an ungrouped bucket when a command has no group', function (): void {
    config()->set('command-center.commands', [
        'x' => ['run' => 'route:list', 'label' => 'X'],
    ]);

    expect(array_keys(livewire(Commands::class)->instance()->groups()))->toBe(['Ungrouped']);
});

it('denies page access when the plugin authorize callback denies it', function (): void {
    filament('command-center')->authorize(fn (): bool => false);

    expect(Commands::canAccess())->toBeFalse();
});

it('filters the table by the search term', function (): void {
    Gate::define('run-backups', fn (): bool => true);

    livewire(Commands::class)
        ->assertSee('Backup database')
        ->searchTable('backup')
        ->assertSee('Backup database')
        ->assertDontSee('List routes');
});

it('searches the command line as well as the label', function (): void {
    Gate::define('run-backups', fn (): bool => true);

    livewire(Commands::class)
        ->searchTable('route:list')
        ->assertSee('List routes')
        ->assertDontSee('Backup database');
});

it('groups the catalogue by the command group', function (): void {
    Gate::define('run-backups', fn (): bool => true);

    $table = livewire(Commands::class)->instance()->getTable();

    expect($table->getGroups())->toHaveKey('group')
        ->and($table->getDefaultGroup()?->getId())->toBe('group');
});

it('still renders every group heading', function (): void {
    Gate::define('run-backups', fn (): bool => true);

    livewire(Commands::class)
        ->assertSee('Diagnostics')
        ->assertSee('Maintenance');
});
