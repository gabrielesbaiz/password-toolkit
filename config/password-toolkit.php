<?php

return [
    /*
     * Configuration for name types for people and things to be used
     */
    'name_types' => [
        'people' => [
            'back_to_the_future' => true,
            'cartoons' => true,
            'disney_characters' => true,
            'game_of_thrones' => true,
            'greek_mythology' => true,
            'harry_potter' => true,
            'italian_actors' => true,
            'italian_architects' => true,
            'italian_basketball_legends' => true,
            'italian_chefs' => true,
            'italian_comedians' => true,
            'italian_cyclists' => true,
            'italian_explorers' => true,
            'italian_fashion_designers' => true,
            'italian_film_directors' => true,
            'italian_football_legends' => true,
            'italian_musicians' => true,
            'italian_nobel_prize_winners' => true,
            'italian_opera_composers' => true,
            'italian_painters' => true,
            'italian_poets' => true,
            'italian_presidents_of_the_republic' => true,
            'italian_racing_drivers' => true,
            'italian_renaissance_artists' => true,
            'italian_scientists' => true,
            'italian_superheroes' => true,
            'italian_television_personalities' => true,
            'italian_writers' => true,
            'philosophers' => true,
            'pixar_characters' => true,
            'star_wars' => true,
        ],
        'things' => [
            'car_brands' => true,
            'italian_cheeses' => true,
            'italian_monuments' => true,
            'italian_regional_foods' => true,
            'italian_wines' => true,
            'coffee_brands' => true,
        ],
    ],

    /*
     * Defines the symbol used to separate words in the generated password.
     *
     * Can be any symbol or null if no separator is desired.
     */
    'separator_symbol' => '-',

    /*
     * Defines whether to replace spaces with the separator
     * symbol in the name part of the password.
     *
     * Options:
     * 'true' will replace spaces with the separator symbol
     * 'false' will remove spaces.
     */
    'name_separator' => true,

    /*
     * Determines if numbers should be added to the generated password.
     *
     * Options:
     * 'true' will add numbers
     * 'false' will not
     */
    'add_numbers' => true,

    /*
     * Specifies the number of digits for the random number
     * that will be added to the password (if enabled).
     */
    'numbers_digits' => 4,

    /*
     * Defines where to place the numbers in the password.
     *
     * Options:
     * 'start' - numbers are placed at the beginning of the password,
     * 'middle' - numbers are placed in the middle of the password,
     * 'end' - numbers are placed at the end of the password.
     */
    'numbers_position' => 'end',

    /*
     * Defines the level of leetspeak conversion to apply
     * to the generated password.
     *
     * Options:
     * 'no' - no leetspeak conversion,
     * 'basic' - apply basic leetspeak conversion,
     * 'advanced' - apply advanced leetspeak conversion.
     */
    'leetspeak_conversion' => 'no',
];
