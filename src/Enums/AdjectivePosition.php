<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Enums;

use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * Where the adjective sits relative to the name.
 *
 * This is a property of the language, not a preference. Italian puts the
 * adjective after the noun — "Goldrake Mitico" — while English puts it before:
 * "Legendary Goldrake". Getting it backwards produces passwords that read as
 * broken to a native speaker, which defeats the point of a memorable password.
 *
 * Each locale declares its own order in its _default adjective pack, so a new
 * language brings its word order with it.
 */
enum AdjectivePosition: string
{
    case Before = 'before';

    case After = 'after';

    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw InvalidOptionException::because("Unknown adjective position [{$value}]. Expected before or after.");
    }

    /**
     * The two words in reading order.
     *
     * @return array{0: string, 1: string}
     */
    public function order(string $name, string $adjective): array
    {
        return match ($this) {
            self::Before => [$adjective, $name],
            self::After => [$name, $adjective],
        };
    }
}
