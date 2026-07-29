<?php

declare(strict_types=1);

use Bityukov\CommandCenter\CommandRegistry;

it('registers the package config', function (): void {
    expect(config('command-center.default_timeout'))->toBe(30)
        ->and(config('command-center.max_sync_timeout'))->toBe(30)
        ->and(config('command-center.shell.enabled'))->toBeFalse()
        ->and(config('command-center.commands'))->not->toBe([]);
});

it('ships a starter set that parses and passes its own validation', function (): void {
    // The commands shipped in config are the first thing a new install runs, so
    // they are held to the same bar as an adopter's: every one must build, and
    // `command-center:check` must be happy with the lot.
    $definitions = app(CommandRegistry::class)->all();

    expect($definitions)->not->toBeEmpty();

    $this->artisan('command-center:check')->assertExitCode(0);
});

it('asks before anything that changes the running application', function (): void {
    $registry = app(CommandRegistry::class);

    foreach (['migrate', 'down'] as $key) {
        expect($registry->find($key)?->confirm)->not->toBeFalse();
    }
});
