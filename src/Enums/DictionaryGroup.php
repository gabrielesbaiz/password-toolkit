<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Enums;

use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * The thematic bucket a dictionary belongs to.
 *
 * `type` says whether a dictionary holds people or things, which is structural.
 * This says what it is *about*, which is what someone picking a dictionary
 * actually cares about — and it is a closed vocabulary so a UI can render a
 * stable list and a typo fails the build rather than creating a silent orphan.
 */
enum DictionaryGroup: string
{
    case Food = 'food';

    case Drink = 'drink';

    case Nature = 'nature';

    case Places = 'places';

    case Culture = 'culture';

    case Arts = 'arts';

    case Screen = 'screen';

    case Sport = 'sport';

    case Science = 'science';

    case History = 'history';

    case Myth = 'myth';

    case Vehicles = 'vehicles';

    /**
     * Parse the given value into a dictionary group.
     *
     * @throws InvalidOptionException
     */
    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw InvalidOptionException::because(
                "Unknown dictionary group [{$value}]. Expected one of: ".implode(', ', self::values()).'.',
            );
    }

    /**
     * Get every group value, for validation and for listing the vocabulary.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the translated, human-facing name.
     */
    public function label(): string
    {
        return (string) trans('password-toolkit::dictionaries.groups.'.$this->value);
    }

    /**
     * Get a display icon, for a picker that would otherwise be 91 identical rows.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Food => '🍝',
            self::Drink => '🍷',
            self::Nature => '🏔️',
            self::Places => '🏛️',
            self::Culture => '🎭',
            self::Arts => '🎨',
            self::Screen => '🎬',
            self::Sport => '⚽',
            self::Science => '🔬',
            self::History => '📜',
            self::Myth => '⚡',
            self::Vehicles => '🏎️',
        };
    }
}
