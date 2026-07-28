<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Filament\Resources\CommandRecordResource;
use Bityukov\CommandCenter\Filament\Resources\CommandRecordResource\Pages\CreateCommandRecord;
use Bityukov\CommandCenter\Filament\Resources\CommandRecordResource\Pages\EditCommandRecord;
use Bityukov\CommandCenter\Sources\CommandRecord;
use Bityukov\CommandCenter\Sources\ConfigSource;
use Bityukov\CommandCenter\Sources\DatabaseSource;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    config()->set('command-center.sources', [ConfigSource::class, DatabaseSource::class]);
    app()->forgetScopedInstances();

    $this->actingAs(new TestUser(['name' => 'Ada']));
});

it('denies the editor without the managing ability', function (): void {
    Gate::define('command-center:manage-commands', fn (): bool => false);

    expect(CommandRecordResource::canAccess())->toBeFalse();
});

it('allows the editor with the managing ability', function (): void {
    Gate::define('command-center:manage-commands', fn (): bool => true);

    expect(CommandRecordResource::canAccess())->toBeTrue();
});

it('denies the editor when the database source is not registered at all', function (): void {
    config()->set('command-center.sources', [ConfigSource::class]);
    Gate::define('command-center:manage-commands', fn (): bool => true);

    expect(CommandRecordResource::canAccess())->toBeFalse();
});

it('stores a command built through the form', function (): void {
    Gate::define('command-center:manage-commands', fn (): bool => true);

    livewire(CreateCommandRecord::class)
        ->fillForm([
            'key' => 'backup-db',
            'is_enabled' => true,
            'definition.run' => 'backup:run',
            'definition.label' => 'Backup database',
            'definition.type' => 'artisan',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CommandRecord::query()->where('key', 'backup-db')->exists())->toBeTrue();
});

it('rejects a run template with a token in the command position at the form boundary', function (): void {
    Gate::define('command-center:manage-commands', fn (): bool => true);

    livewire(CreateCommandRecord::class)
        ->fillForm([
            'key' => 'arbitrary',
            'definition.run' => '{bin} hi',
            'definition.type' => 'artisan',
        ])
        ->call('create')
        ->assertHasFormErrors(['definition.run']);

    expect(CommandRecord::query()->count())->toBe(0);
});

it('rejects a duplicate key', function (): void {
    Gate::define('command-center:manage-commands', fn (): bool => true);

    CommandRecord::query()->create([
        'key' => 'taken',
        'is_enabled' => true,
        'definition' => ['run' => 'route:list'],
    ]);

    livewire(CreateCommandRecord::class)
        ->fillForm([
            'key' => 'taken',
            'definition.run' => 'route:list',
            'definition.type' => 'artisan',
        ])
        ->call('create')
        ->assertHasFormErrors(['key']);
});

it('stores variables built through the repeater', function (): void {
    Gate::define('command-center:manage-commands', fn (): bool => true);

    livewire(CreateCommandRecord::class)
        ->fillForm([
            'key' => 'sync-club',
            'definition.run' => 'club:sync --club={club}',
            'definition.type' => 'artisan',
            'definition.variables' => [
                [
                    'name' => 'club',
                    'label' => 'Club',
                    'type' => 'model',
                    'required' => true,
                    'model' => 'App\\Models\\User',
                    'title_attribute' => 'name',
                    'value_attribute' => 'id',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $stored = CommandRecord::query()->where('key', 'sync-club')->first();

    // Keyed by name, which is the shape the parser and the run template expect.
    expect($stored?->definition['variables'])->toHaveKey('club')
        ->and($stored?->definition['variables']['club']['type'])->toBe('model')
        ->and($stored?->definition['variables']['club']['required'])->toBeTrue();
});

it('reads stored variables back into the repeater', function (): void {
    Gate::define('command-center:manage-commands', fn (): bool => true);

    $record = CommandRecord::query()->create([
        'key' => 'sync-club',
        'is_enabled' => true,
        'definition' => [
            'run' => 'club:sync --club={club}',
            'variables' => ['club' => ['type' => 'text', 'label' => 'Club']],
        ],
    ]);

    livewire(EditCommandRecord::class, [
        'record' => $record->getKey(),
    ])->assertFormSet(fn (array $state): bool => ($state['definition']['variables'][0]['name'] ?? null) === 'club');
});

it('rejects a variable whose name is not a token in the run template', function (): void {
    Gate::define('command-center:manage-commands', fn (): bool => true);

    livewire(CreateCommandRecord::class)
        ->fillForm([
            'key' => 'mismatch',
            'definition.run' => 'club:sync',
            'definition.type' => 'artisan',
            'definition.variables' => [['name' => 'club', 'type' => 'text']],
        ])
        ->call('create')
        ->assertHasFormErrors();
});
