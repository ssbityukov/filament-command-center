# Changelog

All notable changes to this package are documented here.

## 1.0.2 — 2026-08-04

### Fixed

- **Queued runs reload the actor through the panel auth guard.** `RunCommandJob`
  used `Auth::getProvider()` (the application default), so a Command Center on a
  Filament panel with a custom `authGuard` — central admins, shop staff, and so
  on — looked the user up in the wrong model. It did not merely fail to find
  anybody: the same id in the default table is a different real person, and the
  gate was asked about them. The catalogue now captures the current panel's
  guard on the job, `command-center.auth_guard` remains available as a fallback,
  and history uses the same resolver.

  A guard is resolved through the provider named in its own config rather than
  through the guard instance, so token guards — anything built on `RequestGuard`,
  which carries no `getProvider()` — work too. A guard that names no resolvable
  provider yields no actor, and the run is denied; it never falls back to the
  default provider, because that is the failure being closed.

  Runs queued by 1.0.1 and picked up after this upgrade are handled: a payload
  without the new guard field reads as "no guard", the previous behaviour.

## 1.0.1 — 2026-07-29

### Changed

- **The PHP floor is `^8.3`, down from `^8.4`.** Nothing in `src/` used an 8.4
  feature, and every dependency already allows 8.3, so the higher floor only
  turned away Laravel 12 projects that had not moved yet. CI runs the suite on
  8.3 as well — the row exists before the claim does.

## 1.0.0 — 2026-07-29

### Added

- **A starter set of commands ships in the config.** A fresh install has a
  usable catalogue instead of an empty page: cache and optimisation clears,
  `cache:forget`, queue restart, failed job listing and retry, `migrate:status`,
  `storage:link`, `about`, and — asking for confirmation first — `migrate`,
  `down` and `up`. It remains an allow-list: delete what you do not want, and
  gate what you keep.

### Notes on the 1.0 promise

- The public API is stable from here: config keys, the plugin's fluent methods,
  the `CommandSource` and `RunStore` contracts, and the shape of a command
  definition. Breaking any of them means 2.0.
- An earlier plan made a browser smoke test a condition of 1.0. That condition
  is dropped deliberately rather than quietly: the behaviour it would cover is
  covered by Livewire component tests, and the package this one is a port of
  ships no browser tests at all.

## 0.10.0 — 2026-07-29

### Changed

- **The module is hidden until the application says who may see it.** The
  catalogue, run view and history now check `abilities.access`
  (`command-center:access` by default) instead of being visible to anyone who
  can open the panel. An undefined gate denies, so an upgrade makes the module
  disappear until `Gate::define('command-center:access', …)` is added. Set
  `abilities.access` to `null` to keep the old behaviour deliberately.
  This replaces the split where the pages were open and only the editor was
  gated.
- `command-center:check` warns when the access gate is undefined, so a hidden
  module explains itself.

## 0.9.0 — 2026-07-29

First published release. The number is below 1.0 on purpose: the suite is green in CI across
every supported combination, but the browser suite covers the guest path only. A 1.0 tag waits
on the signed-in path, so until then this release makes no stability promise about the public
API and does not claim end-to-end browser coverage.

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
