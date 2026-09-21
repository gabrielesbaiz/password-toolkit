<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Support;

use Gabrielesbaiz\PasswordToolkit\Enums\Leetspeak;
use Gabrielesbaiz\PasswordToolkit\Enums\Strength;

/**
 * Entropy estimation, in two models.
 *
 * The charset model asks "how many strings of this length over this alphabet",
 * which is what a generic strength meter reports. The structural model asks
 * "how many passwords could this package have produced", which is what an
 * attacker who knows the package actually has to search — usually a much
 * smaller, and much more honest, number.
 */
final class Entropy
{
    public const LOWER_CHARS = 26;

    public const UPPER_CHARS = 26;

    public const DIGIT_CHARS = 10;

    /** Printable ASCII that is neither a letter nor a digit. */
    public const SYMBOL_CHARS = 32;

    /** Repeated runs of the same character, e.g. "aaa". */
    public const PENALTY_REPEAT = 0.6;

    /** Keyboard or alphabet runs, e.g. "abc" or "qwe". */
    public const PENALTY_SEQUENCE = 0.7;

    /** Fewer than this share of characters are distinct. */
    public const MIN_UNIQUE_RATIO = 0.4;

    public const PENALTY_LOW_VARIETY = 0.7;

    /**
     * Backwards-compatible label map. Prefer the Strength enum.
     *
     * @var array<int, string>
     */
    public const LABELS = [
        0 => 'very_weak',
        1 => 'weak',
        2 => 'fair',
        3 => 'strong',
        4 => 'very_strong',
    ];

    private const MINUTE = 60;

    private const HOUR = 3600;

    private const DAY = 86400;

    private const YEAR = 31557600;

    private const CENTURY = self::YEAR * 100;

    /**
     * Entropy of an arbitrary password under the charset model.
     *
     * @return array{bits: float, flags: array{lower: bool, upper: bool, digits: bool, symbols: bool}, length: int}
     */
    public static function charsetBits(string $password): array
    {
        $length = mb_strlen($password);

        $flags = [
            'lower' => (bool) preg_match('/\p{Ll}/u', $password),
            'upper' => (bool) preg_match('/\p{Lu}/u', $password),
            'digits' => (bool) preg_match('/\d/u', $password),
            'symbols' => (bool) preg_match('/[^\p{L}\d]/u', $password),
        ];

        if ($length === 0) {
            return [
                'bits' => 0.0,
                'flags' => ['lower' => false, 'upper' => false, 'digits' => false, 'symbols' => false],
                'length' => 0,
            ];
        }

        $charset = 0;
        $charset += $flags['lower'] ? self::LOWER_CHARS : 0;
        $charset += $flags['upper'] ? self::UPPER_CHARS : 0;
        $charset += $flags['digits'] ? self::DIGIT_CHARS : 0;
        $charset += $flags['symbols'] ? self::SYMBOL_CHARS : 0;

        $bits = $charset > 0 ? $length * log($charset, 2) : 0.0;
        $bits *= self::repetitionPenalty($password);

        return ['bits' => $bits, 'flags' => $flags, 'length' => $length];
    }

    /**
     * Entropy under the structural model: the size of the space this package
     * can actually produce, given the pools in play.
     *
     * @return array{name: float, adjective: float, number: float, leetspeak_bonus: float, total: float}
     */
    public static function structuralBits(
        int $namesPool,
        int $adjectivesPool,
        int $numbersDigits,
        Leetspeak|string $leetspeak = Leetspeak::None,
    ): array {
        $mode = is_string($leetspeak) ? Leetspeak::parse($leetspeak) : $leetspeak;

        $components = [
            'name' => $namesPool > 0 ? log($namesPool, 2) : 0.0,
            'adjective' => $adjectivesPool > 0 ? log($adjectivesPool, 2) : 0.0,
            'number' => $numbersDigits > 0 ? $numbersDigits * log(10, 2) : 0.0,
            'leetspeak_bonus' => $mode->entropyBonus(),
        ];

        $components['total'] = array_sum($components);

        return $components;
    }

    public static function strength(float $bits): Strength
    {
        return Strength::fromBits($bits);
    }

    public static function score(float $bits): int
    {
        return Strength::fromBits($bits)->score();
    }

    public static function label(int $score): string
    {
        return Strength::fromScore($score)->value;
    }

    /**
     * Expected time to exhaust the space at a given guess rate.
     *
     * Computed in log space. 2 ** $bits overflows to INF well inside the range
     * a long password produces, and an INF on the report is not JSON
     * serialisable — which made StrengthReport::toArray() unusable for exactly
     * the strongest passwords.
     *
     * @return array{seconds: float, human: string}
     */
    public static function crackTime(float $bits, float $guessesPerSecond = 1e10): array
    {
        if ($guessesPerSecond <= 0.0) {
            $guessesPerSecond = 1e10;
        }

        $log10Seconds = ($bits * log10(2)) - log10($guessesPerSecond);

        $seconds = $log10Seconds >= log10(PHP_FLOAT_MAX)
            ? PHP_FLOAT_MAX
            : max(0.0, 10 ** $log10Seconds);

        return ['seconds' => $seconds, 'human' => self::humanizeSeconds($seconds)];
    }

    public static function humanizeSeconds(float $seconds): string
    {
        if ($seconds < 1) {
            return (string) trans('password-toolkit::strength.instant');
        }

        foreach ([
            'century' => self::CENTURY,
            'year' => self::YEAR,
            'day' => self::DAY,
            'hour' => self::HOUR,
            'minute' => self::MINUTE,
        ] as $unit => $span) {
            if ($seconds >= $span) {
                $count = (int) ($seconds / $span);

                // Past a million centuries the number stops meaning anything.
                if ($unit === 'century' && $count >= 1_000_000) {
                    return (string) trans('password-toolkit::strength.eternity');
                }

                return trans_choice('password-toolkit::strength.'.$unit, $count, ['count' => $count]);
            }
        }

        return trans_choice('password-toolkit::strength.second', (int) $seconds, ['count' => (int) $seconds]);
    }

    /**
     * A multiplier that discounts passwords with obvious internal structure.
     */
    private static function repetitionPenalty(string $password): float
    {
        $characters = mb_str_split($password);
        $length = count($characters);

        if ($length < 3) {
            return 1.0;
        }

        if (preg_match('/(.)\1{2,}/u', $password) === 1) {
            return self::PENALTY_REPEAT;
        }

        if (preg_match('/(?:abc|bcd|cde|def|123|234|345|456|567|678|789|qwe|wer|ert)/iu', $password) === 1) {
            return self::PENALTY_SEQUENCE;
        }

        $unique = count(array_unique(array_map(mb_strtolower(...), $characters)));

        return ($unique / $length) < self::MIN_UNIQUE_RATIO
            ? self::PENALTY_LOW_VARIETY
            : 1.0;
    }
}
