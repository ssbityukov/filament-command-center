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
        'driver' => env('COMMAND_CENTER_HISTORY_DRIVER', 'cache'),
        'max' => 100,
        'ttl_hours' => 168,
        'store' => null,
    ],

    /*
     | Gate abilities the package checks for its own destructive actions.
     */
    'abilities' => [
        /*
         | Who sees the module at all: the command catalogue, a run, and the
         | history. Define this gate in your application — an undefined gate
         | denies, so the pages stay hidden until you say who may reach them.
         |
         | Set it to null to make the module visible to everyone who can open
         | the panel. That is a decision, not a default: each command's own
         | 'ability' is then the only thing standing between a panel user and
         | running it.
         */
        'access' => 'command-center:access',
        'prune_history' => 'command-center:prune-history',
        /*
         | Whoever holds this can define what the panel is able to execute.
         | Treat it as deploy access, not as an editor role.
         */
        'manage_commands' => 'command-center:manage-commands',
    ],

    /*
     | Command definitions, keyed by a unique slug.
     |
     | A starter set for a typical Laravel app ships here so a fresh install has
     | something to run. It is still an allow-list: delete what you do not want,
     | add what you do, and give anything dangerous its own 'ability'. Nothing
     | outside this array can ever be executed.
     |
     | Remember that the module is invisible until you define the access gate,
     | so these are not reachable by anyone until you say who may reach them.
     |
     | Run `php artisan command-center:check` after editing. It validates every
     | definition and fails on anything misconfigured.
     |
     | Keys: run, label, group, help, timeout, queue, ability, confirm,
     | concurrency, rate_limit, progress, variables, flags, type
     | ('artisan' by default, 'shell' if shell execution is enabled).
     */
    'commands' => [
        'cache-clear' => [
            'run' => 'cache:clear',
            'label' => 'Clear application cache',
            'group' => 'Cache',
            'help' => 'Flushes the application cache store.',
            'timeout' => 30,
        ],
        'config-clear' => [
            'run' => 'config:clear',
            'label' => 'Clear config cache',
            'group' => 'Cache',
            'timeout' => 30,
        ],
        'view-clear' => [
            'run' => 'view:clear',
            'label' => 'Clear compiled views',
            'group' => 'Cache',
            'timeout' => 30,
        ],
        'cache-forget' => [
            'run' => 'cache:forget {key}',
            'label' => 'Forget a cache key',
            'group' => 'Cache',
            'timeout' => 30,
            'variables' => [
                'key' => [
                    'label' => 'Cache key',
                    'type' => 'text',
                    'required' => true,
                ],
            ],
        ],
        'optimize' => [
            'run' => 'optimize',
            'label' => 'Optimize',
            'group' => 'Optimisation',
            'help' => 'Caches config, routes, views and events.',
            'timeout' => 30,
        ],
        'optimize-clear' => [
            'run' => 'optimize:clear',
            'label' => 'Clear all caches',
            'group' => 'Optimisation',
            'timeout' => 30,
        ],
        'queue-restart' => [
            'run' => 'queue:restart',
            'label' => 'Restart queue workers',
            'group' => 'Queue',
            'help' => 'Gracefully restarts workers, usually after a deploy.',
            'timeout' => 30,
        ],
        'queue-failed' => [
            'run' => 'queue:failed',
            'label' => 'List failed jobs',
            'group' => 'Queue',
            'timeout' => 30,
        ],
        'queue-retry' => [
            'run' => 'queue:retry {id}',
            'label' => 'Retry failed jobs',
            'group' => 'Queue',
            'help' => 'Retry one failed job by id, or "all" for every one of them.',
            'timeout' => 30,
            'variables' => [
                'id' => [
                    'label' => 'Job id',
                    'type' => 'text',
                    'default' => 'all',
                    'required' => true,
                ],
            ],
        ],
        'migrate-status' => [
            'run' => 'migrate:status',
            'label' => 'Migration status',
            'group' => 'Database',
            'timeout' => 30,
        ],
        'storage-link' => [
            'run' => 'storage:link',
            'label' => 'Link storage directory',
            'group' => 'Maintenance',
            'timeout' => 30,
        ],
        'about' => [
            'run' => 'about',
            'label' => 'Application overview',
            'group' => 'Diagnostics',
            'timeout' => 30,
        ],

        /*
         | The rest change the running application. They ship enabled because a
         | command runner that cannot deploy is a toy, but each one asks first,
         | and each is worth an 'ability' of its own before it reaches anyone
         | but you.
         */
        'migrate' => [
            'run' => 'migrate',
            'label' => 'Run migrations',
            'group' => 'Database',
            'help' => 'Applies pending migrations. Queue this one if your migrations are slow.',
            'timeout' => 30,
            'confirm' => 'This applies pending migrations to the live database. Continue?',
            'flags' => [
                '--force' => [
                    'label' => 'Force in production',
                    'default' => true,
                ],
            ],
        ],
        'down' => [
            'run' => 'down',
            'label' => 'Enable maintenance mode',
            'group' => 'Maintenance',
            'help' => 'Takes the application offline — including this panel, so plan how you will bring it back.',
            'timeout' => 30,
            'confirm' => 'This takes the whole application offline, this panel included. Continue?',
        ],
        'up' => [
            'run' => 'up',
            'label' => 'Disable maintenance mode',
            'group' => 'Maintenance',
            'timeout' => 30,
        ],
    ],
];
