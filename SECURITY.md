# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 2.x | Yes |
| 1.x | No — upgrade, see [UPGRADE.md](UPGRADE.md) |

## Reporting a vulnerability

Email **gabriele@sbaiz.com** with a description, the package version, and a
reproduction if you have one. Please do not open a public issue.

You will get an acknowledgement within 5 working days and an assessment within
15. If the report is valid you will be credited in the release notes unless you
ask not to be.

## Scope

**In scope:** anything that causes this package to produce a password weaker
than its own strength report claims, a dictionary path that escapes the
directories it is configured to read, or a crash reachable from a value an
application would reasonably pass in.

**Out of scope:** how an application stores, transmits or rotates the passwords
it gets from here. That is the application's responsibility.

## Design notes for reviewers

These are deliberate. Please do not report them as vulnerabilities.

**Memorable passwords have less entropy than random ones.** That is the entire
trade. A default `Goldrake-Mitico-4271` sits around 35–40 bits under the
structural model, not the 120-odd bits its length suggests under a naive
charset model. This is why the package ships two entropy models and why
`structuralReport()` — the pessimistic, honest one — is what
`generateWithReport()` returns.

**These are not secrets for machines.** Use `Str::password()` or
`random_bytes()` for API keys, tokens and root credentials. This package is for
passwords a human has to read aloud, type from a sticky note, or remember: user
onboarding, demo accounts, share links, seeded fixtures.

**Leetspeak adds almost nothing.** It is a deterministic transform. An attacker
who knows the package applies it gains nothing at all; the small entropy bonus
credited in the report models only the attacker who does not.

**Selection uses `random_int()`,** not `mt_rand()` or `Collection::random()`,
for names, adjectives and digits alike. 1.x only did so for the digits; that
was a real weakness and it is fixed in 2.0.

**Dictionary paths are read, never written, and are not user input.** They come
from configuration. If your application derives one from a request you have
introduced a path traversal that this package cannot defend against.
