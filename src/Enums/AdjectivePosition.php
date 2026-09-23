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

    /**
     * Parse the given value into an adjective position.
     *
     * @throws InvalidOptionException
     */
    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw InvalidOptionException::because("Unknown adjective position [{$value}]. Expected before or after.");
    }

    /**
     * Get the two words in reading order.
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

    /**
     * Get every word in reading order, for any number of adjectives.
     *
     * The adjectives stay in one block on their side of the name, which is how
     * both languages stack them: "Brave-Mighty-Goldrake", "Goldrake-Mitico-Potente".
     *
     * @param  array<int, string>  $adjectives
     * @return array<int, string>
     */
    public function words(string $name, array $adjectives): array
    {
        return match ($this) {
            self::Before => [...array_values($adjectives), $name],
            self::After => [$name, ...array_values($adjectives)],
        };
    }

    /**
     * Get the index where the name meets the adjective block.
     *
     * That boundary is where the numeric segment goes when it is configured to
     * sit in the middle.
     */
    public function boundary(int $adjectiveCount): int
    {
        return match ($this) {
            self::Before => max(1, $adjectiveCount),
            self::After => 1,
        };
    }
}
