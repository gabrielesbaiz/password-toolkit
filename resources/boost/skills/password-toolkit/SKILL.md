---
name: password-toolkit
description: Generate memorable, themed passwords with gabrielesbaiz/password-toolkit — the facade, the fluent builder, locales, custom dictionaries, strength reporting, the validation rule and the artisan commands.
---

# Password Toolkit

## When to use this skill

Use it when working with `gabrielesbaiz/password-toolkit` (2.x) to generate
human-readable passwords in a Laravel application.

Do **not** reach for this package for machine secrets — API keys, tokens, root
credentials. Use `Str::password()` or `random_bytes()` there. This package is
for passwords a person has to read aloud, type from a printout, or remember.

## Installation

```bash
composer require gabrielesbaiz/password-toolkit
php artisan vendor:publish --tag="password-toolkit-config"
```

Requires PHP 8.2+, Laravel 10–13.

## Core API

```php
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;

PasswordToolkit::generate();                  // string — throws if nothing is enabled
PasswordToolkit::generateMany(10);            // array<int, string>, exactly 10
PasswordToolkit::generateUnique(10);          // the same, with no repeats
PasswordToolkit::generateWithReport();         // ['password' => string, 'report' => StrengthReport]
PasswordToolkit::generateManyWithReport(5);    // array of the above

PasswordToolkit::strength($userChosen);        // charset model
PasswordToolkit::structuralReport($generated); // structural model — the honest one

PasswordToolkit::dictionaries();               // Collection of key/type/locale/count
PasswordToolkit::poolSizes();                  // ['names' => int, 'adjectives' => int]
PasswordToolkit::registerDictionary($key, $values, $type);
PasswordToolkit::flushCache();                 // after changing config at runtime
```

`generate()` takes **no count argument** and returns a plain `string`. It throws
`NoDictionariesEnabledException` rather than returning `null`.

Inject `Gabrielesbaiz\PasswordToolkit\Contracts\PasswordGenerator` instead of
using the facade where that reads better. The service is a container singleton,
so `PasswordToolkit::shouldReceive(...)` and `swap()` work in tests.

## The fluent builder

Per-call overrides, without touching config. Each method returns a new builder.

```php
use Gabrielesbaiz\PasswordToolkit\Enums\Leetspeak;
use Gabrielesbaiz\PasswordToolkit\Enums\NumbersPosition;

PasswordToolkit::make()
    ->locale('en')
    ->only(['star_wars'])                   // ->except([…]), ->types('people')
    ->paths([storage_path('dictionaries')])
    ->separator('_')                        // ->separator(null) for none
    ->keepWordBreaks(false)                 // "LukeSkywalker" not "Luke_Skywalker"
    ->digits(6)                             // ->withoutNumbers()
    ->numbersAt(NumbersPosition::Middle)    // or 'middle'
    ->adjectiveAt('before')                 // override the locale's word order
    ->leet(Leetspeak::Basic)                // or 'basic'
    ->guessesPerSecond(1e12)
    ->generate();                           // ->many(10) ->unique(10) ->withReport() ->manyWithReport(10)
```

## Configuration

`config/password-toolkit.php`:

```php
'locale'          => null,   // null follows app()->getLocale()
'fallback_locale' => 'en',

'dictionaries' => [
    'enabled' => '*',                    // '*' or ['star_wars', …]
    'except'  => [],
    'types'   => ['people', 'things'],
    'paths'   => [],                     // directories of your own JSON
    'custom'  => [],                     // inline definitions
],

'separator_symbol'     => '-',
'name_separator'       => true,
'add_numbers'          => true,
'numbers_digits'       => 4,
'numbers_position'     => 'end',         // start | middle | end
'adjective_position'   => null,          // null follows the locale; 'before' | 'after'
'leetspeak_conversion' => 'none',        // none | basic | advanced
'strength' => ['guesses_per_second' => 1e10],
```

A 1.x config using `name_types` still works, with a deprecation. The keys are
translated to `dictionaries.enabled`.

## Custom dictionaries

Three routes. All three produce the same thing.

```php
// 1. a directory of JSON files
'dictionaries' => ['paths' => [resource_path('password-dictionaries')]],

// 2. inline
'dictionaries' => ['custom' => [
    'company_products' => ['type' => 'things', 'values' => ['Orbit', 'Beacon']],
]],

// 3. at runtime, from a service provider
PasswordToolkit::registerDictionary('team', User::pluck('nickname')->all(), 'people');
```

File shape:

```json
{
    "key": "my_team",
    "type": "people",
    "values": [{ "name": "Ada Lovelace", "gender": "female" }]
}
```

A bare list of strings works too; `gender` defaults to `neutral`. Adjectives are
optional — a dictionary without its own falls back to the locale default pool.

## Locales

Adjectives resolve in this order, first hit wins:

1. `{locale}/{dictionary}.json`
2. `{locale}/_default.json`
3. `{fallback_locale}/{dictionary}.json`
4. `{fallback_locale}/_default.json`

Every dictionary has a themed adjective pack in both languages, capped at twenty
words. Missing packs fall back to `_default`, 193 neutral adjectives that suit a
person, place or thing equally. English packs are generated from
`src/Data/Adjectives/_glossary.it-en.json` by `build/build-adjectives.php` —
edit the glossary, never a generated file.

English is the reference locale: `fallback_locale` is `en`, so an app in a
language with no packs gets English, not Italian.

Every dictionary declares the language its **names** are in, via `locale` in the
base file. 77 are `it` — Italian wines, pasta, cyclists, volcanoes — and are
correct untranslated in every locale, because Barolo is Barolo. 14 are `en`,
with Italian as an overlay: the base says `Albus Dumbledore`, and
`Data/Names/it/harry_potter.json` maps it to `Albus Silente`.

Names resolve `{locale}` → `{fallback_locale}` → base. Files live at
`Data/Names/{locale}/{key}.json` as a plain map, or
`{userpath}/names/{locale}/{key}.json` for a user dictionary. When adding a
dictionary, setting `locale` correctly is the decision that matters.

**Word order follows the language.** Each locale's `_default.json` declares
`"adjective_position": "before" | "after"` — Italian says `Goldrake-Mitico`,
English says `Legendary-Goldrake`. It is read from `_default.json` only, never
from a themed pack. Override with the `adjective_position` config key or
`->adjectiveAt(...)` on the builder.

Adding a language means adding one `_default.json` with its
`adjective_position`.

## Strength reporting

```php
$report->score;           // 0..4
$report->label;           // very_weak | weak | fair | strong | very_strong
$report->displayLabel();  // translated
$report->strength;        // Strength enum, ->color() for a meter
$report->entropyBits;
$report->crackTimeHuman;  // "12 years", translated
$report->toArray();       // Arrayable / Jsonable / JsonSerializable
```

Use `strength()` for a password the user chose; use `structuralReport()` (what
`generateWithReport()` returns) for one this package generated — it models an
attacker who knows the package and searches the pool, not the alphabet, and it
is a much lower and more honest number.

## Validation

```php
use Gabrielesbaiz\PasswordToolkit\Rules\StrongPassword;

'password' => ['required', new StrongPassword],       // floor: strong
'pin'      => ['required', StrongPassword::fair()],
'master'   => ['required', StrongPassword::veryStrong()],
```

## Commands

```bash
php artisan password-toolkit:generate 5 --report
php artisan password-toolkit:generate 3 --locale=en --only=star_wars --json
php artisan password-toolkit:generate --list
php artisan password-toolkit:make-dictionary my_team --type=people --locale=en
```

## Dictionary metadata

Every dictionary carries `group` (closed set: food, drink, nature, places,
culture, arts, screen, sport, science, history, myth, vehicles), free-form
`tags`, an `icon`, and a `reach` of `global` | `italian` | `niche`.

```php
PasswordToolkit::make()->groups(['food', 'drink'])->generate();
PasswordToolkit::make()->tagged(['italian', 'sweet'])->generate();  // ALL tags
PasswordToolkit::make()->reach('global')->generate();               // international audience
```

`->only()` beats every filter: naming a dictionary means you want it.

For a UI: `dictionaries()` gives key, translated label/description, icon, group,
group_label, tags, reach, reach_label, type, locale, count, built_in.
`dictionariesWithSamples()` adds a live `sample` password per dictionary.
`groups()` and `tags()` return the vocabulary in use with counts.

Prefer `->reach('global')` for a non-Italian audience — it is the difference
between a password a user can repeat and one they cannot.

## Safety rules to respect

- **Dictionary keys** must match `[A-Za-z0-9_][A-Za-z0-9_-]*`; **locales** must
  look like `en`, `it`, `pt_BR`. Both are interpolated into a filesystem path,
  so anything else throws `InvalidOptionException`. Never pass a raw request
  value into `->locale()` or `registerDictionary()` without expecting that.
- **Do not sanitise names yourself.** Registered names are stripped to letters,
  digits and the separator automatically, so `User::pluck('nickname')` is safe
  to hand over as-is.
- **Leetspeak is worth zero entropy bits** and the report says so. Use it for a
  character-class or length policy, never to claim strength.
- **`generateMany()` caps at `PasswordToolkit::MAX_BATCH`.** Chunk beyond that.

## Exceptions

All extend `Gabrielesbaiz\PasswordToolkit\Exceptions\PasswordToolkitException`:

- `NoDictionariesEnabledException` — the selection resolved to nothing
- `DictionaryNotFoundException` — unknown key, or no adjectives in either locale
- `InvalidOptionException` — a config or builder value that makes no sense
