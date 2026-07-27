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

it('throws on an unknown command type', function (): void {
    (new ArrayDefinitionParser)->parse('x', ['run' => 'df -h', 'type' => 'Shell'], 60);
})->throws(InvalidDefinitionException::class, 'Shell');

it('accepts an explicit artisan type', function (): void {
    $definition = (new ArrayDefinitionParser)->parse('x', ['run' => 'cache:clear', 'type' => 'artisan'], 60);

    expect($definition->type)->toBe(CommandType::Artisan);
});

it('casts a numeric string concurrency', function (): void {
    $definition = (new ArrayDefinitionParser)->parse('x', ['run' => 'cmd', 'concurrency' => '2'], 60);

    expect($definition->concurrency)->toBe(2);
});

it('throws on a concurrency that is not numeric', function (): void {
    (new ArrayDefinitionParser)->parse('x', ['run' => 'cmd', 'concurrency' => ['two']], 60);
})->throws(InvalidDefinitionException::class, 'concurrency');

it('throws on a queue value that is neither a boolean nor a string', function (): void {
    (new ArrayDefinitionParser)->parse('x', ['run' => 'cmd', 'queue' => 1], 60);
})->throws(InvalidDefinitionException::class, 'queue');

it('throws on a confirm value that is neither a boolean nor a string', function (): void {
    (new ArrayDefinitionParser)->parse('x', ['run' => 'cmd', 'confirm' => 1.5], 60);
})->throws(InvalidDefinitionException::class, 'confirm');

it('throws on a group that is not a string', function (): void {
    (new ArrayDefinitionParser)->parse('x', ['run' => 'cmd', 'group' => ['Maintenance']], 60);
})->throws(InvalidDefinitionException::class, 'group');

it('casts a numeric group to a string', function (): void {
    $definition = (new ArrayDefinitionParser)->parse('x', ['run' => 'cmd', 'group' => 2026], 60);

    expect($definition->group)->toBe('2026');
});

it('refuses a token in the command position', function (): void {
    (new ArrayDefinitionParser)->parse('x', [
        'run' => '{bin} hi',
        'type' => 'shell',
        'variables' => ['bin' => ['type' => 'text']],
    ], 60);
})->throws(InvalidDefinitionException::class, 'x');

it('parses allows_leading_dash', function (): void {
    $definition = (new ArrayDefinitionParser)->parse('x', [
        'run' => 'cmd {a} {b}',
        'variables' => [
            'a' => ['type' => 'text', 'allows_leading_dash' => true],
            'b' => ['type' => 'text'],
        ],
    ], 60);

    expect($definition->variable('a')->allowsLeadingDash)->toBeTrue()
        ->and($definition->variable('b')->allowsLeadingDash)->toBeFalse();
});
