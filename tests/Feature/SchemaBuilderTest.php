<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\Flag;
use Bityukov\CommandCenter\Definitions\Variables\BooleanVariable;
use Bityukov\CommandCenter\Definitions\Variables\ModelVariable;
use Bityukov\CommandCenter\Definitions\Variables\SelectVariable;
use Bityukov\CommandCenter\Definitions\Variables\TextVariable;
use Bityukov\CommandCenter\Filament\SchemaBuilder;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

function builtFields(Command $command): array
{
    return (new SchemaBuilder)->fields($command->toDefinition(30));
}

it('maps a text variable to a text input', function (): void {
    $fields = builtFields(
        Command::make('x')->run('a {path}')->variables([TextVariable::make('path')]),
    );

    expect($fields)->toHaveCount(1)
        ->and($fields[0])->toBeInstanceOf(TextInput::class)
        ->and($fields[0]->getName())->toBe('path');
});

it('maps a select variable to a select with its options', function (): void {
    $fields = builtFields(
        Command::make('x')->run('a {db}')->variables([
            SelectVariable::make('db')->options(['main' => 'Main', 'reporting' => 'Reporting']),
        ]),
    );

    expect($fields[0])->toBeInstanceOf(Select::class)
        ->and($fields[0]->getOptions())->toBe(['main' => 'Main', 'reporting' => 'Reporting']);
});

it('maps a boolean variable to a toggle', function (): void {
    $fields = builtFields(
        Command::make('x')->run('a {dry}')->variables([BooleanVariable::make('dry')]),
    );

    expect($fields[0])->toBeInstanceOf(Toggle::class);
});

it('maps a model variable to a searchable select', function (): void {
    $fields = builtFields(
        Command::make('x')->run('a {user}')->variables([
            ModelVariable::make('user')->model(TestUser::class),
        ]),
    );

    expect($fields[0])->toBeInstanceOf(Select::class)
        ->and($fields[0]->isSearchable())->toBeTrue();
});

it('carries the label and required flag onto the field', function (): void {
    $fields = builtFields(
        Command::make('x')->run('a {path}')->variables([
            TextVariable::make('path')->label('Backup path')->required(),
        ]),
    );

    expect($fields[0]->getLabel())->toBe('Backup path')
        ->and($fields[0]->isRequired())->toBeTrue();
});

/*
 | Helper text is not asserted here. Filament renders it as a component in the
 | field's below-content child schema, which cannot be read from a detached
 | field — it needs a live container. It is asserted against a rendered modal in
 | tests/Feature/RunActionTest.php instead.
 */

it('carries the variables own validation rules onto the field', function (): void {
    $fields = builtFields(
        Command::make('x')->run('a {path}')->variables([
            TextVariable::make('path')->rules(['string', 'max:10']),
        ]),
    );

    expect($fields[0]->getValidationRules())->toContain('max:10');
});

it('renders a redacted variable as a password input that is not revealed', function (): void {
    $fields = builtFields(
        Command::make('x')->run('a {token}')->variables([
            TextVariable::make('token')->redact(),
        ]),
    );

    expect($fields[0])->toBeInstanceOf(TextInput::class)
        ->and($fields[0]->isPassword())->toBeTrue();
});

it('appends a toggle per flag under a state-safe key', function (): void {
    $fields = builtFields(
        Command::make('x')->run('a')->flags([Flag::make('--force')->label('Force')]),
    );

    expect($fields)->toHaveCount(1)
        ->and($fields[0])->toBeInstanceOf(Toggle::class)
        ->and($fields[0]->getName())->toBe(SchemaBuilder::flagKey('--force'))
        ->and($fields[0]->getName())->not->toContain('-')
        ->and($fields[0]->getLabel())->toBe('Force');
});

it('orders variables before flags', function (): void {
    $fields = builtFields(
        Command::make('x')->run('a {path}')
            ->variables([TextVariable::make('path')])
            ->flags([Flag::make('--force')]),
    );

    expect($fields[0])->toBeInstanceOf(TextInput::class)
        ->and($fields[1])->toBeInstanceOf(Toggle::class);
});

it('builds defaults from variable defaults and flag defaults', function (): void {
    $definition = Command::make('x')->run('a {path}')
        ->variables([TextVariable::make('path')->default('/tmp')])
        ->flags([Flag::make('--force')->default(true)])
        ->toDefinition(30);

    expect((new SchemaBuilder)->defaults($definition))->toBe([
        'path' => '/tmp',
        SchemaBuilder::flagKey('--force') => true,
    ]);
});
