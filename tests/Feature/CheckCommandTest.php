<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;

it('passes with a clean configuration', function (): void {
    config()->set('command-center.commands', [
        'clear-cache' => ['run' => 'cache:clear'],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('1 command checked')
        ->assertExitCode(0);
});

it('fails when a token has no variable', function (): void {
    config()->set('command-center.commands', [
        'broken' => ['run' => 'backup:run {database}'],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('{database}')
        ->assertExitCode(1);
});

it('warns when a variable has no token', function (): void {
    config()->set('command-center.commands', [
        'x' => ['run' => 'cache:clear', 'variables' => ['unused' => ['type' => 'text']]],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('unused')
        ->assertExitCode(0);
});

it('fails when a shell command exists but shell mode is disabled', function (): void {
    config()->set('command-center.shell.enabled', false);
    config()->set('command-center.commands', [
        'disk' => ['run' => 'df -h', 'type' => 'shell'],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('shell')
        ->assertExitCode(1);
});

it('warns rather than fails when an ability is not explicitly defined as a gate', function (): void {
    config()->set('command-center.commands', [
        'x' => ['run' => 'cache:clear', 'ability' => 'undefined-gate'],
    ]);

    // Gate::has() cannot see an ability served by a Gate::before callback, which
    // is how spatie/laravel-permission and super-admin catch-alls work, so this
    // may be a correctly configured app. An ability that really is undefined
    // fails closed at runtime, so a warning is the honest severity.
    // Laravel matches only one expected substring per written line, so the two
    // halves of the message are asserted in separate invocations.
    $this->artisan('command-center:check')
        ->expectsOutputToContain('undefined-gate')
        ->assertExitCode(0);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('Gate::before')
        ->assertExitCode(0);
});

it('passes for an ability served only by a Gate::before callback', function (): void {
    Gate::before(fn (): ?bool => true);

    config()->set('command-center.commands', [
        'x' => ['run' => 'cache:clear', 'ability' => 'run-backups'],
    ]);

    expect(Gate::has('run-backups'))->toBeFalse();

    $this->artisan('command-center:check')->assertExitCode(0);
});

it('passes when the ability gate is defined', function (): void {
    Gate::define('defined-gate', fn (): bool => true);

    config()->set('command-center.commands', [
        'x' => ['run' => 'cache:clear', 'ability' => 'defined-gate'],
    ]);

    $this->artisan('command-center:check')->assertExitCode(0);
});

it('fails when a synchronous command exceeds the sync timeout cap', function (): void {
    config()->set('command-center.max_sync_timeout', 30);
    config()->set('command-center.commands', [
        'slow' => ['run' => 'backup:run', 'timeout' => 600],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('max_sync_timeout')
        ->assertExitCode(1);
});

it('passes when a slow command is queued', function (): void {
    config()->set('command-center.max_sync_timeout', 30);
    config()->set('queue.default', 'database');
    config()->set('command-center.commands', [
        'slow' => ['run' => 'backup:run', 'timeout' => 600, 'queue' => true],
    ]);

    $this->artisan('command-center:check')->assertExitCode(0);
});

it('warns when a queued command runs on the sync queue connection', function (): void {
    config()->set('queue.default', 'sync');
    config()->set('command-center.commands', [
        'x' => ['run' => 'backup:run', 'queue' => true],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('run inline inside the request')
        ->assertExitCode(0);
});

it('fails when a model variable names a missing class', function (): void {
    config()->set('command-center.commands', [
        'x' => [
            'run' => 'cmd {user}',
            'variables' => ['user' => ['type' => 'model', 'model' => 'App\\Models\\Nope']],
        ],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('App\\Models\\Nope')
        ->assertExitCode(1);
});

it('fails when the run template is only whitespace', function (): void {
    config()->set('command-center.commands', [
        'blank' => ['run' => '   '],
    ]);

    // A truly empty string is refused earlier, by ArrayDefinitionParser's
    // missingRun check. Whitespace passes that and reaches check #3.
    $this->artisan('command-center:check')
        ->expectsOutputToContain('empty run template')
        ->assertExitCode(1);
});

it('fails when a definition cannot even be built from an empty run key', function (): void {
    config()->set('command-center.commands', [
        'blank' => ['run' => ''],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('Could not build definitions: Command [blank]')
        ->assertExitCode(1);
});

it('fails when a timeout is below one second', function (): void {
    config()->set('command-center.commands', [
        'x' => ['run' => 'cache:clear', 'timeout' => 0],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('invalid timeout')
        ->assertExitCode(1);
});

it('warns when a select variable has no options', function (): void {
    config()->set('command-center.commands', [
        'x' => [
            'run' => 'cmd {mode}',
            'variables' => ['mode' => ['type' => 'select']],
        ],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('no options')
        ->assertExitCode(0);
});

it('reports the offending command when a token sits in the command position', function (): void {
    config()->set('command-center.shell.enabled', true);
    config()->set('command-center.commands', [
        'arbitrary' => [
            'run' => '{bin} hi',
            'type' => 'shell',
            'variables' => ['bin' => ['type' => 'text']],
        ],
    ]);

    // The definition now throws while being built, so handle()'s try/catch
    // surfaces it. The exception message names the command key, so CI output
    // still points at the offending definition.
    $this->artisan('command-center:check')
        ->expectsOutputToContain('Could not build definitions: Command [arbitrary]')
        ->assertExitCode(1);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('Make the first element a literal')
        ->assertExitCode(1);
});

it('fails on an unknown command type', function (): void {
    config()->set('command-center.commands', [
        'x' => ['run' => 'df -h', 'type' => 'Shell'],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('Could not build definitions')
        ->assertExitCode(1);
});

it('warns when a queued command is configured against the sync queue connection', function (): void {
    config()->set('queue.default', 'sync');
    config()->set('command-center.commands', [
        'backup' => ['run' => 'backup:run', 'queue' => true],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('run inline inside the request')
        ->assertExitCode(0);
});

it('does not warn about the sync queue when no command is queued', function (): void {
    config()->set('queue.default', 'sync');
    config()->set('command-center.commands', [
        'backup' => ['run' => 'backup:run'],
    ]);

    $this->artisan('command-center:check')
        ->doesntExpectOutputToContain('run inline inside the request')
        ->assertExitCode(0);
});
