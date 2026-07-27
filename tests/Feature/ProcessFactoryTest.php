<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Definitions\Command;
use Bityukov\CommandCenter\Execution\ProcessFactory;

beforeEach(function (): void {
    config()->set('command-center.max_sync_timeout', 30);
});

it('clamps a synchronous command to the max sync timeout', function (): void {
    $definition = Command::make('slow')->run('backup:run')->timeout(600)->toDefinition(30);

    $process = app(ProcessFactory::class)->make($definition, ['backup:run']);

    expect($process->getTimeout())->toBe(30.0);
});

it('leaves a synchronous command below the cap alone', function (): void {
    $definition = Command::make('quick')->run('cache:clear')->timeout(5)->toDefinition(30);

    $process = app(ProcessFactory::class)->make($definition, ['cache:clear']);

    expect($process->getTimeout())->toBe(5.0);
});

it('lets a queued command keep its full timeout', function (): void {
    $definition = Command::make('slow')->run('backup:run')->timeout(600)->queue()->toDefinition(30);

    $process = app(ProcessFactory::class)->make($definition, ['backup:run']);

    expect($process->getTimeout())->toBe(600.0);
});

it('follows the configured cap rather than a hard coded one', function (): void {
    config()->set('command-center.max_sync_timeout', 120);

    $definition = Command::make('slow')->run('backup:run')->timeout(600)->toDefinition(30);

    $process = app(ProcessFactory::class)->make($definition, ['backup:run']);

    expect($process->getTimeout())->toBe(120.0);
});
