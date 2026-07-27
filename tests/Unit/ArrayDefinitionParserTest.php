<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\CommandType;
use Bityukov\CommandCenter\Definitions\Variables\BooleanVariable;
use Bityukov\CommandCenter\Definitions\Variables\ModelVariable;
use Bityukov\CommandCenter\Definitions\Variables\SelectVariable;
use Bityukov\CommandCenter\Definitions\Variables\TextVariable;
use Bityukov\CommandCenter\Exceptions\InvalidDefinitionException;
use Bityukov\CommandCenter\Sources\ArrayDefinitionParser;

it('parses a minimal definition', function (): void {
    $definition = (new ArrayDefinitionParser)->parse('clear-cache', ['run' => 'cache:clear'], 60);

    expect($definition->key)->toBe('clear-cache')
        ->and($definition->label)->toBe('Clear cache')
        ->and($definition->run)->toBe('cache:clear')
        ->and($definition->type)->toBe(CommandType::Artisan)
        ->and($definition->timeout)->toBe(60);
});

it('throws when run is missing', function (): void {
    (new ArrayDefinitionParser)->parse('broken', ['label' => 'Broken'], 60);
})->throws(InvalidDefinitionException::class, 'broken');

it('parses every scalar option', function (): void {
    $definition = (new ArrayDefinitionParser)->parse('backup', [
        'run' => 'backup:run',
        'label' => 'Backup',
        'type' => 'shell',
        'group' => 'Maintenance',
        'help' => 'Dumps.',
        'timeout' => 600,
        'queue' => 'long-running',
        'ability' => 'run-backups',
        'concurrency' => 2,
        'rate_limit' => ['attempts' => 3, 'minutes' => 60],
        'confirm' => 'Sure?',
        'progress' => true,
    ], 60);

    expect($definition->type)->toBe(CommandType::Shell)
        ->and($definition->group)->toBe('Maintenance')
        ->and($definition->help)->toBe('Dumps.')
        ->and($definition->timeout)->toBe(600)
        ->and($definition->queue)->toBe('long-running')
        ->and($definition->ability)->toBe('run-backups')
        ->and($definition->concurrency)->toBe(2)
        ->and($definition->rateLimit)->toBe(['attempts' => 3, 'minutes' => 60])
        ->and($definition->confirm)->toBe('Sure?')
        ->and($definition->progress)->toBeTrue();
});

it('parses each variable type', function (): void {
    $definition = (new ArrayDefinitionParser)->parse('x', [
        'run' => 'cmd {a} {b} {c} {d}',
        'variables' => [
            'a' => ['type' => 'text', 'required' => true, 'rules' => ['string']],
            'b' => ['type' => 'select', 'options' => ['one' => 'One']],
            'c' => ['type' => 'boolean', 'true_value' => 'yes'],
            'd' => ['type' => 'model', 'model' => 'App\\Models\\User', 'title_attribute' => 'email'],
        ],
    ], 60);

    expect($definition->variable('a'))->toBeInstanceOf(TextVariable::class)
        ->and($definition->variable('a')->required)->toBeTrue()
        ->and($definition->variable('a')->rules)->toBe(['string'])
        ->and($definition->variable('b'))->toBeInstanceOf(SelectVariable::class)
        ->and($definition->variable('b')->options)->toBe(['one' => 'One'])
        ->and($definition->variable('c'))->toBeInstanceOf(BooleanVariable::class)
        ->and($definition->variable('c')->trueValue)->toBe('yes')
        ->and($definition->variable('d'))->toBeInstanceOf(ModelVariable::class)
        ->and($definition->variable('d')->model)->toBe('App\\Models\\User')
        ->and($definition->variable('d')->titleAttribute)->toBe('email');
});

it('defaults a variable without a type to text', function (): void {
    $definition = (new ArrayDefinitionParser)->parse('x', [
        'run' => 'cmd {a}',
        'variables' => ['a' => []],
    ], 60);

    expect($definition->variable('a'))->toBeInstanceOf(TextVariable::class);
});

it('throws on an unknown variable type', function (): void {
    (new ArrayDefinitionParser)->parse('x', [
        'run' => 'cmd {a}',
        'variables' => ['a' => ['type' => 'wormhole']],
    ], 60);
})->throws(InvalidDefinitionException::class, 'wormhole');

it('parses flags', function (): void {
    $definition = (new ArrayDefinitionParser)->parse('x', [
        'run' => 'cmd',
        'flags' => ['--force' => ['label' => 'Force it', 'default' => true]],
    ], 60);

    expect($definition->flags['--force']->label)->toBe('Force it')
        ->and($definition->flags['--force']->default)->toBeTrue();
});
