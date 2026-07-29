# Filament Command Center

Run pre-approved Artisan and shell commands from your Filament panel.

Every command is an entry in an allow-list. There is no free-form command input anywhere, user
input never becomes a structural part of a command line, and each command can require its own gate.

- Allow-listed commands only, defined in config, in the database, or by your own source
- Synchronous or queued execution, with live output, progress and cancel
- Per-command authorization, concurrency limits and rate limits
- Run history with no migration required, or a durable database driver
- `command-center:check` validates every definition in CI

## Status

Stable. 442 tests run against PHP 8.4 and 8.5 × Laravel 12 and 13 in CI, with
PHPStan level 6, Pint, and two guard scripts that assert no shell execution
primitive exists in `src/` and that the core carries no Filament import.

There is no browser suite. Everything behind the login — pages, actions,
authorization, queued runs, live output, the editor — is covered by Livewire
component tests, which is the same bar the package this one is a port of sets.

## Credits

This package is a Filament port of [farsidev/nova-command-center](https://github.com/farsidev/nova-command-center),
which worked out what a command runner in an admin panel should feel like. The
catalogue, the run modal, the live output and the history all follow the shape
it established; the argv model, the source layer and the checks are this
package's own. Thanks to its authors.

## Requirements

PHP 8.4+, Laravel 12 or 13, Filament 5.

The suite runs against each of those combinations in CI. Older versions are not
listed because they are not tested, not because they are known to break.

**Linux and macOS.** Windows is untested and not claimed. The reason is not
effort but behaviour: on Unix an argument array goes to `proc_open` and execs the
binary with no shell involved, while on Windows Symfony always converts it to an
escaped command string. That is a different guarantee, and this package has never
run a test against it.

## Installation

```bash
composer require ssbityukov/filament-command-center
```

Register the plugin on your panel:

```php
use Bityukov\CommandCenter\Filament\CommandCenterPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(CommandCenterPlugin::make());
}
```

Publish the config:

```bash
php artisan vendor:publish --tag=command-center-config
```

Say who may reach the module. **Until you do, nobody sees it** — the gate is
undefined, and Laravel denies an undefined gate:

```php
// app/Providers/AppServiceProvider.php, in boot()
use Illuminate\Support\Facades\Gate;

Gate::define('command-center:access', fn ($user) => $user->is_admin);
Gate::define('command-center:manage-commands', fn ($user) => $user->is_admin);
Gate::define('command-center:prune-history', fn ($user) => $user->is_admin);
```

Swap `is_admin` for whatever your application uses — a role check, a policy
call, an email allow-list. The three abilities are named in
`config/command-center.php` under `abilities`, so you can rename them.

`access` decides whether the catalogue, a run and the history appear at all.
`manage-commands` guards the database editor, and `prune-history` guards
deleting run records. Setting `abilities.access` to `null` shows the module to
everyone who can open the panel; that is a decision worth making on purpose,
because each command's own `ability` is then the only thing left.

`php artisan command-center:check` warns when the access gate is missing, so a
module that has gone quiet reports why instead of looking broken.

Everything publishable, if you need the rest:

| Tag | What it gives you |
|---|---|
| `command-center-config` | `config/command-center.php` — where commands are defined |
| `command-center-migrations` | The `runs` and `commands` tables, for the database history driver and the database source |
| `command-center-views` | The Blade views, if you want to change the markup |

Migrations are published rather than run automatically: an app using the cache
history driver needs no tables, and a package that creates them on install has
outstayed its welcome. Publishing an existing file is skipped rather than
overwritten — pass `--force` if you mean to replace it.

## What you get out of the box

The published config ships a starter set, so the catalogue is not empty on the
first visit: cache and optimisation clears, `cache:forget`, queue restart, failed
job listing and retry, `migrate:status`, `storage:link`, `about`, plus three that
change the running application — `migrate`, `down` and `up`. The last three ask
for confirmation before they run.

It is still an allow-list. Delete what you do not want, and give anything you
consider dangerous its own `ability`:

```php
// config/command-center.php
'commands' => [
    'clear-cache' => [
        'run' => 'cache:clear',
        'label' => 'Clear application cache',
        'group' => 'Maintenance',
        'timeout' => 30,
    ],
    'migrate' => [
        'run' => 'migrate',
        'ability' => 'run-migrations',   // now only this gate may run it
        'confirm' => 'Apply pending migrations?',
    ],
],
```

Nothing outside that array can be executed, whatever a request asks for.

## Variables and flags

A `run` template holds tokens. Each token is filled from a variable and becomes **one** argv
element — never part of a command string:

```php
'route-list' => [
    'run' => 'route:list --path={path}',
    'variables' => [
        'path' => ['type' => 'text', 'label' => 'Path filter', 'rules' => ['string', 'max:255']],
    ],
    'flags' => ['--json' => ['label' => 'As JSON']],
],
```

Variable types: `text`, `select` (with `options`), `boolean`, `model` (with `model`,
`title_attribute`, `value_attribute`). A blank optional variable removes its whole argv element, so
`--path={path}` disappears rather than becoming `--path=`.

`'redact' => true` keeps a value out of run history while still passing it to the process.

## Authorization

```php
'backup-db' => [
    'run' => 'backup:run',
    'ability' => 'run-backups',
],
```

Commands you cannot run are absent from the catalogue payload entirely, and the run action
re-checks the gate server-side. Reading a run's output requires the same ability as running the
command that produced it.

Three package-level abilities, all configurable under `abilities`:

| Ability | Guards |
|---|---|
| `command-center:access` | Seeing the module at all: catalogue, run view, history |
| `command-center:prune-history` | Deleting run records, individually or in bulk |
| `command-center:manage-commands` | The database command editor |

All three deny until your application defines them. If a panel needs its own
rule instead, `CommandCenterPlugin::make()->authorize(fn ($user) => …)`
overrides `access` for that panel.

## Queued commands

```php
'backup-db' => ['run' => 'backup:run', 'queue' => true, 'timeout' => 600],
```

The run view streams output while the job runs, shows progress, and offers Cancel. Emit progress
from your own command with the documented sentinel:

```php
$this->line('##CC_PROGRESS:'.$percent.'##');
```

Nothing is inferred from log volume: a command that reports nothing shows an indeterminate bar.

## Run history

The default `cache` driver needs no migration. For a durable audit trail:

```php
'history' => ['driver' => 'database'],
```

```bash
php artisan vendor:publish --tag=command-center-migrations
php artisan migrate
php artisan command-center:prune --days=30
```

## Commands in the database

Opt in by adding the source:

```php
'sources' => [
    \Bityukov\CommandCenter\Sources\ConfigSource::class,
    \Bityukov\CommandCenter\Sources\DatabaseSource::class,
],
```

The table it reads comes from the published migrations, so publish and run them
first — the editor has nowhere to write otherwise:

```bash
php artisan vendor:publish --tag=command-center-migrations
php artisan migrate
```

This enables a structured editor in the panel, guarded by `command-center:manage-commands`.
`command-center:check` **fails** if the source is enabled while that gate is undefined.

## Validating in CI

```bash
php artisan command-center:check
```

Exits non-zero on unknown tokens, variables with no token, shell commands while shell mode is off,
undefined abilities, invalid timeouts, and an unguarded database editor.

## Security posture

1. **Allow-list only.** A command absent from every source cannot be run by any request. The HTTP
   payload carries a command key, never a command string.
2. **The package never builds a command string.** Execution always uses `Process` with an argument
   array; `Process::fromShellCommandline()` appears nowhere in `src/`, enforced by a CI script. A
   value containing `; rm -rf /` stays one argv element.
3. **Shell mode is off by default.** Enabling it never unlocks arbitrary commands — shell
   definitions remain allow-listed and are executed as argv vectors.
4. **Authorization is enforced server-side** in the run action, in the dispatcher, and again inside
   the queued job, because a gate can be revoked between dispatch and execution.
5. **Input is validated before substitution.** A model variable re-resolves the submitted id
   through its own scoped query, so a scoped select cannot reach another tenant's record.
6. **A token cannot become an option or the binary.** A value opening an argv element may not start
   with `-` unless the variable opts in, and a token may not sit in the command position at all.

## Limitations

Read these before adopting. They are properties of the design, not bugs.

- **Anyone who can edit the allow-list can run anything the PHP process can.** That means config
  write access, and it means the database source table. The database source is the
  highest-privilege surface in the package — treat write access to it as deploy access.
- **`redact` hides a value from history, not from the OS.** A secret passed as an argv element may
  be visible to other users on the host via `ps`. Pass secrets through environment variables.
- **The package cannot sandbox what a command does once running.** It controls which commands run
  and with what arguments. Nothing more.
- **A `run` template is split on whitespace before substitution**, so a literal path containing a
  space cannot be written into it. Pass such a path as a variable — variable values are substituted
  after the split and always stay one argument.
- **Cancellation is only noticed between chunks of output.** A command that has fallen silent
  cannot be interrupted; its own timeout bounds that case. Cancelling a **synchronous** run barely
  works at all — the request setting the flag and the request running the process are different PHP
  processes. Cancel is for queued runs.
- **The cache history driver is capped and TTL-bounded.** `cache:clear` erases it. Use the database
  driver where the audit trail matters.
- **The database editor does not edit variables yet.** Define commands with variables in config.
- **A command that fails to start** does not always surface as a thrown exception; Symfony may fall
  back to a shell wrapper, so the run is recorded as failed with exit code 126 or 127 and the
  diagnostic in its output rather than in the error field.

## Deep dives

| Guide | What it covers |
|---|---|
| [Configuration](docs/configuration.md) | Every config key, and what breaks if it is wrong |
| [Command sources](docs/command-sources.md) | Config, database and custom sources |
| [Security model](docs/security.md) | The threat model and every control |
| [Authorization](docs/authorization.md) | Gates, abilities and who may read a run |
| [Queued execution and progress](docs/queued-execution.md) | Queueing, live output, progress, cancel |

## Testing

```bash
composer test
```

## License

MIT. See [LICENSE](LICENSE).
