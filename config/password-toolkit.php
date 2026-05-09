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
            'hayao_miyazaki' => true,
            'italian_actors' => true,
            'italian_architects' => true,
            'italian_basketball_legends' => true,
            'italian_chefs' => true,
            'italian_comedians' => true,
            'italian_cyclists' => true,
            'italian_dj_producers' => true,
            'italian_explorers' => true,
            'italian_inventors' => true,
            'italian_journalists' => true,
            'italian_mathematicians' => true,
            'italian_motogp_legends' => true,
            'italian_olympic_legends' => true,
            'italian_rappers' => true,
            'italian_singers_classic' => true,
            'italian_singers_modern' => true,
            'italian_tennis_players' => true,
            'italian_volleyball_legends' => true,
            'italian_voice_actors' => true,
            'disney_villains' => true,
            'lupin_iii_characters' => true,
            'roman_emperors' => true,
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
            'italian_youtubers' => true,
            'philosophers' => true,
            'pixar_characters' => true,
            'roman_mythology' => true,
            'star_wars' => true,
        ],
        'things' => [
            'car_brands' => true,
            'italian_aperitivi' => true,
            'italian_breads' => true,
            'italian_card_games' => true,
            'italian_carnival_masks' => true,
            'italian_cars' => true,
            'italian_castles' => true,
            'italian_cured_meats' => true,
            'italian_dance_styles' => true,
            'italian_design_objects' => true,
            'italian_folk_instruments' => true,
            'italian_islands' => true,
            'italian_lakes' => true,
            'italian_liqueurs' => true,
            'italian_mountains' => true,
            'italian_old_currencies' => true,
            'italian_rivers' => true,
            'italian_sea_creatures' => true,
            'italian_train_stations_classic' => true,
            'italian_volcanoes' => true,
            'italian_wine_regions' => true,
            'italian_cheeses' => true,
            'italian_children_games_70s' => true,
            'italian_children_games_80s' => true,
            'italian_children_games_90s' => true,
            'italian_children_games_2000s' => true,
            'italian_circus_terms' => true,
            'italian_cryptids_legends' => true,
            'italian_desserts' => true,
            'italian_dialect_words' => true,
            'italian_icecream_flavors' => true,
            'italian_invented_words' => true,
            'italian_monuments' => true,
            'italian_motorcycles' => true,
            'italian_old_jobs' => true,
            'italian_pasta_shapes' => true,
            'italian_pizza_types' => true,
            'italian_progressive_rock_bands' => true,
            'italian_regional_foods' => true,
            'italian_street_foods' => true,
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

    /*
     * Strength reporter settings.
     *
     * 'guesses_per_second' is the assumed attacker capability used to
     * estimate crack time. 1e10 (~10 billion guesses/sec) is a reasonable
     * default for an offline GPU rig against fast hashes.
     */
    'strength' => [
        'guesses_per_second' => 1e10,
    ],
];
