# Upgrading from Nova Command Center

This package is a functional port of [`farsidev/nova-command-center`](https://github.com/farsidev/nova-command-center)
to Filament. The concepts map closely; the differences that matter are listed below rather than
smoothed over.

## Registration

Nova registers a tool; Filament registers a plugin.

```php
// Nova
->tools([new \Farsidev\CommandCenter\CommandCenter])

// Filament
->plugin(\Bityukov\CommandCenter\Filament\CommandCenterPlugin::make())
```

## Config keys

| Nova | This package | Notes |
|---|---|---|
| `commands.*.command` | `commands.*.run` | Same template syntax, `{token}` placeholders |
| `commands.*.name` | `commands.*.label` | |
| `commands.*.description` | `commands.*.help` | Shown on the catalogue card |
| `commands.*.group` | `commands.*.group` | Unchanged |
| `commands.*.timeout` | `commands.*.timeout` | Seconds, unchanged |
| `commands.*.queue` | `commands.*.queue` | `true` or a queue name |
| `commands.*.permission` | `commands.*.ability` | A Laravel gate ability |
| `commands.*.fields` | `commands.*.variables` | See below |
| `commands.*.confirm` | `commands.*.confirm` | `true` or a custom message |

## Fields become variables

Nova fields carry Nova field classes. Here they are plain arrays with a `type`:

```php
// Nova
'fields' => [
    Text::make('Path'),
    Select::make('Database')->options([...]),
],

// This package
'variables' => [
    'path' => ['type' => 'text', 'label' => 'Path'],
    'database' => ['type' => 'select', 'options' => [...]],
],
```

Types: `text`, `select`, `boolean`, `model`.

## What has no equivalent

- **Nova resource fields.** Variables are not Nova/Filament field instances and do not accept
  arbitrary field configuration. What you can express is what the array shape supports.
- **Closures in config.** `visible` and dynamic select options are available through the fluent
  `Command::make()` API, not through array config, because config must stay `config:cache`-safe.
- **Nova's authorization callbacks.** Authorization is a gate ability plus an optional `visible`
  closure on the fluent API.

## What this package adds

- `command-center:check`, which validates every definition and fails CI on a bad one
- A run history driver that needs no migration, plus a durable database driver
- Per-command concurrency locks and rate limits
- A guard against a submitted value becoming a command-line option or the executed binary

## Before you switch

Read the Limitations section of the README. The shell-safety model, the `redact` caveat and the
cancellation semantics are all stated there, and at least one of them usually changes how a team
configures its commands.
