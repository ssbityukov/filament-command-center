<?php

declare(strict_types=1);
use Bityukov\CommandCenter\Sources\ConfigSource;

return [
    /*
     | Absolute path to the PHP binary used to run Artisan commands.
     | Null means auto-detect via Symfony's PhpExecutableFinder.
     */
    'php_binary' => env('COMMAND_CENTER_PHP_BINARY'),

    /*
     | Working directory for spawned processes. Null means the app base path.
     */
    'working_directory' => null,

    /*
     | Default timeout in seconds applied to commands that do not set their own.
     |
     | This defaults to the same value as max_sync_timeout, because a command
     | that does not specify a timeout may still run synchronously, and a
     | synchronous run cannot outlive the HTTP request that started it. Raise it
     | only for commands you also queue.
     */
    'default_timeout' => 30,

    /*
     | Commands running synchronously may not exceed this timeout, because the
     | web server would terminate the request and orphan the process.
     */
    'max_sync_timeout' => 30,

    /*
     | Shell commands are disabled by default. Enabling this does NOT allow
     | arbitrary commands: shell definitions remain allow-listed and are
     | executed as argument vectors, never as a shell string.
     */
    'shell' => [
        'enabled' => false,
    ],

    /*
     | Command sources, resolved from the container in order. Later sources
     | override earlier ones when two define the same command key.
     */
    'sources' => [
        ConfigSource::class,
    ],

    /*
     | Run history.
     |
     | The cache driver needs no migration, which keeps installation to a single
     | composer require. It is capped and TTL-bounded, so treat it as a recent
     | activity log rather than a permanent audit trail — a cache flush clears
     | it. Plan 4 adds a durable database driver.
     */
    'history' => [
        'max' => 100,
        'ttl_hours' => 168,
        'store' => null,
    ],

    /*
     | Gate abilities the package checks for its own destructive actions.
     */
    'abilities' => [
        'prune_history' => 'command-center:prune-history',
    ],

    /*
     | Command definitions, keyed by a unique slug.
     */
    'commands' => [],
];
