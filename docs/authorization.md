# Authorization

## Per-command ability

```php
'backup-db' => [
    'run' => 'backup:run',
    'ability' => 'run-backups',
],
```

The ability is checked with `Gate::forUser($user)->allows($ability, [$definition])`,
so the definition is available to the gate:

```php
Gate::define('run-backups', function (User $user, CommandDefinition $definition): bool {
    return $user->isAdmin() && $definition->group === 'Maintenance';
});
```

A command with no ability is runnable by anyone who can reach the panel. That is
a deliberate default for harmless commands, and a reason to set one on anything
that is not.

## Where it is enforced

Hiding a command from the catalogue is UX, not a boundary. The check runs:

1. In the catalogue, to decide what is rendered — denied commands are absent
   from the payload, not hidden in it.
2. In the run action, because a crafted Livewire call can name any key.
3. In `RunDispatcher`, which is the entry point every caller goes through.
4. In `RunCommandJob`, because a gate can be revoked between dispatch and
   execution.

## Reading a run

A run record carries the argv and the full output of the command that produced
it, so it is exactly as sensitive as that command. `RunVisibility` gates reads
by the same ability: if you may run it, you may read what it did.

A run whose command has since left every source stays visible — there is no
ability left to check, and hiding it would erase the audit trail rather than
protect it.

Visibility is re-checked on every poll and on cancel, not only when the page
opens: a gate revoked while the page sits there must stop the stream.

## Extra visibility rules

The fluent API takes a closure for anything a gate cannot express:

```php
Command::make('backup-db')
    ->ability('run-backups')
    ->visible(fn (?Authenticatable $user, CommandDefinition $definition): bool
        => app()->environment('production'));
```

Both must pass. Closures are unavailable in array config, which must survive
`config:cache`.

## Package abilities

| Ability | Guards |
|---|---|
| `command-center:prune-history` | Deleting run records, individually or in bulk |
| `command-center:manage-commands` | The database command editor |

Both are configurable under `abilities`. The managing ability decides who can
define what the panel executes — treat it as deploy access, not as an editor
role. `command-center:check` fails when the database source is enabled and that
gate is undefined.
