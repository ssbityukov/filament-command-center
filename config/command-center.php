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
     |
     | DatabaseSource is opt-in and deliberately not enabled here: anyone who
     | can write its table can run anything the PHP process can. Enable it only
     | with the editor guarded by a strong ability.
     */
    'sources' => [
        ConfigSource::class,
        // \Bityukov\CommandCenter\Sources\DatabaseSource::class,
    ],

    /*
     | Live output.
     |
     | Output is streamed into the cache while a command runs and copied onto
     | the run record when it finishes. The cap bounds a runaway command's log;
     | the head and tail are kept and the middle is dropped.
     */
    'output' => [
        'max_bytes' => 262144,
        'ttl_minutes' => 60,
        'poll_ms' => 750,
    ],

    /*
     | An optional limit applied to every command, on top of any per-command
     | rate limit. Null disables it.
     */
    'rate_limit' => [
        'global' => null,
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
        /*
         | 'cache' needs no migration and is capped and TTL-bounded, which makes
         | it a recent-activity log rather than an audit trail — a cache flush
         | clears it. 'database' is durable and needs the published migration.
         */
        'driver' => 'cache',
        'max' => 100,
        'ttl_hours' => 168,
        'store' => null,
    ],

    /*
     | Gate abilities the package checks for its own destructive actions.
     */
    'abilities' => [
        'prune_history' => 'command-center:prune-history',
        /*
         | Whoever holds this can define what the panel is able to execute.
         | Treat it as deploy access, not as an editor role.
         */
        'manage_commands' => 'command-center:manage-commands',
    ],

    /*
     | Command definitions, keyed by a unique slug.
     */
    /*
     | Command definitions, keyed by a unique slug.
     |
     | Empty on purpose. This is an allow-list, and a package that ships a
     | populated one decides for you what your panel can execute — including
     | things like migrate. You opt in, command by command.
     |
     | A starter set for a typical Laravel app is below. Uncomment what you
     | actually want, then run `php artisan command-center:check`, which will
     | tell you if anything is misconfigured before your users find out.
     |
     | 'commands' => [
     |     'cache-clear' => [
     |         'run' => 'cache:clear',
     |         'label' => 'Clear application cache',
     |         'group' => 'Cache',
     |         'timeout' => 30,
     |     ],
     |     'config-clear' => [
     |         'run' => 'config:clear',
     |         'label' => 'Clear config cache',
     |         'group' => 'Cache',
     |         'timeout' => 30,
     |     ],
     |     'view-clear' => [
     |         'run' => 'view:clear',
     |         'label' => 'Clear compiled views',
     |         'group' => 'Cache',
     |         'timeout' => 30,
     |     ],
     |     'optimize' => [
     |         'run' => 'optimize',
     |         'label' => 'Optimize',
     |         'group' => 'Optimisation',
     |         'timeout' => 120,
     |         'queue' => true,
     |     ],
     |     'optimize-clear' => [
     |         'run' => 'optimize:clear',
     |         'label' => 'Clear all caches',
     |         'group' => 'Optimisation',
     |         'timeout' => 120,
     |         'queue' => true,
     |     ],
     |     'queue-restart' => [
     |         'run' => 'queue:restart',
     |         'label' => 'Restart queue workers',
     |         'group' => 'Queue',
     |         'timeout' => 30,
     |     ],
     |     'queue-failed' => [
     |         'run' => 'queue:failed',
     |         'label' => 'List failed jobs',
     |         'group' => 'Queue',
     |         'timeout' => 30,
     |     ],
     |     'storage-link' => [
     |         'run' => 'storage:link',
     |         'label' => 'Link storage directory',
     |         'group' => 'Maintenance',
     |         'timeout' => 30,
     |     ],
     |     'about' => [
     |         'run' => 'about',
     |         'label' => 'Application overview',
     |         'group' => 'Diagnostics',
     |         'timeout' => 30,
     |     ],
     |
     |     // Destructive. Give it a gate and a confirmation, or leave it out.
     |     'migrate' => [
     |         'run' => 'migrate --force',
     |         'label' => 'Run migrations',
     |         'group' => 'Maintenance',
     |         'timeout' => 300,
     |         'queue' => true,
     |         'ability' => 'run-migrations',
     |         'confirm' => 'This applies pending migrations to the live database. Continue?',
     |     ],
     | ],
     */
    'commands' => [],
];
