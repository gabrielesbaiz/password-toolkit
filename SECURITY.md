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
than its own strength report claims, a dictionary or adjective lookup that
escapes the directories it is configured to read, a character reaching the
generated password that should have been stripped, or a crash reachable from a
value an application would reasonably pass in.

**Out of scope:** how an application stores, transmits or rotates the passwords
it gets from here. That is the application's responsibility.

## What the package defends against

**Path traversal through locales and dictionary keys.** Both are interpolated
into `{root}/{locale}/{key}.json`, and an application may pass a request value
into `->locale(...)` or key a dictionary by one. Both are validated against a
strict pattern the moment they enter — in `Options`, in `Dictionary::fromArray`,
and again in `AdjectiveResolver` immediately before the path is built. An
application locale that does not validate is ignored in favour of the fallback
rather than trusted; Laravel's own `setLocale()` guard permits `.` and `..`, so
reaching this package through it is not sufficient.

**Hostile dictionary content.** The README suggests registering a dictionary
from application data, so names are not assumed to be clean. Every segment is
stripped down to letters, digits and the configured separator before assembly,
and runs of whitespace are collapsed first. Quotes, semicolons, angle brackets,
pipes, backslashes, newlines, tabs and NUL bytes cannot reach the generated
password. An entry with no letters or digits at all is rejected where it is
defined rather than silently producing a password missing a segment.

**Unbounded batches.** `generateMany()` builds its result in memory, so it caps
at `PasswordToolkit::MAX_BATCH`. `generateUnique()` gives up after a bounded
number of attempts and says how many it managed, rather than looping forever
when the pool is smaller than the request.

## Design notes for reviewers

These are deliberate. Please do not report them as vulnerabilities.

**Memorable passwords have less entropy than random ones.** That is the entire
trade. A default `Goldrake-Mitico-427193` sits around 37 bits under the
structural model, not the 120-odd bits its length suggests under a naive
charset model. Raise `numbers_digits` if you need more — it is the only setting
that scales. This is why the package ships two entropy models and why
`structuralReport()` — the pessimistic, honest one — is what
`generateWithReport()` returns.

**These are not secrets for machines.** Use `Str::password()` or
`random_bytes()` for API keys, tokens and root credentials. This package is for
passwords a human has to read aloud, type from a sticky note, or remember: user
onboarding, demo accounts, share links, seeded fixtures.

**Leetspeak is credited zero bits.** It is a deterministic transform of an
already-chosen password, so it does not enlarge the space an attacker who knows
the configuration has to search — and that attacker is exactly the one the
structural model assumes. Earlier 2.0 development builds credited 6 and 12 bits
here; those figures modelled an attacker who had not read the config file, and
overstated every leetspeak password. Use leetspeak to satisfy a character-class
policy, not to add strength.

**Selection uses `random_int()`,** not `mt_rand()` or `Collection::random()`,
for names, adjectives and digits alike. 1.x only did so for the digits; that
was a real weakness and it is fixed in 2.0.

**Dictionary paths are read, never written.** Unlike locales and keys, the paths
themselves are taken as given: they come from configuration, which is code. If
your application derives a dictionary *path* from a request, that is a traversal
this package does not attempt to defend against — the directory listing is
deliberately under your control.

**Generated passwords are returned, never logged or persisted.** The package
holds no state about what it produced and writes nothing to disk. Where the
plaintext goes next is the application's decision.
