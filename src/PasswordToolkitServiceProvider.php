<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit;

use Gabrielesbaiz\PasswordToolkit\Console\GeneratePasswordCommand;
use Gabrielesbaiz\PasswordToolkit\Console\MakeDictionaryCommand;
use Gabrielesbaiz\PasswordToolkit\Contracts\DictionaryRepository;
use Gabrielesbaiz\PasswordToolkit\Contracts\PasswordGenerator;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\AdjectiveResolver;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\FileDictionaryRepository;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\NameTranslator;
use Gabrielesbaiz\PasswordToolkit\Generator\LeetspeakTransformer;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PasswordToolkitServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('password-toolkit')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasCommands([
                GeneratePasswordCommand::class,
                MakeDictionaryCommand::class,
            ]);
    }

    /**
     * Register any package services.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton(DictionaryRepository::class, FileDictionaryRepository::class);

        $this->app->singleton(AdjectiveResolver::class, function (): AdjectiveResolver {
            $resolver = new AdjectiveResolver;

            // Adjectives are searched in the user's dictionary paths before the
            // built-in ones, so a personal collection can ship its own
            // {locale}/{key}.json alongside its names.
            /** @var array<int, string> $paths */
            $paths = (array) config('password-toolkit.dictionaries.paths', []);

            return $resolver->usingPaths(array_values(array_filter($paths, 'is_string')));
        });

        $this->app->singleton(NameTranslator::class, function (): NameTranslator {
            /** @var array<int, string> $paths */
            $paths = (array) config('password-toolkit.dictionaries.paths', []);

            return (new NameTranslator)->usingPaths(array_values(array_filter($paths, 'is_string')));
        });

        $this->app->singleton(LeetspeakTransformer::class);

        // Bound on the concrete class because that is what the facade accessor
        // resolves, and aliased to the contract for constructor injection.
        $this->app->singleton(PasswordToolkit::class);
        $this->app->alias(PasswordToolkit::class, PasswordGenerator::class);
        $this->app->alias(PasswordToolkit::class, 'password-toolkit');
    }
}
