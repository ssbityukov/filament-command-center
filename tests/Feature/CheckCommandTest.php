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

it('fails when an ability has no gate', function (): void {
    config()->set('command-center.commands', [
        'x' => ['run' => 'cache:clear', 'ability' => 'undefined-gate'],
    ]);

    $this->artisan('command-center:check')
        ->expectsOutputToContain('undefined-gate')
        ->assertExitCode(1);
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
        ->expectsOutputToContain('sync')
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
