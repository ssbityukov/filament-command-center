<?php

declare(strict_types=1);

use Bityukov\CommandCenter\CommandRegistry;
use Bityukov\CommandCenter\Exceptions\InvalidDefinitionException;
use Bityukov\CommandCenter\Sources\CommandRecord;
use Bityukov\CommandCenter\Sources\ConfigSource;
use Bityukov\CommandCenter\Sources\DatabaseSource;

/**
 * @param  array<string, mixed>  $definition
 */
function storeCommand(string $key, array $definition = [], bool $enabled = true): CommandRecord
{
    return CommandRecord::query()->create([
        'key' => $key,
        'is_enabled' => $enabled,
        'definition' => array_merge(['run' => 'route:list', 'label' => ucfirst($key)], $definition),
    ]);
}

it('reads a stored command into a definition', function (): void {
    storeCommand('backup-db', ['group' => 'Maintenance', 'timeout' => 120]);

    $definitions = app(DatabaseSource::class)->definitions();

    expect($definitions)->toHaveKey('backup-db')
        ->and($definitions['backup-db']->label)->toBe('Backup-db')
        ->and($definitions['backup-db']->group)->toBe('Maintenance')
        ->and($definitions['backup-db']->timeout)->toBe(120);
});

it('skips a disabled command', function (): void {
    storeCommand('enabled');
    storeCommand('disabled', enabled: false);

    expect(array_keys(app(DatabaseSource::class)->definitions()))->toBe(['enabled']);
});

it('parses variables and flags the same way config does', function (): void {
    storeCommand('route-list', [
        'run' => 'route:list --path={path}',
        'variables' => ['path' => ['type' => 'text', 'required' => true]],
        'flags' => ['--json' => ['label' => 'JSON']],
    ]);

    $definition = app(DatabaseSource::class)->definitions()['route-list'];

    expect($definition->variable('path'))->not->toBeNull()
        ->and($definition->variable('path')->required)->toBeTrue()
        ->and($definition->flags)->toHaveKey('--json');
});

it('refuses a stored command that puts a token in the command position', function (): void {
    storeCommand('arbitrary', ['run' => '{bin} hi']);

    expect(fn () => app(DatabaseSource::class)->definitions())
        ->toThrow(InvalidDefinitionException::class, 'first element');
});

it('is not registered as a source by default', function (): void {
    expect(config('command-center.sources'))->not->toContain(DatabaseSource::class);
});

it('overrides a config command with the same key when registered last', function (): void {
    config()->set('command-center.commands', [
        'shared' => ['run' => 'from:config', 'label' => 'From config'],
    ]);
    config()->set('command-center.sources', [ConfigSource::class, DatabaseSource::class]);
    app()->forgetScopedInstances();

    storeCommand('shared', ['run' => 'from:database', 'label' => 'From database']);

    expect(app(CommandRegistry::class)->findOrFail('shared')->label)->toBe('From database');
});
