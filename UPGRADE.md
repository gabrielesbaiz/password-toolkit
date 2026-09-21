# Upgrading

## 1.x to 2.0

> [!CAUTION]
> This is a breaking release. Most of it is shimmed, but four changes need an
> edit in your code. Read the table before you deploy.

### Why 2.0 exists

1.x was a single static class that re-read its data directory on every call,
picked a dictionary file before it picked a name, and returned
`string|array|null` from one method. It was also Italian-only by construction:
adjectives lived in one flat directory with gender agreement baked in, so
adding English meant writing 91 new files.

2.0 fixes all of that. The generator is an injectable object, dictionaries are
cached and flattened, adjectives resolve through a locale chain, and you can
add dictionaries of your own without forking the package.

### What changed

| Change | Shimmed? |
|---|---|
| `generate($count)` → `generateMany($count)` | no |
| `generate()` throws instead of returning `null` | no |
| `poolSizes()` returns `['names' => …, 'adjectives' => …]`, not a list | no |
| Exceptions no longer extend `\InvalidArgumentException` | no |
| Config `name_types` → `dictionaries.enabled` / `.except` | **yes**, with a deprecation |
| `leetspeak_conversion: 'no'` → `'none'` | **yes**, `'no'` still parses |
| `clearPoolCache()` → `flushCache()` | **yes**, deprecated alias kept |
| `Entropy::LABELS` → `Enums\Strength` | **yes**, the constant still exists |
| Static calls on the class → an instance behind the facade | **yes** via the facade |
| Adjectives move to `Data/Adjectives/{locale}/{key}.json` | no, if you forked the data |
| `humanizeSeconds(90)` returns `"1 minute"`, not `"1 minutes"` | no, it was a bug |
| Reported entropy drops — file-first picking overstated it | no, the old figure was wrong |
| Requires PHP 8.2 (was 8.0) | no |

`strength()`, `structuralReport()`, `generateWithReport()`,
`generateManyWithReport()` and the `StrengthReport` properties
(`entropyBits`, `length`, `label`, `score`, `components`, `charsetFlags`,
`crackTimeSeconds`, `crackTimeHuman`) are unchanged.

### 1. Batch generation

```diff
- $passwords = PasswordToolkit::generate(10);
+ $passwords = PasswordToolkit::generateMany(10);
```

`generate()` now takes no count and always returns one `string`.
`generateMany()` always returns exactly the number you asked for; 1.x silently
returned fewer when a dictionary failed to load.

### 2. Null handling

```diff
- $password = PasswordToolkit::generate();
-
- if ($password === null) {
-     // no dictionaries enabled
- }
+ use Gabrielesbaiz\PasswordToolkit\Exceptions\NoDictionariesEnabledException;
+
+ try {
+     $password = PasswordToolkit::generate();
+ } catch (NoDictionariesEnabledException $e) {
+     // no dictionaries enabled
+ }
```

An empty selection is a configuration mistake, not a value. Making it a `null`
return pushed the problem into every call site.

### 3. Pool sizes

```diff
- [$names, $adjectives] = PasswordToolkit::poolSizes();
+ ['names' => $names, 'adjectives' => $adjectives] = PasswordToolkit::poolSizes();
```

### 4. Caught exceptions

```diff
- } catch (\InvalidArgumentException $e) {
+ } catch (\Gabrielesbaiz\PasswordToolkit\Exceptions\PasswordToolkitException $e) {
```

Every exception the package throws now extends `PasswordToolkitException`.
Catch that one type, or a specific subclass when you mean to handle one cause
differently.

### 5. Configuration

The shim keeps a 1.x config working, so nothing breaks on deploy. Migrate it
anyway — the new file is around a hundred lines shorter and does not have to be
kept in sync with the filesystem by hand.

The simplest path is to re-publish rather than hand-edit:

```bash
php artisan vendor:publish --tag="password-toolkit-config" --force
```

Then reapply your choices:

```diff
- 'name_types' => [
-     'people' => ['star_wars' => true, 'cartoons' => false, /* 47 more */],
-     'things' => ['italian_wines' => true, /* 41 more */],
- ],
+ 'dictionaries' => [
+     'enabled' => '*',
+     'except' => ['cartoons'],
+     'types' => ['people', 'things'],
+ ],

- 'leetspeak_conversion' => 'no',
+ 'leetspeak_conversion' => 'none',
```

Two keys are new and have no 1.x equivalent:

```php
'locale' => null,            // null follows the application locale
'fallback_locale' => 'it',   // where adjectives come from when the locale has none
```

Leave `fallback_locale` at `it` unless you have your own adjective packs. The
built-in themed adjectives are Italian, and an English request falls through to
`en/_default.json`, which ships with the package.

### 6. If you forked the data directory

Adjective files moved and changed shape:

```diff
- src/Data/Adjectives/star_wars_adjectives.json      [ {"name": …, "gender": …}, … ]
+ src/Data/Adjectives/it/star_wars.json              {"key": …, "locale": …, "values": [ … ]}
```

Name files are unchanged. The loader now takes the dictionary key from the
filename, so the `name` field inside a name file is documentation only.

Rather than re-forking, consider pointing
`password-toolkit.dictionaries.paths` at a directory of your own — it takes both
name files and a `{locale}/` subdirectory of adjectives, and it survives
upgrades.

### 7. Entropy figures will change

1.x picked a dictionary file uniformly and then an entry within it, but
computed entropy as though every name across every enabled dictionary were
equally likely. The two did not agree, and the reported figure was the
optimistic one.

2.0 picks uniformly across entries, so the model and the behaviour now match.
If you assert on exact bit counts anywhere, expect them to move.
