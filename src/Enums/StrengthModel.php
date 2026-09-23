<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Enums;

use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * Which entropy model a score is taken from.
 *
 * The charset model asks how many strings of this length over this alphabet
 * exist; the structural model asks how many passwords this package could have
 * produced. Which one is honest depends entirely on where the password came
 * from, so it is a choice the caller has to make, not a default we can pick for
 * every situation.
 */
enum StrengthModel: string
{
    case Charset = 'charset';

    case Structural = 'structural';

    /**
     * Parse the given value into a strength model.
     *
     * @throws InvalidOptionException
     */
    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw InvalidOptionException::because(
                "Unknown strength model [{$value}]. Expected charset or structural.",
            );
    }
}
