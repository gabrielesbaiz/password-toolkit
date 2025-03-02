<?php

namespace Gabrielesbaiz\PasswordToolkit;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PasswordToolkitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('password-toolkit')
            ->hasConfigFile();
    }
}
