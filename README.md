# PasswordToolkit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gabrielesbaiz/password-toolkit.svg?style=flat-square)](https://packagist.org/packages/gabrielesbaiz/password-toolkit)

A lightweight helper package to generate nice passwords.

## Features

- ✅ Password generator
- ✅ Name + adjective password style
- ✅ Higly customizable
- ✅ Works seamlessly with Laravel facades

## Installation

You can install the package via composer:

```bash
composer require gabrielesbaiz/password-toolkit
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="password-toolkit-config"
```

This is the contents of the published config file:

```php
return [
    /*
     * Configuration for name types for people and things to be used
     */
    'name_types' => [
        'people' => [
            'back_to_the_future' => true,
            'cartoons' => true,
            'game_of_thrones' => true,
            'harry_potter' => true,
            'italian_actors' => true,
            'italian_comedians' => true,
            'italian_nobel_prize_winners' => true,
            'italian_presidents_of_the_republic' => true,
            'italian_television_personalities' => true,
            'italian_writers' => true,
            'philosophers' => true,
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
```

## Usage

```php
$passwordToolkit = new Gabrielesbaiz\PasswordToolkit();

echo $passwordToolkit->generate();
```

Using facade:

```php
use PasswordToolkit;

PasswordToolkit::generate();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Gabriele Sbaiz](https://github.com/gabrielesbaiz)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
