<?php

declare(strict_types=1);

it('registers the package config', function (): void {
    expect(config('command-center.default_timeout'))->toBe(60)
        ->and(config('command-center.max_sync_timeout'))->toBe(30)
        ->and(config('command-center.shell.enabled'))->toBeFalse()
        ->and(config('command-center.commands'))->toBe([]);
});
