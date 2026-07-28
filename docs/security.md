# Security model

## Threat model

The adversary is a lower-privileged panel user trying to reach arbitrary command
execution, or a compromised admin session. The package does not defend against a
developer with commit access to `config/` — anyone who can edit the allow-list
can already deploy code.

## Controls

**1. Allow-list only.** There is no free-form command input anywhere. A command
absent from every registered source cannot be run by any request. The HTTP
payload carries a command key, never a command string.

**2. The package never builds a command string.** Execution always uses
`Process` with an argument array. `Process::fromShellCommandline()` appears
nowhere in `src/`, enforced by `bin/assert-no-shell-commandline.sh` rather than
by convention.

What Symfony does with that array, verified against `Process::start()`:

- **Unix, primary path** — the array goes straight to `proc_open`, which execs
  the binary directly. No shell is involved, so metacharacters carry no meaning.
- **Fallback** — if that call fails, Symfony retries through a shell with each
  element escaped individually.
- **sigchild builds and Windows** — the array is always converted to an escaped
  string.

The package's guarantee is the array boundary. Symfony's escaping is a second
layer, not the absent one.

**3. A value cannot change its own grammatical role.** Two guards, both
structural rather than filtering:

- A token that opens an argv element is refused when its value starts with `-`,
  unless the variable opts in with `allowsLeadingDash()`. Otherwise a submitted
  value could become an option of the target command.
- A token may not sit in the command position at all, so a value can never
  choose which program runs.

Both are all-or-nothing: a value is passed through byte-for-byte or refused.
Nothing is ever rewritten, escaped or filtered for metacharacters.

**4. Shell mode off by default.** Enabling it never unlocks arbitrary commands.

**5. Authorization enforced server-side, three times.** In the page action, in
the dispatcher, and again inside the queued job — a gate can be revoked between
dispatch and execution.

**6. Input validated before substitution.** A model variable re-resolves the
submitted id through its own scoped query, so a scoped select cannot reach
another tenant's record even with a crafted request.

**7. Audit trail.** Every run records who, what argv, when, exit code and
output. `redact` keeps a value out of history while still passing it to the
process.

**8. Blast-radius limits.** Per-command timeout, concurrency lock, rate limit and
output cap bound a runaway command in time, parallelism and storage.

## Limitations

- Anyone who can edit the allow-list config, or write the database source table,
  can run anything the PHP process can.
- `redact` hides a value from history, not from the OS process table. A secret
  passed as an argv element may be visible to other users via `ps`. Pass secrets
  through environment variables.
- The package cannot sandbox what a command does once running.
- A `run` template is split on whitespace before substitution, so a literal path
  containing a space must be passed as a variable.
- Cancellation is noticed between chunks of output; a silent command cannot be
  interrupted, and its timeout is what bounds that case.
- The cache history driver is erased by `cache:clear`.
