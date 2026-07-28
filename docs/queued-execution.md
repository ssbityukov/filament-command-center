# Queued execution and progress

## When a command must be queued

A synchronous run lives inside the HTTP request, so its timeout is capped by
`max_sync_timeout` (30 seconds by default). Anything longer has to be queued, or
the web server will kill the request and orphan the process with nothing
recording its outcome. `command-center:check` fails on a definition that asks
for more without queueing.

```php
'backup-db' => ['run' => 'backup:run', 'queue' => true, 'timeout' => 600],
'reports' => ['run' => 'reports:build', 'queue' => 'long-running'],
```

`true` uses the default queue; a string names one.

## What the job does

`RunCommandJob` is deliberately thin:

- `$tries = 1`. A privileged command is never silently re-run because a worker
  blinked. If it fails, a human decides.
- `$timeout` sits above the process timeout, so the worker outlives the process
  rather than racing it.
- It re-checks authorization from the serialized user id.
- It checks cancellation **before** starting, because a run cancelled while it
  sat in the queue must not execute.
- It acquires the concurrency slot itself, and releases it in a `finally`.

A queued run is recorded as `queued` the moment it is dispatched, so it appears
in history before a worker touches it.

## Live output

The runner attaches an output callback to the process; each chunk goes to a
cache-backed buffer keyed by run id. The run view polls while the run is
unfinished and asks only for bytes past the offset it already has, so a long log
is not re-serialised on every tick. Polling stops at a terminal state.

Output is capped by `output.max_bytes`, keeping the head and the tail with a
marker between them — the opening lines say what the command decided to do and
the closing lines say how it ended.

Terminal escape sequences are stripped for display. The stored record keeps
exactly what the process wrote.

## Progress

Progress requires the command to cooperate. Nothing is inferred from log volume.

Emit the documented sentinel:

```php
$this->line('##CC_PROGRESS:'.$percent.'##');
```

A percentage in a progress-bar line is also read, with the sentinel taking
precedence. A command that reports nothing shows an indeterminate state rather
than a made-up number.

## Cancel

Cancelling writes a flag to cache. The runner checks it between output chunks
and stops the process — SIGTERM, then SIGKILL after ten seconds.

Two honest limitations:

- A command that has fallen silent cannot be interrupted; its own timeout bounds
  that case.
- Cancelling a **synchronous** run barely works: the request setting the flag and
  the request running the process are different PHP processes, and the second is
  blocked. Cancel is for queued runs.

## Concurrency and rate limits

```php
'backup-db' => ['run' => 'backup:run', 'concurrency' => 1, 'rate_limit' => ['attempts' => 3, 'minutes' => 60]],
```

Concurrency uses one lock per slot rather than a counter — a counter cannot be
decremented safely by a worker that died, whereas a slot lock expires. The TTL is
the command's timeout plus a minute. On a cache driver without atomic locks the
lock **fails closed** rather than pretending the limit is enforced.

A rejected attempt is recorded as a run with state `rejected`. A refusal is part
of the audit trail, not a gap in it.
