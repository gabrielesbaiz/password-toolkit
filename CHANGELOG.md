# Changelog

All notable changes to `password-toolkit` are documented here.

## 2.0.0 — unreleased

A rewrite. See [UPGRADE.md](UPGRADE.md) before you deploy.

### Added

- **Locales.** Adjectives live under `src/Data/Adjectives/{locale}/` and resolve
  through a fallback chain — themed file, locale default, fallback locale — so a
  new language needs one `_default.json`, not 91 themed files. An English pack
  of 224 adjectives ships with the package.
- **A themed adjective pack for every dictionary, in both languages**, capped at
  twenty words so a pack stays specific. A dictionary without one falls back to
  `_default`, which was rebuilt as 193 genuinely neutral adjectives — it had
  been the union of every themed pack, and therefore saturated with food and
  wine vocabulary, which is how a basketball player ended up described as
  *corposo* (full-bodied).
- **English themed adjectives for all 91 dictionaries.** English previously had
  one generic pool, so an English password lost every bit of theming the Italian
  data carries. The 2,811 Italian entries reduce to 626 lemmas once gendered
  pairs are collapsed, so the translation lives in one reviewable glossary —
  `src/Data/Adjectives/_glossary.it-en.json` — and `build/build-adjectives.php`
  applies it. A test regenerates the packs and asserts they come back
  byte-identical, so hand-editing a generated file fails the build.
- **Dictionary metadata.** Every dictionary now carries a `group` (a closed
  vocabulary of twelve themes), free-form `tags`, an `icon` and a `reach`.
  Filter with `->groups()`, `->tagged()` and `->reach()`, with
  `--group` / `--tag` / `--reach` on the command, or from config.
- **`reach`** — `global`, `italian` or `niche` — is how widely recognisable a
  dictionary's names are. A memorable password only works if the reader knows
  the word, so an international application can ask for `global` and get a pool
  its users will actually remember, instead of hand-listing keys.
- **Picker data.** `dictionaries()` returns a translated label, description,
  icon, group, tags, reach, locale and count per dictionary;
  `dictionariesWithSamples()` adds a live sample password; `groups()` and
  `tags()` return the vocabulary in use with counts. Enough for a UI to render a
  dictionary chooser without hardcoding anything.
- **Translated names**, for the 15 dictionaries that hold Italian forms of
  something with a real name elsewhere: Giove is Jupiter, Topolino is Mickey
  Mouse, Albus Silente is Dumbledore, Cervino is the Matterhorn. 211 in all.
  Translations are sparse and additive — a file lists only what differs — so
  every proper noun that should not translate simply keeps its Italian form.
  Barolo is Barolo in every language.
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
- **`generateUnique()`** and `->unique()` on the builder, for a batch with no
  repeats. It gives up with a clear message when the pool is smaller than the
  request rather than looping.
- `Strength`, `Leetspeak`, `NumbersPosition`, `AdjectivePosition` and `Gender`
  enums, carrying the behaviour that used to be `match` statements on raw
  strings.
- **A third word.** `word_count` accepts 2 or 3; three draws a second adjective
  from the same agreeing pool, ordered per locale — `Brave-Mighty-Goldrake` in
  English, `Goldrake-Mitico-Potente` in Italian — with both adjectives agreeing
  with the name's gender. The pair is drawn without replacement, so it is worth
  `log2(A) + log2(A-1)` and reported as its own `second_adjective` component
  rather than folded into `adjective`. A pool too small to supply two distinct
  words falls back to one, and the report credits only what was drawn.
  `->words(3)` on the builder, `--words=` on the command.
- **`case`** — `title`, `lower`, `upper` or `preserve` — with `->casing()` and
  `--case=`. `title` is the default and leaves names spelled as their dictionary
  wrote them, because `MB_CASE_TITLE` would flatten `McFly` to `Mcfly`. Like
  leetspeak it is worth zero bits, and the digits are never touched.
- **`numbers_allow_leading_zero`**, with `->allowLeadingZero()` and
  `--leading-zero`. False keeps the historical draw; true widens the segment
  from `9 * 10^(d-1)` values to `10^d` and zero-pads to a fixed width.
- **`strength.thresholds`** moves the five band edges, which is what
  `StrongPassword` accepts at signup. `Strength::fromBits()` takes them as an
  optional argument — an enum case has no business reading configuration — and
  bands that do not ascend throw `InvalidOptionException`.
- **`strength.rule_model`** picks which model `StrongPassword` scores with,
  `charset` or `structural`, per rule with `->using(...)`. `charset` stays the
  default: the value under validation is one the user chose, and you know
  nothing about how they chose it.
- **`unique_attempts_multiplier`** makes the `generateUnique()` attempt budget
  configurable. It stays `count * multiplier + 100`, and the base of 100 is what
  keeps a small batch practical.

### Changed

- **English is the reference locale.** `fallback_locale` defaults to `en`, so an
  application in a language with no packs of its own gets English rather than
  Italian. The package was written Italian-first; for one published
  internationally that was the wrong default.
- **Every dictionary declares the language its names are written in.** There is
  no single right answer for all 91: a dictionary of Italian wines is Italian in
  every locale, because Barolo is Barolo, while one about Harry Potter is
  English and Italian is the dub. The 14 dictionaries with a non-Italian subject
  moved to an English base with an Italian overlay — `Albus Dumbledore` is now
  canonical and `Albus Silente` is the translation, where it was the other way
  round. The 77 Italian ones are untouched, and correct untranslated.
- Name lookup now resolves `{locale}` → `{fallback_locale}` → the base, so a
  French reader gets the English rendering wherever one exists.
- `coffee_brands` is now `italian_coffee_brands`, and the two non-Italian
  entries in it (Nespresso, Starbucks) were dropped to match the name.
  `car_brands` stays as it is — Porsche, Tesla and Toyota are not Italian.
- Data files keep each entry on one line — `{ "name": "Amarone", "gender":
  "male" }` — so a dictionary stays scannable and a diff stays readable.
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
- `numbers_digits` defaults to **6** everywhere. The published config said 6
  while `Options` defaulted to 4, so an application that constructed `Options`
  itself — or published no config at all — silently got a weaker password than
  the file it was reading described.
- `Entropy::structuralBits()` takes two further arguments, for leading zeros and
  the adjective count, and reports a `second_adjective` component. Both are
  optional and default to the previous behaviour.
- Requires PHP 8.2 and supports Laravel 10 through 13.

### Security

- **Locales and dictionary keys can no longer escape their directory.** Both are
  interpolated into `{root}/{locale}/{key}.json`, so an application passing a
  request value into `->locale(...)` — or keying a dictionary by one — could
  read any JSON file the PHP process could see. Both are now validated against a
  strict pattern where they enter, and again immediately before the path is
  built. An application locale that fails validation is ignored in favour of the
  fallback; Laravel's own `setLocale()` guard permits `.` and `..`, so arriving
  through it was not sufficient.
- **Dictionary content is treated as untrusted.** The documented pattern is to
  register a dictionary from application data, so names are stripped to letters,
  digits and the configured separator before assembly, with whitespace runs
  collapsed first. Quotes, semicolons, angle brackets, pipes, backslashes,
  newlines, tabs and NUL bytes can no longer reach a generated password.
- **Leetspeak is credited zero entropy bits.** Earlier 2.0 development builds
  credited 6 and 12; a deterministic transform adds nothing against the
  attacker the structural model assumes, so those figures overstated every
  leetspeak password.
- **Batches are bounded.** `generateMany()` builds its result in memory and now
  caps at `PasswordToolkit::MAX_BATCH`, so a count arriving from a request
  cannot exhaust the process.

### Fixed

- **The numeric segment is no longer credited entropy it never had.** The draw
  runs from `10^(d-1)` to `10^d - 1`, so six digits are 900,000 values, not
  1,000,000 — but the structural model credited `d * log2(10)` as though the
  full decade were available. Every reported figure was about 0.15 bits
  optimistic. The segment is now worth `log2(9 * 10^(d-1))` when leading zeros
  are off and `log2(10^d)` when they are on. **Reported entropy will drop very
  slightly for everyone**; the bands in the README are unchanged at the
  rounding they are quoted to.
- **Crack time no longer overflows to `INF`.** `2 ** $bits` overflowed well
  inside the range a long password produces, which made
  `StrengthReport::toArray()` unserialisable for exactly the strongest
  passwords. It is computed in log space now.
- **Time strings are pluralised.** `humanizeSeconds(90)` returned
  `"1 minutes"`; it returns `"1 minute"`, in the active locale.
- Leetspeak is applied when `add_numbers` is false. 1.x skipped the transform
  entirely on that branch.
- 48 duplicate adjective entries removed across nine files.
- A malformed user dictionary now reports itself as invalid JSON rather than as
  "has no values", which sent people looking in the wrong place.
- An entry with no letters or digits is rejected where it is defined, rather
  than silently producing a password missing a segment.
- Multibyte handling is consistent — the repetition penalty counted bytes while
  the charset model counted characters.

### Removed

- `.php-cs-fixer.php` and `tlint.json`, neither of which any script ran. Pint is
  the formatter, pinned by `pint.json`.
- The `Database\Factories\` autoload entry, which pointed at a directory that
  never existed.

## 1.7.0 and earlier

See the [releases](../../releases).
