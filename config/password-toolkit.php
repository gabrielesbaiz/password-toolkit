<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | This value determines the language the adjectives are drawn from. When it
    | is null, the package will simply follow your application's locale, which
    | is typically what you want. Each locale ships a default pool of
    | adjectives, so every dictionary will work in every locale you add.
    |
    | Supported out of the box: "en", "it"
    |
    */

    'locale' => null,

    /*
    |--------------------------------------------------------------------------
    | Fallback Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used when the active one has no resources of its own.
    | English is the default here since it is the language most likely to be
    | understood by someone who did not get the locale they asked for.
    |
    | Names are handled a little differently: each dictionary declares the
    | language its own names are written in. A dictionary of Italian wines is
    | Italian in every locale, because "Barolo" is simply its name.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Dictionaries
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the built-in dictionaries are used when
    | building a password, and register any of your own. The "enabled" option
    | accepts "*" for everything, or an array of the keys you want, while
    | "except" is applied afterwards to drop the ones you do not.
    |
    | You are free to mix these filters however you like. To see exactly what
    | survives them, you may run:
    |
    |     php artisan password-toolkit:generate --list
    |
    */

    'dictionaries' => [

        /*
         * Which dictionaries take part. Supported: "*", or an array of keys.
         */
        'enabled' => '*',

        /*
         * Keys to remove. This is applied after "enabled", so it always wins.
         */
        'except' => [],

        /*
         * Limit the pool to one kind of name.
         *
         * Supported: "people", "things"
         */
        'types' => ['people', 'things'],

        /*
         * Thematic filters. Leaving these empty applies no restriction at all.
         *
         * "groups" is a fixed vocabulary, while "tags" is free-form and a
         * dictionary must carry every tag you list. "reach" describes how
         * widely the names are recognised, and is worth setting for an
         * international audience: a password is only memorable if the person
         * reading it actually knows the word.
         *
         * Supported groups: "food", "drink", "nature", "places", "culture",
         *                   "arts", "screen", "sport", "science", "history",
         *                   "myth", "vehicles"
         *
         * Supported reach: "global", "italian", "niche"
         */
        'groups' => [],

        'tags' => [],

        'reach' => null,

        /*
         * Directories containing dictionaries of your own. Every JSON file in
         * them is loaded alongside the built-in ones. You may scaffold a file
         * in the correct shape with:
         *
         *     php artisan password-toolkit:make-dictionary my_team
         */
        'paths' => [
            // resource_path('password-dictionaries'),
        ],

        /*
         * Dictionaries defined right here, when a file would be overkill. A
         * bare list of strings is fine and "gender" will default to neutral.
         */
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
    | Separator
    |--------------------------------------------------------------------------
    |
    | This string is placed between the segments of the password. You may use
    | any string here, or null for none at all. The "name_separator" option
    | decides what happens inside a name that contains a space: enabling it
    | gives you "Luke-Skywalker", while disabling it gives "LukeSkywalker".
    |
    */

    'separator_symbol' => '-',

    'name_separator' => true,

    /*
    |--------------------------------------------------------------------------
    | Adjective Position
    |--------------------------------------------------------------------------
    |
    | Word order is a property of the language rather than a preference, so
    | leaving this null is almost always correct. Italian places the adjective
    | after the noun ("Goldrake-Mitico") and English places it before
    | ("Legendary-Goldrake"), and each locale declares its own order.
    |
    | Supported: null, "before", "after"
    |
    */

    'adjective_position' => null,

    /*
    |--------------------------------------------------------------------------
    | Numbers
    |--------------------------------------------------------------------------
    |
    | The numeric segment is drawn with random_int() and is where most of the
    | entropy in a generated password lives. The word pools are fixed by the
    | data that ships, so "numbers_digits" is the setting that actually scales:
    | every digit adds roughly 3.32 bits.
    |
    | Six digits is a sensible floor. Feel free to raise it towards twelve when
    | the passwords guard something that matters, keeping in mind that someone
    | has to read them out loud. Any value from 1 to 18 is accepted.
    |
    | Supported positions: "start", "middle", "end"
    |
    */

    'add_numbers' => true,

    'numbers_digits' => 6,

    'numbers_position' => 'end',

    /*
     * Whether the numeric segment may begin with a zero.
     *
     * When disabled, the draw runs from 10^(d-1) upwards, so "042193" never
     * appears and six digits are 900,000 values rather than 1,000,000. The
     * strength report accounts for the difference. Enabling this buys those
     * bits back, at the cost of a password whose leading zero has to be
     * dictated aloud.
     */
    'numbers_allow_leading_zero' => false,

    /*
    |--------------------------------------------------------------------------
    | Words
    |--------------------------------------------------------------------------
    |
    | A password is built from two words by default: one adjective and one
    | name. Asking for three adds a second adjective, drawn from the same
    | agreeing pool without replacement, and still ordered the way the language
    | writes it — "Brave-Mighty-Goldrake" or "Goldrake-Mitico-Potente".
    |
    | The extra word is worth seven or eight bits, which is about two digits'
    | worth for eight more characters to type. Reach for "numbers_digits"
    | first, and for a third word when the words themselves are the part that
    | has to be memorable.
    |
    | Supported word counts: 2, 3
    |
    */

    'word_count' => 2,

    /*
     * How the words are cased.
     *
     * "title" leaves names spelled as the dictionary wrote them, so "McFly"
     * stays "McFly". Casing is a deterministic transform, so like leetspeak it
     * costs nothing and is worth nothing in the entropy figure.
     *
     * Supported: "title", "lower", "upper", "preserve"
     */
    'case' => 'title',

    /*
    |--------------------------------------------------------------------------
    | Leetspeak
    |--------------------------------------------------------------------------
    |
    | Here you may substitute lookalike characters into the finished password.
    | "basic" only swaps single characters, so the length is preserved, while
    | "advanced" adds multi-character glyphs and makes the password longer,
    | which is useful against a strict minimum-length policy.
    |
    | This is worth exactly zero bits and the strength report will say so. It
    | is here to satisfy a character-class rule, never to add strength.
    |
    | Supported: "none", "basic", "advanced"
    |
    */

    'leetspeak_conversion' => 'none',

    /*
    |--------------------------------------------------------------------------
    | Unique Batches
    |--------------------------------------------------------------------------
    |
    | When generateUnique() is asked for a batch, it will draw until it has
    | them all, giving up after count * multiplier + 100 attempts rather than
    | spinning against a pool too small to supply them.
    |
    | Raising this improves the odds of filling a large batch from a narrow
    | pool. Widening the pool with more dictionaries or more digits is usually
    | the better fix, and the exception that is thrown will say as much.
    |
    */

    'unique_attempts_multiplier' => 10,

    /*
    |--------------------------------------------------------------------------
    | Strength Reporting
    |--------------------------------------------------------------------------
    |
    | These options configure the strength report and the StrongPassword
    | validation rule. The "guesses_per_second" value is the attacker you
    | assume when estimating how long a password would take to crack: 1e10 is
    | about right for a single offline GPU against a fast hash, and you may
    | raise it towards 1e12 for a better funded adversary.
    |
    | The "thresholds" are the band edges, in bits, and they must ascend. They
    | decide what the validation rule accepts at sign-up, so treat them as a
    | policy decision rather than a constant.
    |
    | Finally, "rule_model" chooses how that rule scores. The charset model is
    | the right one for a password your user chose, since you know nothing
    | about how they chose it. The structural model reports a much lower figure
    | and should only be used where the rule guards passwords this package
    | generated itself.
    |
    | Supported models: "charset", "structural"
    |
    */

    'strength' => [

        'guesses_per_second' => 1e10,

        'thresholds' => [
            'weak' => 28,
            'fair' => 36,
            'strong' => 60,
            'very_strong' => 128,
        ],

        'rule_model' => 'charset',

    ],

];
