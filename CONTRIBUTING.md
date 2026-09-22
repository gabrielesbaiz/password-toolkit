# Contributing

Thanks for considering it. This is a small package and PRs are welcome.

## Setup

```bash
git clone git@github.com:gabrielesbaiz/password-toolkit.git
cd password-toolkit
composer install
```

## The three gates

There is **no CI**. These three commands are the contract — run all of them
before you open a PR, because nothing else will.

```bash
composer format    # pint, laravel preset plus strict types
composer analyse   # phpstan level 6 via larastan
composer test      # pest
```

`composer lint` runs the first two together.

## Ground rules

- **Every behaviour change needs a test that fails without it.** The suite is
  fast; there is no excuse.
- **Data changes are code changes.** `tests/DataIntegrityTest.php` enforces the
  schema, ASCII-only names, the three-word cap, single-word adjectives, and
  that every locale ships a `_default` pool. A dictionary that does not pass is
  not merged.
- **Names must be verifiable.** No invented people, films or places. If a
  reader cannot confirm the entry exists, it does not belong in the data.
- **Comments explain why, not what.** If a line is surprising, say what it
  prevents.

## Adding a dictionary

1. Drop a JSON file in `src/Data/Names/People/` or `src/Data/Names/Things/`:

   ```json
   {
       "key": "my_dictionary",
       "type": "things",
       "locale": "it",
       "values": [
           { "name": "Example", "gender": "neutral" }
       ]
   }
   ```

   `locale` is the language the **names** are in, and it decides whether they
   ever get translated. A dictionary about Italian wines is `it` and needs no
   translations at all — Barolo is Barolo in every language. A dictionary about
   something with an international name is `en`, and Italian becomes an overlay.
   Get this wrong and either the names are translated when they should not be,
   or an English reader is shown a dub name they will not recognise.

   Then add the dictionary to `build/metadata.php` — its group, tags, icon and
   reach — and run `php build/apply-metadata.php`. Add a label to
   `resources/lang/en/dictionaries.php` and `resources/lang/it/dictionaries.php`.
   Tests enforce all of this, so a dictionary cannot ship half-described.

   Be honest about `reach`. `global` means a reader anywhere is likely to know
   the names; `italian` means an Italian audience will; `niche` means it is
   specialist even in Italy. Over-claiming it puts unrecognisable words into
   someone's password.

   Keep each entry on one line. A dictionary is a list of short records, and one
   record per line is what makes a diff readable.

2. Optionally add themed adjectives at
   `src/Data/Adjectives/{locale}/my_dictionary.json`. Without one, the
   dictionary falls back to the locale's `_default` pool, which is a perfectly
   good outcome — only add a themed file when the theme actually earns it.
3. Run `composer test`. The shipped config enables everything by default, so
   there is no list to update.
4. Update the dictionary table in the README.

## Translating

**Adjectives** are generated, never hand-written. Edit
`src/Data/Adjectives/_glossary.it-en.json` and run:

```bash
php build/build-adjectives.php
```

A test regenerates the packs and compares them byte for byte, so editing a
generated file directly fails the build rather than being quietly reverted by
the next run. English targets must be a single ASCII word in Title Case.

**Names** are translated sparsely, in `src/Data/Names/{locale}/{key}.json`:

```json
{ "key": "roman_mythology", "locale": "en", "values": { "Giove": "Jupiter" } }
```

List only the entries that actually differ. Most names are proper nouns and
must not be translated at all — Barolo is Barolo in every language. Leaving an
entry out is the correct outcome, not a gap, and it is always better than a
guess: if you cannot establish a mapping from a source, do not add it. Every
key must name an entry that really exists in the base dictionary, and targets
obey the same ASCII, three-word, no-apostrophe rules as the base data. All of
that is enforced by `tests/NameTranslationTest.php`.

## Adding a locale

1. Create `src/Data/Adjectives/{locale}/_default.json`. That single file is
   enough for the locale to work everywhere. It must declare the language's word
   order:

   ```json
   {
       "key": "_default",
       "locale": "en",
       "adjective_position": "before",
       "values": [{ "name": "Legendary", "gender": "neutral" }]
   }
   ```

   `before` or `after` — whichever the language actually uses. A test enforces
   that every locale declares one, because a locale that does not silently
   inherits Italian word order.
2. Add `resources/lang/{locale}/strength.php`, copying the English file.
3. Themed adjective files per dictionary are optional. To generate a full set
   from the Italian packs, copy `build/build-adjectives.php` and point it at a
   glossary for your language.
4. Name translations are optional too, and should stay sparse — see
   **Translating** above.

## Pull requests

- Branch from `main`.
- Keep the three gates green.
- Describe the behaviour change, not the diff.
- Add an entry to `CHANGELOG.md`.
