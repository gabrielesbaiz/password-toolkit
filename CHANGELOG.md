# Changelog

All notable changes to `password-toolkit` are documented here.

## 2.0.0 — unreleased

A rewrite. See [UPGRADE.md](UPGRADE.md) before you deploy.

### Added

- **Locales.** Adjectives live under `src/Data/Adjectives/{locale}/` and resolve
  through a fallback chain — themed file, locale default, fallback locale — so a
  new language needs one `_default.json`, not 91 themed files. An English pack
  of 224 adjectives ships with the package.
- **Word order follows the language.** Each locale declares
  `adjective_position` in its `_default.json`: Italian puts the adjective after
  the noun (`Goldrake-Mitico`), English puts it before
  (`Legendary-Goldrake`). Getting this backwards produces passwords that read as
  broken to a native speaker, which defeats the point of a memorable password.
  Override with the `adjective_position` config key or `->adjectiveAt(…)` on the
  builder.
- **Your own dictionaries**, from three routes: a directory listed in
  `dictionaries.paths`, an inline block in `dictionaries.custom`, or
  `PasswordToolkit::registerDictionary()` at runtime. A dictionary without its
  own adjectives falls through to the locale default, so the minimum viable
  personal collection is one JSON file of names.
- **A fluent builder.** `PasswordToolkit::make()->locale('en')->only([…])
  ->digits(6)->leet('basic')->generate()` overrides any option for one call
  without touching config. Every method returns a new builder, so a partially
  configured one is safe to hold and reuse.
- **`password-toolkit:generate`**, with `--report`, `--json`, `--list` and an
  override flag for every option.
- **`password-toolkit:make-dictionary`**, which scaffolds a valid dictionary —
  and optionally its adjective file — into a configured path.
- **`StrongPassword` validation rule**, scoring a user's chosen password against
  a strength floor, with a translated message that names both the achieved and
  the required band.
- **Translations** for strength labels, crack-time units and the validation
  message, in English and Italian.
- **Contracts.** `DictionaryRepository` and `PasswordGenerator` are bound in the
  container, so either half can be replaced without subclassing.
- `Strength`, `Leetspeak`, `NumbersPosition` and `Gender` enums, carrying the
  behaviour that used to be `match` statements on raw strings.

### Changed

- **The service is an object, not a static class**, bound as a singleton. The
  facade now actually resolves through the container, so `swap()` and
  `shouldReceive()` work — in 1.x the accessor pointed at a class with no
  binding and every call resolved statically.
- **Names are picked uniformly across entries, not across files.** 1.x picked a
  file and then an entry, which made a 20-entry dictionary as likely as a
  200-entry one while reporting entropy as though every name were equally
  likely. The two now agree, and reported figures are lower and correct.
- **Every random choice uses `random_int()`.** 1.x used it only for the numeric
  segment and `Collection::random()` — that is, `mt_rand()` — for names and
  adjectives.
- **Dictionaries are decoded once per process.** 1.x walked both data
  directories and decoded JSON on every single call, so a batch of a thousand
  passwords did a thousand directory walks.
- **Configuration is a selection block**, not 91 booleans that had to be kept in
  sync with the filesystem by hand. A shim translates the 1.x `name_types`
  shape, with a deprecation, so existing installs keep working.
- `generate()` takes no count and returns `string`; batches go through
  `generateMany()`, which always returns exactly the count requested.
- `generate()` throws `NoDictionariesEnabledException` rather than returning
  `null` for what is a configuration mistake.
- `poolSizes()` returns `['names' => …, 'adjectives' => …]` instead of a
  positional pair.
- `clearPoolCache()` is now `flushCache()`; the old name is a deprecated alias.
- `StrengthReport` implements `Arrayable`, `Jsonable` and `JsonSerializable`,
  and carries the `Strength` enum alongside the existing properties.
- `leetspeak_conversion` spells its off state `'none'`; `'no'` still parses.
- Requires PHP 8.2 and supports Laravel 10 through 13.

### Fixed

- **Crack time no longer overflows to `INF`.** `2 ** $bits` overflowed well
  inside the range a long password produces, which made
  `StrengthReport::toArray()` unserialisable for exactly the strongest
  passwords. It is computed in log space now.
- **Time strings are pluralised.** `humanizeSeconds(90)` returned
  `"1 minutes"`; it returns `"1 minute"`, in the active locale.
- Leetspeak is applied when `add_numbers` is false. 1.x skipped the transform
  entirely on that branch.
- 48 duplicate adjective entries removed across nine files.
- Multibyte handling is consistent — the repetition penalty counted bytes while
  the charset model counted characters.

### Removed

- `.php-cs-fixer.php` and `tlint.json`, neither of which any script ran. Pint is
  the formatter, pinned by `pint.json`.
- The `Database\Factories\` autoload entry, which pointed at a directory that
  never existed.

## 1.7.0 and earlier

See the [releases](../../releases).
