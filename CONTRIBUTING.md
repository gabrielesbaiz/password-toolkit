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
       "values": [
           { "name": "Example", "gender": "neutral" }
       ]
   }
   ```

2. Optionally add themed adjectives at
   `src/Data/Adjectives/{locale}/my_dictionary.json`. Without one, the
   dictionary falls back to the locale's `_default` pool, which is a perfectly
   good outcome — only add a themed file when the theme actually earns it.
3. Run `composer test`. The shipped config enables everything by default, so
   there is no list to update.
4. Update the dictionary table in the README.

## Adding a locale

1. Create `src/Data/Adjectives/{locale}/_default.json`. That single file is
   enough for the locale to work everywhere.
2. Add `resources/lang/{locale}/strength.php`, copying the English file.
3. Themed files per dictionary are optional and can land later.

## Pull requests

- Branch from `main`.
- Keep the three gates green.
- Describe the behaviour change, not the diff.
- Add an entry to `CHANGELOG.md`.
