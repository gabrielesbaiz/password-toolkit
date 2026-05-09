<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('strict types & no var_dump/print_r/echo in src')
    ->expect(['var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('facades extend Illuminate facade')
    ->expect('Gabrielesbaiz\\PasswordToolkit\\Facades')
    ->toExtend(\Illuminate\Support\Facades\Facade::class);

arch('service provider extends spatie base')
    ->expect(\Gabrielesbaiz\PasswordToolkit\PasswordToolkitServiceProvider::class)
    ->toExtend(\Spatie\LaravelPackageTools\PackageServiceProvider::class);

arch('main class is in root namespace')
    ->expect('Gabrielesbaiz\\PasswordToolkit\\PasswordToolkit')
    ->toBeClass();
