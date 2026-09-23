<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Enums;

use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * How aggressively to substitute characters after assembly.
 *
 * There is one substitution table. Basic uses the subset of it listed in
 * BASIC_KEYS, which keeps the password the same length and still readable
 * aloud; advanced uses the whole table, which expands the string and helps
 * against strict minimum-length policies.
 */
enum Leetspeak: string
{
    case None = 'none';

    case Basic = 'basic';

    case Advanced = 'advanced';

    /**
     * The full substitution table.
     *
     * Basic mode uses the single-character entries; advanced uses all of them.
     *
     * @var array<string, string>
     */
    public const MAP = [
        'a' => '4',
        'b' => '8',
        'c' => '<',
        'e' => '3',
        'f' => '|=',
        'g' => '9',
        'h' => '#',
        'i' => '1',
        'j' => '_|',
        'k' => '|<',
        'l' => '1',
        'm' => '|V|',
        'n' => '|\\|',
        'o' => '0',
        'p' => '|D',
        'q' => '9',
        'r' => '2',
        's' => '$',
        't' => '7',
        'u' => '|_|',
        'v' => '\\/',
        'w' => '\\/\\/',
        'x' => '%',
        'y' => '`/',
        'z' => '2',
    ];

    /**
     * The letters basic mode touches.
     *
     * This is the 1.x basic set, kept verbatim: it is documented in the README
     * and changing it would silently change every password a user's existing
     * config produces. Note it is not simply "the single-character entries" —
     * 's' maps to '$' and is in, while 'c' maps to '<' and is out.
     *
     * @var array<int, string>
     */
    public const BASIC_KEYS = ['a', 'b', 'e', 'g', 'i', 'l', 'o', 'q', 'r', 's', 't', 'z'];

    /**
     * Parse the given value into a leetspeak mode.
     *
     * The 1.x spelling 'no', as well as 'false' and an empty string, are still
     * accepted and resolve to none.
     *
     * @throws InvalidOptionException
     */
    public static function parse(string $value): self
    {
        $value = strtolower(trim($value));

        // 'no' was the 1.x spelling and stays accepted so a published config
        // from 1.x keeps booting after `composer update`.
        if ($value === 'no' || $value === 'false' || $value === '') {
            return self::None;
        }

        return self::tryFrom($value)
            ?? throw InvalidOptionException::because("Unknown leetspeak mode [{$value}]. Expected none, basic or advanced.");
    }

    /**
     * Get the substitution table for this mode.
     *
     * @return array<string, string>
     */
    public function map(): array
    {
        return match ($this) {
            self::None => [],
            self::Basic => array_intersect_key(self::MAP, array_flip(self::BASIC_KEYS)),
            self::Advanced => self::MAP,
        };
    }

    /**
     * Get the extra entropy bits this mode contributes, which is always zero.
     *
     * Leetspeak is a deterministic transform of an already-chosen password: it
     * does not enlarge the set of passwords the package can produce, so it adds
     * no work for an attacker who knows the configuration — which is precisely
     * the attacker the structural model assumes. 2.0.0-dev credited 6 and 12
     * bits here; those figures modelled an attacker who had not read the config
     * file, and overstated the strength of every leetspeak password.
     *
     * Use leetspeak to satisfy a character-class policy, not to add strength.
     */
    public function entropyBonus(): float
    {
        return 0.0;
    }
}
