<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Exceptions\PasswordToolkitException;

arch('no debugging helpers ship')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('everything declares strict types')
    ->expect('Gabrielesbaiz\PasswordToolkit')
    ->toUseStrictTypes();

arch('enums are string backed')
    ->expect('Gabrielesbaiz\PasswordToolkit\Enums')
    ->toBeStringBackedEnums();

arch('value objects are readonly')
    ->expect([
        'Gabrielesbaiz\PasswordToolkit\Dictionaries\Dictionary',
        'Gabrielesbaiz\PasswordToolkit\Dictionaries\Entry',
        'Gabrielesbaiz\PasswordToolkit\Generator\Options',
        'Gabrielesbaiz\PasswordToolkit\Generator\PasswordBuilder',
        'Gabrielesbaiz\PasswordToolkit\Support\StrengthReport',
    ])
    ->toBeReadonly();

arch('contracts are interfaces')
    ->expect('Gabrielesbaiz\PasswordToolkit\Contracts')
    ->toBeInterfaces();

arch('exceptions share one base')
    ->expect('Gabrielesbaiz\PasswordToolkit\Exceptions')
    ->toExtend(PasswordToolkitException::class);

arch('commands live in Console')
    ->expect('Gabrielesbaiz\PasswordToolkit\Console')
    ->toExtend(Illuminate\Console\Command::class)
    ->toHaveSuffix('Command');

arch('rules implement the validation contract')
    ->expect('Gabrielesbaiz\PasswordToolkit\Rules')
    ->toImplement(Illuminate\Contracts\Validation\ValidationRule::class);

arch('the facade extends the Laravel base')
    ->expect('Gabrielesbaiz\PasswordToolkit\Facades')
    ->toExtend(Illuminate\Support\Facades\Facade::class);

arch('the service provider extends the spatie base')
    ->expect(Gabrielesbaiz\PasswordToolkit\PasswordToolkitServiceProvider::class)
    ->toExtend(Spatie\LaravelPackageTools\PackageServiceProvider::class);

arch('the data layer does not reach for the container')
    ->expect(['Gabrielesbaiz\PasswordToolkit\Dictionaries', 'Gabrielesbaiz\PasswordToolkit\Enums'])
    ->not->toUse(['config', 'Illuminate\Support\Facades\File']);
