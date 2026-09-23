<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Support;

use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * Validates the two values that reach the filesystem: locales and keys.
 *
 * Both are interpolated into a path — `{root}/{locale}/{key}.json` — so both
 * are a path traversal waiting to happen. An application that does
 * `->locale($request->segment(1))`, or registers a dictionary keyed by a value
 * it got from a user, would otherwise be able to read any JSON file the PHP
 * process can see. Laravel guards `app()->setLocale()` for the same reason;
 * this package cannot rely on having been reached through it.
 *
 * Validation happens where the value enters the package, not where it is used,
 * so a bad value fails loudly at the call site that introduced it.
 */
final class Identifier
{
    /**
     * Locale codes: `en`, `it`, `pt_BR`, `zh-Hant`.
     */
    public const LOCALE_PATTERN = '/^[A-Za-z0-9]+(?:[_-][A-Za-z0-9]+)*$/';

    /**
     * Dictionary keys, and the adjective filenames derived from them.
     *
     * The leading underscore is allowed because the default pool is `_default`.
     */
    public const KEY_PATTERN = '/^[A-Za-z0-9_][A-Za-z0-9_-]*$/';

    /**
     * Validate a locale code, returning it unchanged.
     *
     * The value is interpolated into a dictionary path, so anything that does
     * not match the pattern is rejected rather than sanitised — a locale
     * carrying path segments would otherwise read arbitrary JSON files.
     *
     * @throws InvalidOptionException
     */
    public static function locale(string $locale): string
    {
        if (preg_match(self::LOCALE_PATTERN, $locale) !== 1 || strlen($locale) > 35) {
            throw InvalidOptionException::because(
                "[{$locale}] is not a valid locale. Expected something like 'en', 'it' or 'pt_BR'.",
            );
        }

        return $locale;
    }

    /**
     * Validate a dictionary key, returning it unchanged.
     *
     * The key becomes a filename in the dictionary path, so a value that does
     * not match the pattern is rejected outright; letting one through would
     * turn a user-supplied key into a path traversal.
     *
     * @throws InvalidOptionException
     */
    public static function key(string $key): string
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1 || strlen($key) > 64) {
            throw InvalidOptionException::because(
                "[{$key}] is not a valid dictionary key. Use letters, digits, underscores and hyphens.",
            );
        }

        return $key;
    }

    /**
     * Determine whether the value is a usable locale.
     */
    public static function isLocale(string $locale): bool
    {
        return preg_match(self::LOCALE_PATTERN, $locale) === 1 && strlen($locale) <= 35;
    }
}
