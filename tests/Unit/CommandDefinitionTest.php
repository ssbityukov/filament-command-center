<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\CommandType;
use Bityukov\CommandCenter\Definitions\Flag;
use Bityukov\CommandCenter\Definitions\Variables\SelectVariable;
use Bityukov\CommandCenter\Definitions\Variables\TextVariable;

it('builds an artisan definition with sensible defaults', function (): void {
    $definition = Command::make('clear-cache')
        ->run('cache:clear')
        ->toDefinition(defaultTimeout: 60);

    expect($definition->key)->toBe('clear-cache')
        ->and($definition->label)->toBe('Clear cache')
        ->and($definition->run)->toBe('cache:clear')
        ->and($definition->type)->toBe(CommandType::Artisan)
        ->and($definition->timeout)->toBe(60)
        ->and($definition->queue)->toBeFalse()
        ->and($definition->isQueued())->toBeFalse()
        ->and($definition->queueName())->toBeNull()
        ->and($definition->progress)->toBeFalse()
        ->and($definition->confirm)->toBeFalse()
        ->and($definition->variables)->toBe([])
        ->and($definition->flags)->toBe([]);
});

it('carries every configured option onto the definition', function (): void {
    $definition = Command::make('backup-db')
        ->label('Backup database')
        ->run('backup:run {database} --path={path}')
        ->group('Maintenance')
        ->help('Dumps the database.')
        ->timeout(600)
        ->queue('long-running')
        ->ability('run-backups')
        ->concurrency(1)
        ->rateLimit(3, perMinutes: 60)
        ->confirm('Are you sure?')
        ->progress()
        ->variables([
            SelectVariable::make('database')->options(['main' => 'Main'])->required(),
            TextVariable::make('path'),
        ])
        ->flags([Flag::make('--force')])
        ->toDefinition(defaultTimeout: 60);

    expect($definition->label)->toBe('Backup database')
        ->and($definition->group)->toBe('Maintenance')
        ->and($definition->help)->toBe('Dumps the database.')
        ->and($definition->timeout)->toBe(600)
        ->and($definition->queue)->toBe('long-running')
        ->and($definition->isQueued())->toBeTrue()
        ->and($definition->queueName())->toBe('long-running')
        ->and($definition->ability)->toBe('run-backups')
        ->and($definition->concurrency)->toBe(1)
        ->and($definition->rateLimit)->toBe(['attempts' => 3, 'minutes' => 60])
        ->and($definition->confirm)->toBe('Are you sure?')
        ->and($definition->progress)->toBeTrue()
        ->and(array_keys($definition->variables))->toBe(['database', 'path'])
        ->and(array_keys($definition->flags))->toBe(['--force']);
});

it('marks shell definitions', function (): void {
    $definition = Command::make('disk-usage')->run('df -h')->shell()->toDefinition(defaultTimeout: 60);

    expect($definition->type)->toBe(CommandType::Shell);
});

it('reports queue name as null for the default queue', function (): void {
    $definition = Command::make('x')->run('cache:clear')->queue(true)->toDefinition(defaultTimeout: 60);

    expect($definition->isQueued())->toBeTrue()
        ->and($definition->queueName())->toBeNull();
});

it('extracts unique tokens from the template in order', function (): void {
    $definition = Command::make('x')
        ->run('backup:run {database} --path={path} --also={database}')
        ->toDefinition(defaultTimeout: 60);

    expect($definition->tokens())->toBe(['database', 'path']);
});

it('looks up a variable by name', function (): void {
    $definition = Command::make('x')
        ->run('cmd {path}')
        ->variables([TextVariable::make('path')])
        ->toDefinition(defaultTimeout: 60);

    expect($definition->variable('path'))->not->toBeNull()
        ->and($definition->variable('missing'))->toBeNull();
});
