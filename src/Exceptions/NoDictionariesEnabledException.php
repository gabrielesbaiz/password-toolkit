<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Exceptions;

/**
 * Thrown when the enabled set resolves to nothing.
 *
 * 1.x returned null here, which pushed a configuration mistake into every
 * call site as a nullable return. It is a misconfiguration, so it throws.
 */
final class NoDictionariesEnabledException extends PasswordToolkitException
{
    public static function make(): self
    {
        return self::because(
            'No dictionaries are enabled. Check password-toolkit.dictionaries.enabled, '
            .'dictionaries.except and dictionaries.types, or the ->only()/->except() calls on the builder.',
        );
    }
}
