<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Variables\BooleanVariable;
use Bityukov\CommandCenter\Definitions\Variables\ModelVariable;
use Bityukov\CommandCenter\Definitions\Variables\SelectVariable;
use Bityukov\CommandCenter\Definitions\Variables\TextVariable;

it('builds a text variable with defaults', function (): void {
    $variable = TextVariable::make('path');

    expect($variable->name)->toBe('path')
        ->and($variable->label)->toBe('Path')
        ->and($variable->required)->toBeFalse()
        ->and($variable->redact)->toBeFalse()
        ->and($variable->rules)->toBe([])
        ->and($variable->fieldType())->toBe('text');
});

it('humanises snake case names', function (): void {
    expect(TextVariable::make('backup_path')->label)->toBe('Backup path');
});

it('applies builder methods immutably', function (): void {
    $base = TextVariable::make('path');
    $configured = $base->required()->default('/tmp')->redact()->rules(['string']);

    expect($base->required)->toBeFalse()
        ->and($configured->required)->toBeTrue()
        ->and($configured->default)->toBe('/tmp')
        ->and($configured->redact)->toBeTrue()
        ->and($configured->rules)->toBe(['string']);
});

it('resolves a text value to a string', function (): void {
    $variable = TextVariable::make('path');

    expect($variable->resolve('/var/backups'))->toBe('/var/backups')
        ->and($variable->resolve(''))->toBeNull()
        ->and($variable->resolve(null))->toBeNull();
});

it('falls back to the default when no value is given', function (): void {
    expect(TextVariable::make('path')->default('/tmp')->resolve(null))->toBe('/tmp');
});

it('builds a select variable with options', function (): void {
    $variable = SelectVariable::make('database')->options(['main' => 'Main']);

    expect($variable->options)->toBe(['main' => 'Main'])
        ->and($variable->fieldType())->toBe('select')
        ->and($variable->resolve('main'))->toBe('main');
});

it('resolves a boolean variable to its true value or null', function (): void {
    $variable = BooleanVariable::make('dry_run');

    expect($variable->fieldType())->toBe('boolean')
        ->and($variable->resolve(true))->toBe('1')
        ->and($variable->resolve(false))->toBeNull()
        ->and($variable->trueValue('yes')->resolve(true))->toBe('yes');
});

it('builds a model variable', function (): void {
    $variable = ModelVariable::make('user')
        ->model('App\\Models\\User')
        ->titleAttribute('email')
        ->valueAttribute('id');

    expect($variable->model)->toBe('App\\Models\\User')
        ->and($variable->titleAttribute)->toBe('email')
        ->and($variable->valueAttribute)->toBe('id')
        ->and($variable->fieldType())->toBe('model')
        ->and($variable->resolve(7))->toBe('7');
});
