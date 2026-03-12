---
name: password-toolkit
description: Generate memorable, themed passwords using the gabrielesbaiz/password-toolkit Laravel package — covers the facade, configuration, name types, adjectives, leetspeak, and number placement.
---

# Password Toolkit

## When to use this skill
Use this skill when working with `gabrielesbaiz/password-toolkit` to generate readable, themed passwords in a Laravel application.

## Installation

```bash
composer require gabrielesbaiz/password-toolkit
php artisan vendor:publish --tag="password-toolkit-config"
```

## Basic Usage

```php
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;

$password = PasswordToolkit::generate();
// e.g. "Topolino-Fortunato-1234"
```

`generate()` returns a `?string` — it returns `null` only if all name types are disabled in config.

## Configuration

Config file: `config/password-toolkit.php`

```php
return [

    'name_types' => [
        'people' => [
            'back_to_the_future'                    => true,
            'cartoons'                              => true,
            'disney_characters'                     => true,
            'game_of_thrones'                       => true,
            'greek_mythology'                       => true,
            'harry_potter'                          => true,
            'italian_actors'                        => true,
            'italian_architects'                    => true,
            'italian_basketball_legends'            => true,
            'italian_chefs'                         => true,
            'italian_comedians'                     => true,
            'italian_cyclists'                      => true,
            'italian_explorers'                     => true,
            'italian_fashion_designers'             => true,
            'italian_film_directors'                => true,
            'italian_football_legends'              => true,
            'italian_musicians'                     => true,
            'italian_nobel_prize_winners'           => true,
            'italian_opera_composers'               => true,
            'italian_painters'                      => true,
            'italian_poets'                         => true,
            'italian_presidents_of_the_republic'    => true,
            'italian_racing_drivers'                => true,
            'italian_renaissance_artists'           => true,
            'italian_scientists'                    => true,
            'italian_superheroes'                   => true,
            'italian_television_personalities'      => true,
            'italian_writers'                       => true,
            'philosophers'                          => true,
            'pixar_characters'                      => true,
            'star_wars'                             => true,
        ],
        'things' => [
            'car_brands'            => true,
            'coffee_brands'         => true,
            'italian_cheeses'       => true,
            'italian_monuments'     => true,
            'italian_regional_foods'=> true,
            'italian_wines'         => true,
        ],
    ],

    'separator_symbol'      => '-',      // character between password parts
    'name_separator'        => true,     // replace spaces in names with separator_symbol
    'add_numbers'           => true,     // append random numbers
    'numbers_digits'        => 4,        // how many digits
    'numbers_position'      => 'end',    // 'start' | 'middle' | 'end'
    'leetspeak_conversion'  => 'no',     // 'no' | 'basic' | 'advanced'

];
```

### Disable specific name categories

```php
'name_types' => [
    'people' => [
        'cartoons'     => true,
        'star_wars'    => true,
        'harry_potter' => false, // excluded
    ],
    'things' => [
        'italian_wines' => true,
    ],
],
```

### Numbers placement

| `numbers_position` | Example output           |
|--------------------|--------------------------|
| `start`            | `1234-Topolino-Fortunato`|
| `middle`           | `Topolino-1234-Fortunato`|
| `end`              | `Topolino-Fortunato-1234`|

### Leetspeak conversion

| `leetspeak_conversion` | Description                          |
|------------------------|--------------------------------------|
| `no`                   | Plain text (default)                 |
| `basic`                | Common substitutions (a→4, e→3, …)   |
| `advanced`             | Extended substitutions               |

## Password Structure

Every generated password is composed of three parts joined by `separator_symbol`:

1. **Name** — randomly picked from enabled categories (gender-tagged: male / female / neutral)
2. **Adjective** — gender-matched to the selected name
3. **Number** (optional) — `numbers_digits` random digits, placed according to `numbers_position`

## Advanced: Accessing internals

```php
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;

// Get a random name entry (returns Illuminate\Support\Collection)
$nameData = PasswordToolkit::getRandomNameData();
// { name: "Topolino", gender: "male", file: "cartoons" }

// Get a gender-matched adjective for a name entry
$adjective = PasswordToolkit::getRandomAdjective($nameData);
// "Fortunato"
```

## Conventions

- Always use the `PasswordToolkit` facade rather than instantiating the class directly.
- Publish and commit the config file so project-specific name type selections are version-controlled.
- When disabling name types set the value to `false` (not remove the key) so the config remains explicit.
