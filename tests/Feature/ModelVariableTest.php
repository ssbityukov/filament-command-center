<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Variables\ModelVariable;
use Bityukov\CommandCenter\Exceptions\UnknownModelValueException;
use Bityukov\CommandCenter\Tests\Fixtures\TestUser;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function (): void {
    TestUser::query()->insert([
        ['id' => 1, 'name' => 'Ada', 'email' => 'ada@test.dev', 'password' => 'x'],
        ['id' => 2, 'name' => 'Grace', 'email' => 'grace@test.dev', 'password' => 'x'],
    ]);
});

it('lists options as value keys mapped to title labels', function (): void {
    $variable = ModelVariable::make('user')->model(TestUser::class);

    expect($variable->options())->toBe([1 => 'Ada', 2 => 'Grace']);
});

it('honours modifyQueryUsing when listing options', function (): void {
    $variable = ModelVariable::make('user')
        ->model(TestUser::class)
        ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('name', 'Ada'));

    expect($variable->options())->toBe([1 => 'Ada']);
});

it('resolves an id that exists in scope', function (): void {
    $variable = ModelVariable::make('user')->model(TestUser::class);

    expect($variable->resolve(2))->toBe('2');
});

it('rejects an id outside the scope of modifyQueryUsing', function (): void {
    $variable = ModelVariable::make('user')
        ->model(TestUser::class)
        ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('name', 'Ada'));

    expect(fn () => $variable->resolve(2))->toThrow(UnknownModelValueException::class);
});

it('names the variable and the value when it rejects an out-of-scope id', function (): void {
    $variable = ModelVariable::make('tenant')->model(TestUser::class);

    $resolve = fn () => $variable->resolve(999);

    expect($resolve)->toThrow(UnknownModelValueException::class, 'tenant')
        ->and($resolve)->toThrow(UnknownModelValueException::class, '999');
});

it('resolves against a custom value attribute', function (): void {
    $variable = ModelVariable::make('user')
        ->model(TestUser::class)
        ->valueAttribute('email')
        ->titleAttribute('name');

    expect($variable->options())->toBe(['ada@test.dev' => 'Ada', 'grace@test.dev' => 'Grace'])
        ->and($variable->resolve('grace@test.dev'))->toBe('grace@test.dev');
});

it('returns null for a blank optional value without querying', function (): void {
    $variable = ModelVariable::make('user')->model(TestUser::class);

    expect($variable->resolve(null))->toBeNull()
        ->and($variable->resolve(''))->toBeNull();
});

it('throws when no model class has been set', function (): void {
    expect(fn () => ModelVariable::make('user')->options())
        ->toThrow(UnknownModelValueException::class);
});
