<?php

declare(strict_types=1);

use Bityukov\CommandCenter\Sources\ConfigSource;

it('builds definitions from the config array', function (): void {
    config()->set('command-center.commands', [
        'clear-cache' => ['run' => 'cache:clear'],
        'migrate' => ['run' => 'migrate --force', 'group' => 'Database'],
    ]);

    $definitions = app(ConfigSource::class)->definitions();

    expect(array_keys($definitions))->toBe(['clear-cache', 'migrate'])
        ->and($definitions['migrate']->group)->toBe('Database')
        ->and($definitions['clear-cache']->timeout)->toBe(60);
});

it('returns an empty array when no commands are configured', function (): void {
    config()->set('command-center.commands', []);

    expect(app(ConfigSource::class)->definitions())->toBe([]);
});
