<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\Variables\TextVariable;
use Bityukov\CommandCenter\Exceptions\InvalidDefinitionException;
use Bityukov\CommandCenter\Exceptions\UnsafeValueException;
use Bityukov\CommandCenter\Execution\ArgvBuilder;

/*
 | The datasets 'hostile values' and 'leading dash values' live in tests/Pest.php
 | so the feature suite can drive the same inputs through a real process.
 */

it('keeps a hostile standalone value as exactly one argv element', function (string $hostile): void {
    $definition = Command::make('x')
        ->run('backup:run {path}')
        ->variables([TextVariable::make('path')])
        ->toDefinition(defaultTimeout: 60);

    $argv = (new ArgvBuilder)->build($definition, ['path' => $hostile]);

    expect($argv)->toHaveCount(2)
        ->and($argv[0])->toBe('backup:run')
        ->and($argv[1])->toBe($hostile);
})->with('hostile values');

it('keeps a hostile embedded value inside one argv element', function (string $hostile): void {
    $definition = Command::make('x')
        ->run('backup:run --path={path}')
        ->variables([TextVariable::make('path')])
        ->toDefinition(defaultTimeout: 60);

    $argv = (new ArgvBuilder)->build($definition, ['path' => $hostile]);

    expect($argv)->toHaveCount(2)
        ->and($argv[1])->toBe('--path='.$hostile);
})->with('hostile values');

it('keeps a leading dash value verbatim inside an embedded element', function (string $hostile): void {
    $definition = Command::make('x')
        ->run('backup:run --path={path}')
        ->variables([TextVariable::make('path')])
        ->toDefinition(defaultTimeout: 60);

    $argv = (new ArgvBuilder)->build($definition, ['path' => $hostile]);

    expect($argv)->toHaveCount(2)
        ->and($argv[1])->toBe('--path='.$hostile);
})->with('leading dash values');

it('never escapes or mutates the submitted value', function (string $hostile): void {
    $definition = Command::make('x')
        ->run('cmd {v}')
        ->variables([TextVariable::make('v')])
        ->toDefinition(defaultTimeout: 60);

    $argv = (new ArgvBuilder)->build($definition, ['v' => $hostile]);

    expect($argv[1])->toBe($hostile)
        ->and($argv[1])->not->toContain('\\\\');
})->with('hostile values');

it('rejects a leading dash value in a standalone token', function (string $hostile): void {
    $definition = Command::make('env-dump')
        ->run('env {which}')
        ->variables([TextVariable::make('which')])
        ->toDefinition(defaultTimeout: 60);

    expect(fn () => (new ArgvBuilder)->build($definition, ['which' => $hostile]))
        ->toThrow(UnsafeValueException::class);
})->with('leading dash values');

it('names the command and the variable when it rejects a leading dash', function (): void {
    $definition = Command::make('env-dump')
        ->run('env {which}')
        ->variables([TextVariable::make('which')])
        ->toDefinition(defaultTimeout: 60);

    $build = fn () => (new ArgvBuilder)->build($definition, ['which' => '--env=production']);

    expect($build)->toThrow(UnsafeValueException::class, 'env-dump')
        ->and($build)->toThrow(UnsafeValueException::class, 'which');
});

it('passes a leading dash value through when the variable opts in', function (string $hostile): void {
    $definition = Command::make('x')
        ->run('cmd {v}')
        ->variables([TextVariable::make('v')->allowsLeadingDash()])
        ->toDefinition(defaultTimeout: 60);

    $argv = (new ArgvBuilder)->build($definition, ['v' => $hostile]);

    expect($argv)->toBe(['cmd', $hostile]);
})->with('leading dash values');

it('rejects a leading dash value in a token that opens an element', function (): void {
    $definition = Command::make('env-dump')
        ->run('env {which}=1')
        ->variables([TextVariable::make('which')])
        ->toDefinition(defaultTimeout: 60);

    expect(fn () => (new ArgvBuilder)->build($definition, ['which' => '--env']))
        ->toThrow(UnsafeValueException::class);
});

it('rejects a leading dash value in the first of two adjacent tokens', function (): void {
    $definition = Command::make('env-dump')
        ->run('env {prefix}{name}')
        ->variables([TextVariable::make('prefix'), TextVariable::make('name')])
        ->toDefinition(defaultTimeout: 60);

    expect(fn () => (new ArgvBuilder)->build($definition, ['prefix' => '--force', 'name' => 'x']))
        ->toThrow(UnsafeValueException::class);
});

it('allows a leading dash value in a token that does not open an element', function (): void {
    $definition = Command::make('x')
        ->run('cmd {prefix}{name}')
        ->variables([TextVariable::make('prefix'), TextVariable::make('name')])
        ->toDefinition(defaultTimeout: 60);

    $argv = (new ArgvBuilder)->build($definition, ['prefix' => 'safe', 'name' => '--force']);

    expect($argv)->toBe(['cmd', 'safe--force']);
});

it('rejects a leading dash coming from a variable default', function (): void {
    $definition = Command::make('x')
        ->run('cmd {v}')
        ->variables([TextVariable::make('v')->default('--force')])
        ->toDefinition(defaultTimeout: 60);

    expect(fn () => (new ArgvBuilder)->build($definition, []))
        ->toThrow(UnsafeValueException::class);
});

it('does not let a value inject an additional argv element', function (): void {
    $definition = Command::make('x')
        ->run('cmd {v}')
        ->variables([TextVariable::make('v')])
        ->toDefinition(defaultTimeout: 60);

    $argv = (new ArgvBuilder)->build($definition, ['v' => 'one two three']);

    expect($argv)->toBe(['cmd', 'one two three']);
});

it('does not let a value introduce a new token', function (): void {
    $definition = Command::make('x')
        ->run('cmd {v}')
        ->variables([TextVariable::make('v')])
        ->toDefinition(defaultTimeout: 60);

    $argv = (new ArgvBuilder)->build($definition, ['v' => '{other}']);

    expect($argv)->toBe(['cmd', '{other}']);
});

it('refuses to build a shell definition whose command position is a token', function (): void {
    Command::make('x')
        ->shell()
        ->run('{bin} hi')
        ->variables([TextVariable::make('bin')])
        ->toDefinition(defaultTimeout: 60);
})->throws(InvalidDefinitionException::class, 'x');

it('refuses an artisan definition that is nothing but a token', function (): void {
    Command::make('anything')
        ->run('{cmd}')
        ->variables([TextVariable::make('cmd')])
        ->toDefinition(defaultTimeout: 60);
})->throws(InvalidDefinitionException::class);

it('refuses a token embedded in the command position', function (): void {
    Command::make('x')
        ->shell()
        ->run('/usr/bin/{bin} hi')
        ->variables([TextVariable::make('bin')])
        ->toDefinition(defaultTimeout: 60);
})->throws(InvalidDefinitionException::class);

it('still allows tokens in every position after the first', function (): void {
    $definition = Command::make('x')
        ->run('route:list {path} --json')
        ->variables([TextVariable::make('path')])
        ->toDefinition(defaultTimeout: 60);

    expect((new ArgvBuilder)->build($definition, ['path' => 'up']))
        ->toBe(['route:list', 'up', '--json']);
});
