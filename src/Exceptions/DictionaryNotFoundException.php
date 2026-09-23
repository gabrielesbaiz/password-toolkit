<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Exceptions;

final class DictionaryNotFoundException extends PasswordToolkitException
{
    /**
     * Create a new exception for a dictionary key that is not registered.
     */
    public static function key(string $key): self
    {
        return self::because("No dictionary registered under the key [{$key}].");
    }

    /**
     * Create a new exception for a dictionary with no adjectives in either locale.
     */
    public static function adjectives(string $key, string $locale, string $fallback): self
    {
        return self::because(
            "No adjectives found for dictionary [{$key}] in locale [{$locale}] or fallback [{$fallback}]. "
            .'Add resources for that locale, or ship a _default file for it.',
        );
    }
}
