<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Enums;

use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * How the words are cased once they have been drawn.
 *
 * Casing is worth zero entropy: it is a deterministic transform of an
 * already-chosen password, exactly like leetspeak, so an attacker who knows the
 * configuration gains nothing from it. Choose it for legibility, or to satisfy
 * a policy that demands an upper-case letter, not for strength.
 */
enum Casing: string
{
    case Title = 'title';

    case Lower = 'lower';

    case Upper = 'upper';

    case Preserve = 'preserve';

    /**
     * Parse the given value into a casing mode.
     *
     * @throws InvalidOptionException
     */
    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw InvalidOptionException::because(
                "Unknown case [{$value}]. Expected title, lower, upper or preserve.",
            );
    }

    /**
     * Apply this casing to one word.
     */
    public function apply(string $word): string
    {
        return match ($this) {
            self::Title => mb_convert_case($word, MB_CASE_TITLE),
            self::Lower => mb_strtolower($word),
            self::Upper => mb_strtoupper($word),
            self::Preserve => $word,
        };
    }

    /**
     * Apply this casing to a name.
     *
     * Title case leaves a name alone. Names are proper nouns stored in the form
     * the language actually writes them — McFly, DeLarge, van Gogh — and
     * MB_CASE_TITLE would flatten those to Mcfly and Delarge. A dictionary
     * author has already decided how the name is spelled, so the only casing
     * modes that overrule them are the ones asked for explicitly.
     */
    public function applyToName(string $name): string
    {
        return $this === self::Title ? $name : $this->apply($name);
    }
}
