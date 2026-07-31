# Security policy

## Supported versions

| Version | Supported |
| ------- | --------- |
| 1.x     | Yes       |
| < 1.0   | No        |

Fixes land on the latest 1.x release. There are no long-term support branches.

## Reporting a vulnerability

**Do not open a public issue, discussion, or pull request for a security
problem.** A public report is a working exploit handed to every installation
that has not upgraded yet.

Report privately, by either route:

- [Open a private security advisory](https://github.com/ssbityukov/filament-command-center/security/advisories/new)
  on GitHub. This is preferred — the report, the discussion, and the fix stay in
  one place until the advisory is published.
- Email **s.s.bityukov@gmail.com** with `filament-command-center` in the subject.

A useful report includes the package version, the Laravel and PHP versions, what
an attacker gains, and the smallest sequence of steps that demonstrates it. A
failing test or a minimal reproduction is worth more than a description.

## What to expect

- Acknowledgement within 7 days.
- An assessment — whether it is in scope, and the intended fix — within 30 days.
- Credit in the release notes and the advisory, unless you ask to stay anonymous.

Please give a fix a reasonable window before disclosing publicly. If a report
goes unanswered past the timelines above, treat that as license to disclose.

## Scope

[`docs/security.md`](https://github.com/ssbityukov/filament-command-center/blob/main/docs/security.md)
states the threat model this package is built against: the adversary is a
lower-privileged panel user reaching for arbitrary command execution, or a
compromised admin session.

In scope — anything that breaks one of the documented controls:

- Running a command that is absent from every registered source.
- Getting a submitted value to act as a command, an option, or a shell
  metacharacter rather than as an argument.
- Bypassing the authorization gate on a command a user may not run.

Out of scope:

- Anything that requires commit access to the application's `config/`. Whoever
  edits the allow-list can already deploy code.
- Vulnerabilities in Laravel, Filament, or Symfony themselves — report those to
  the projects that own them.
- A panel deliberately configured to expose a dangerous command to untrusted
  users. The package executes what it is given the keys to.
