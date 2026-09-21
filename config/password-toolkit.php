<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Locale
    |--------------------------------------------------------------------------
    |
    | Which language the adjectives are drawn from. Null follows the
    | application locale. Adjectives live in src/Data/Adjectives/{locale}/ and
    | every locale ships a _default pool, so a dictionary without a themed file
    | for the active locale still works.
    |
    | 'fallback_locale' is used when the active locale has no pool at all. It
    | defaults to Italian because that is where the built-in themed adjectives
    | are, and because the built-in names are largely Italian too.
    |
    */

    'locale' => null,

    'fallback_locale' => 'it',

    /*
    |--------------------------------------------------------------------------
    | Dictionaries
    |--------------------------------------------------------------------------
    |
    | 'enabled'  '*' for every built-in dictionary, or an array of keys.
    | 'except'   Keys to drop, applied after 'enabled'.
    | 'types'    Limit to 'people', 'things', or both.
    | 'paths'    Directories of your own dictionary JSON files.
    | 'custom'   Dictionaries defined inline, without a file.
    |
    | Run `php artisan password-toolkit:generate --list` to see every key that
    | is currently resolving, and `php artisan password-toolkit:make-dictionary`
    | to scaffold one of your own.
    |
    */

    'dictionaries' => [

        'enabled' => '*',

        'except' => [],

        'types' => ['people', 'things'],

        'paths' => [
            // resource_path('password-dictionaries'),
        ],

        'custom' => [
            // 'company_products' => [
            //     'type' => 'things',
            //     'values' => [
            //         ['name' => 'Orbit', 'gender' => 'neutral'],
            //         ['name' => 'Beacon', 'gender' => 'neutral'],
            //     ],
            // ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Assembly
    |--------------------------------------------------------------------------
    |
    | 'separator_symbol'  Placed between segments. Any string, or null for none.
    | 'name_separator'    Multi-word names: true turns the space into the
    |                     separator ("Luke-Skywalker"), false strips it
    |                     ("LukeSkywalker").
    |
    */

    'separator_symbol' => '-',

    'name_separator' => true,

    /*
    |--------------------------------------------------------------------------
    | Adjective position
    |--------------------------------------------------------------------------
    |
    | Null follows the locale, which is almost always what you want: word order
    | is a property of the language, not a preference. Italian puts the
    | adjective after the noun ("Goldrake-Mitico"), English puts it before
    | ("Legendary-Goldrake"), and each locale declares its own order in its
    | _default adjective pack.
    |
    | Set 'before' or 'after' to override that for every locale.
    |
    */

    'adjective_position' => null,

    /*
    |--------------------------------------------------------------------------
    | Numbers
    |--------------------------------------------------------------------------
    |
    | 'numbers_position' is one of 'start', 'middle' or 'end'. Digits are drawn
    | with random_int(), so the numeric segment is cryptographically random
    | even though the words are not the whole of the entropy.
    |
    */

    'add_numbers' => true,

    'numbers_digits' => 4,

    'numbers_position' => 'end',

    /*
    |--------------------------------------------------------------------------
    | Leetspeak
    |--------------------------------------------------------------------------
    |
    | 'none'      leave the password as assembled
    | 'basic'     single-character substitutions only, so length is preserved
    | 'advanced'  adds multi-character glyphs, which lengthens the password and
    |             helps against strict minimum-length policies
    |
    */

    'leetspeak_conversion' => 'none',

    /*
    |--------------------------------------------------------------------------
    | Strength reporting
    |--------------------------------------------------------------------------
    |
    | The attacker capability assumed when estimating crack time. 1e10 is about
    | right for a single offline GPU against a fast hash; raise it towards 1e12
    | if your threat model includes a well-funded adversary.
    |
    */

    'strength' => [

        'guesses_per_second' => 1e10,

    ],

];
