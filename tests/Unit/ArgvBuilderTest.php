<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Definitions\Flag;
use Bityukov\CommandCenter\Definitions\Variables\BooleanVariable;
use Bityukov\CommandCenter\Definitions\Variables\TextVariable;
use Bityukov\CommandCenter\Exceptions\MissingRequiredValueException;
use Bityukov\CommandCenter\Exceptions\UnknownTokenException;
use Bityukov\CommandCenter\Execution\ArgvBuilder;

function buildArgv(Command $command, array $input = []): array
{
    return (new ArgvBuilder)->build($command->toDefinition(defaultTimeout: 60), $input);
}

it('returns the template elements when there are no tokens', function (): void {
    expect(buildArgv(Command::make('x')->run('cache:clear')))->toBe(['cache:clear']);
});

it('collapses repeated whitespace in the template', function (): void {
    expect(buildArgv(Command::make('x')->run("queue:work   --once\t--tries=1")))
        ->toBe(['queue:work', '--once', '--tries=1']);
});

it('substitutes a standalone token', function (): void {
    $command = Command::make('x')
        ->run('backup:run {database}')
        ->variables([TextVariable::make('database')]);

    expect(buildArgv($command, ['database' => 'main']))->toBe(['backup:run', 'main']);
});

it('substitutes a token embedded in an option', function (): void {
    $command = Command::make('x')
        ->run('backup:run --path={path}')
        ->variables([TextVariable::make('path')]);

    expect(buildArgv($command, ['path' => '/var/backups']))->toBe(['backup:run', '--path=/var/backups']);
});

it('drops the whole element when an optional token is blank', function (): void {
    $command = Command::make('x')
        ->run('backup:run --path={path} --keep')
        ->variables([TextVariable::make('path')]);

    expect(buildArgv($command, ['path' => '']))->toBe(['backup:run', '--keep']);
});

it('uses the variable default when no value is submitted', function (): void {
    $command = Command::make('x')
        ->run('backup:run --path={path}')
        ->variables([TextVariable::make('path')->default('/tmp')]);

    expect(buildArgv($command))->toBe(['backup:run', '--path=/tmp']);
});

it('throws when a required token has no value', function (): void {
    $command = Command::make('x')
        ->run('backup:run {database}')
        ->variables([TextVariable::make('database')->required()]);

    buildArgv($command, ['database' => '']);
})->throws(MissingRequiredValueException::class, 'database');

it('throws when a token has no matching variable', function (): void {
    buildArgv(Command::make('x')->run('backup:run {nope}'));
})->throws(UnknownTokenException::class, 'nope');

it('appends enabled flags after the template', function (): void {
    $command = Command::make('x')
        ->run('cache:clear')
        ->flags([Flag::make('--force'), Flag::make('--quiet')]);

    expect(buildArgv($command, ['--force' => true, '--quiet' => false]))
        ->toBe(['cache:clear', '--force']);
});

it('honours a flag default when the input omits it', function (): void {
    $command = Command::make('x')
        ->run('cache:clear')
        ->flags([Flag::make('--force')->default(true)]);

    expect(buildArgv($command))->toBe(['cache:clear', '--force']);
});

it('drops a false boolean variable element', function (): void {
    $command = Command::make('x')
        ->run('migrate --pretend={dry}')
        ->variables([BooleanVariable::make('dry')]);

    expect(buildArgv($command, ['dry' => false]))->toBe(['migrate'])
        ->and(buildArgv($command, ['dry' => true]))->toBe(['migrate', '--pretend=1']);
});

it('substitutes multiple tokens in one element', function (): void {
    $command = Command::make('x')
        ->run('sync {from}:{to}')
        ->variables([TextVariable::make('from'), TextVariable::make('to')]);

    expect(buildArgv($command, ['from' => 'a', 'to' => 'b']))->toBe(['sync', 'a:b']);
});

it('does not let one value be clobbered by a sibling token substitution', function (): void {
    $command = Command::make('x')
        ->run('sync {from}:{to}')
        ->variables([TextVariable::make('from'), TextVariable::make('to')]);

    expect(buildArgv($command, ['from' => '{to}', 'to' => 'REAL']))->toBe(['sync', '{to}:REAL']);
});

it('does not let a value be substituted in either token order', function (): void {
    $command = Command::make('x')
        ->run('sync {from}:{to}')
        ->variables([TextVariable::make('from'), TextVariable::make('to')]);

    expect(buildArgv($command, ['from' => 'REAL', 'to' => '{from}']))->toBe(['sync', 'REAL:{from}']);
});
