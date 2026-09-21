<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Enums;

use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * Grammatical gender of a dictionary entry.
 *
 * Italian adjectives agree with the noun they modify, so a name carries a
 * gender and an adjective is only eligible if it matches, or is neutral.
 * Languages without agreement (English, for one) simply mark everything
 * neutral, which makes every adjective eligible for every name.
 */
enum Gender: string
{
    case Male = 'male';

    case Female = 'female';

    case Neutral = 'neutral';

    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw InvalidOptionException::because("Unknown gender [{$value}]. Expected male, female or neutral.");
    }

    /**
     * Whether an adjective of this gender may modify a name of $name gender.
     */
    public function agreesWith(self $name): bool
    {
        return $this === $name || $this === self::Neutral;
    }
}
