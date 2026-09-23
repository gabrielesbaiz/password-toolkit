<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Enums;

use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * How widely recognisable a dictionary's names are.
 *
 * This is the attribute the package's own purpose implies. A memorable password
 * is only memorable if the reader recognises the word: Luke-Skywalker works
 * anywhere, Guaglione means nothing outside southern Italy, and
 * Aquila-della-Notte means nothing to most Italians either. An application
 * serving an international audience should be able to say so and get a sensible
 * pool, rather than hand-listing 91 keys.
 */
enum Reach: string
{
    /** Recognisable more or less anywhere: Star Wars, Ferrari, Zeus. */
    case Global = 'global';

    /** Recognisable to an Italian audience: Lucio Battisti, Cacciucco. */
    case Italian = 'italian';

    /** Specialist even in Italy: dialect words, old currencies, circus terms. */
    case Niche = 'niche';

    /**
     * Parse the given value into a reach level.
     *
     * @throws InvalidOptionException
     */
    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw InvalidOptionException::because(
                "Unknown reach [{$value}]. Expected global, italian or niche.",
            );
    }

    /**
     * Get the translated, human-facing name.
     */
    public function label(): string
    {
        return (string) trans('password-toolkit::dictionaries.reach.'.$this->value);
    }

    /**
     * Determine whether this reach is included when asking for at least the given minimum.
     *
     * Asking for `italian` accepts `global` too, because anything universally
     * recognisable is also recognisable to an Italian.
     */
    public function satisfies(self $minimum): bool
    {
        return $this->breadth() >= $minimum->breadth();
    }

    /**
     * Get the numeric rank used to order the reach levels against one another.
     */
    private function breadth(): int
    {
        return match ($this) {
            self::Niche => 1,
            self::Italian => 2,
            self::Global => 3,
        };
    }
}
