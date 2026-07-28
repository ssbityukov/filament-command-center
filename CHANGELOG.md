# Changelog

All notable changes to this package are documented here.

## Unreleased

### Definitions and argv safety

- Immutable `CommandDefinition` value objects, built by a fluent `Command` builder or parsed from
  array config by one shared parser.
- Text, select, boolean and model variables; flags; per-command timeout, queue, ability,
  concurrency, rate limit, confirmation and progress settings.
- `ArgvBuilder` turns a template plus validated input into an argument **vector**. Values are never
  escaped, quoted or filtered — they are passed as discrete argv elements, so shell metacharacters
  carry no meaning.
- A value may not become an option: a token that opens an argv element is refused when its value
  starts with `-`, unless the variable opts in with `allowsLeadingDash()`.
- A token may not sit in the command position, so a submitted value can never choose the binary.
- Model variables re-resolve the submitted id through their own scoped query, so a scoped select
  cannot reach a record outside its scope.
- `command-center:check` validates every definition from every source and exits non-zero on error.
- CI guards asserting no shell execution primitives in `src/`, and no Filament outside the UI layer.

### Filament layer

- `CommandCenterPlugin`, an optional cluster, and three pages: catalogue, run view, history.
- `SchemaBuilder` maps each variable type onto a Filament field in one place.
- A run modal with a display-only preview of the resolved command, built by the same `ArgvBuilder`
  the runner uses.
- Run visibility follows the command's own ability: if you may run it, you may read what it did.
- History deletion and pruning both require `command-center:prune-history`.

### Queued runs and live output

- `RunDispatcher` as the single entry point: authorization, rate limit, concurrency lock, then
  queue or inline. Rejections are recorded as runs.
- `RunCommandJob` re-checks authorization and cancellation before starting, and never retries.
- Cache-backed live output with a head-and-tail cap, incremental polling in the run view, progress
  from a documented sentinel, and cancel.
- Per-command concurrency slots, TTL-bounded, failing closed on a cache driver without atomic locks.
- Per-command and global rate limiting.

### Database layer and release

- `DatabaseRunStore` behind the same `RunStore` contract, with a shared contract suite that runs
  against both drivers, plus a publishable migration and `command-center:prune`.
- `DatabaseSource` reading commands from the database through the same parser config uses, with a
  structured editor guarded by `command-center:manage-commands`. `command-center:check` fails when
  the source is enabled without that gate.
- README, upgrade guide from Nova Command Center, and a CI matrix.
