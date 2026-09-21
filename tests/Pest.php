<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Path to a data directory inside the package.
 */
function packagePath(string $relative = ''): string
{
    return rtrim(dirname(__DIR__).'/'.ltrim($relative, '/'), '/');
}

/**
 * Restrict the enabled dictionaries for the current test.
 */
function onlyDictionaries(string ...$keys): void
{
    config()->set('password-toolkit.dictionaries.enabled', array_values($keys));

    Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit::flushCache();
}

/**
 * Run an artisan command and return everything it wrote to stdout.
 *
 * @param  array<string, mixed>  $arguments
 */
function artisanOutput(string $command, array $arguments = []): string
{
    Illuminate\Support\Facades\Artisan::call($command, $arguments);

    return Illuminate\Support\Facades\Artisan::output();
}

/**
 * Run a callback with E_USER_DEPRECATED swallowed.
 *
 * The legacy config shim raises one deliberately; a test that is not asserting
 * on it should not be failed by it.
 */
function withoutDeprecations(Closure $callback): mixed
{
    set_error_handler(static fn (): bool => true, E_USER_DEPRECATED);

    try {
        return $callback();
    } finally {
        restore_error_handler();
    }
}
