# Configuration

Every key in `config/command-center.php`, and what happens if you get it wrong.

## Execution

| Key | Default | What it does |
|---|---|---|
| `php_binary` | `env('COMMAND_CENTER_PHP_BINARY')` | Absolute path to PHP for Artisan runs. Null auto-detects with Symfony's `PhpExecutableFinder`. |
| `working_directory` | `null` | Working directory for spawned processes. Null means the app base path. |
| `default_timeout` | `30` | Applied to commands that set none. |
| `max_sync_timeout` | `30` | Ceiling for a synchronous run. |
| `shell.enabled` | `false` | Whether shell definitions may run at all. |

`default_timeout` equals `max_sync_timeout` on purpose. A command without an
explicit timeout may still run synchronously, and a synchronous run cannot
outlive the HTTP request that started it. Raising the default without queueing
buys you an orphaned process when the web server gives up on the request.

`shell.enabled` does not unlock arbitrary commands. Shell definitions stay
allow-listed and still execute as argument vectors; the flag only decides
whether that category is permitted.

## Sources

```php
'sources' => [
    ConfigSource::class,
    // \Bityukov\CommandCenter\Sources\DatabaseSource::class,
],
```

Resolved in order; a later source wins when two define the same key. See
[Command sources](command-sources.md).

## Output

| Key | Default | What it does |
|---|---|---|
| `output.max_bytes` | `262144` | Cap on buffered output. Head and tail are kept, the middle is dropped. |
| `output.ttl_minutes` | `60` | How long live output and progress survive in cache. |
| `output.poll_ms` | `750` | How often the run view asks for new bytes. |

## Rate limiting

```php
'rate_limit' => ['global' => null],
```

`null` disables it. `['attempts' => 10, 'minutes' => 60]` applies to every
command, on top of any per-command limit. Both are keyed by user; an
unauthenticated caller shares one bucket rather than being exempt.

## History

| Key | Default | What it does |
|---|---|---|
| `history.driver` | `env('COMMAND_CENTER_HISTORY_DRIVER', 'cache')` | `cache` or `database`. |
| `history.max` | `100` | Cache driver only: how many runs the index keeps. |
| `history.ttl_hours` | `168` | Cache driver only: how long a run survives. |
| `history.store` | `null` | Which cache store to use. Null means the default. |

The cache driver needs no migration, which is why it is the default. It is also
erased by `cache:clear`. Where the audit trail matters, use `database` and
publish the migration.

## Abilities

```php
'abilities' => [
    'prune_history' => 'command-center:prune-history',
    'manage_commands' => 'command-center:manage-commands',
],
```

See [Authorization](authorization.md).

## Commands

Empty by default, deliberately: a package that ships a populated allow-list
decides for you what your panel can execute. The published config carries a
commented starter set.
