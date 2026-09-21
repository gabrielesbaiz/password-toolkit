<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Tests;

use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\PasswordToolkitServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Dictionaries and adjective pools are process-cached, so a test that
        // changes configuration has to start from a clean slate.
        PasswordToolkit::flushCache();
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('app.locale', 'it');
    }

    protected function getPackageProviders($app): array
    {
        return [
            PasswordToolkitServiceProvider::class,
        ];
    }

    /**
     * Restrict generation to a single dictionary.
     */
    protected function onlyDictionary(string ...$keys): void
    {
        config()->set('password-toolkit.dictionaries.enabled', array_values($keys));

        PasswordToolkit::flushCache();
    }
}
