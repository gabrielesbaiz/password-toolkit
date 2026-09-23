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
     * Backwards-compatible label map.
     *
     * Prefer the Strength enum.
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
     * Get the entropy of an arbitrary password under the charset model.
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
     * Get the entropy under the structural model.
     *
     * The structural model is the size of the space this package can actually
     * produce, given the pools in play.
     *
     * Leetspeak and casing contribute nothing — both are deterministic
     * transforms of an already-chosen password. The leetspeak component is
     * still reported, at zero, so callers reading the array do not have to
     * branch on whether the key is there, and `second_adjective` is reported
     * the same way when only one adjective was drawn.
     *
     * @param  int  $adjectiveCount  how many adjectives were actually drawn
     * @return array{name: float, adjective: float, second_adjective: float, number: float, leetspeak_bonus: float, total: float}
     */
    public static function structuralBits(
        int $namesPool,
        int $adjectivesPool,
        int $numbersDigits,
        Leetspeak|string $leetspeak = Leetspeak::None,
        bool $numbersAllowLeadingZero = false,
        int $adjectiveCount = 1,
    ): array {
        $mode = is_string($leetspeak) ? Leetspeak::parse($leetspeak) : $leetspeak;

        $components = [
            'name' => $namesPool > 0 ? log($namesPool, 2) : 0.0,
            'adjective' => $adjectivesPool > 0 ? log($adjectivesPool, 2) : 0.0,
            // Drawn without replacement, so the second adjective chooses from
            // one fewer word: the pair is worth log2(A) + log2(A-1), not
            // 2 * log2(A). A pool of one cannot supply a second at all.
            'second_adjective' => $adjectiveCount > 1 && $adjectivesPool > 1
                ? log($adjectivesPool - 1, 2)
                : 0.0,
            'number' => self::numberBits($numbersDigits, $numbersAllowLeadingZero),
            'leetspeak_bonus' => $mode->entropyBonus(),
        ];

        $components['total'] = array_sum($components);

        return $components;
    }

    /**
     * Get the entropy of the numeric segment.
     *
     * A fixed-width segment that may not start with zero is not worth
     * d * log2(10): six digits drawn from 100000..999999 are 900,000 values,
     * not 1,000,000, so the honest figure is log2(9 * 10^(d-1)) — about 0.15
     * bits less. Until 2.0.0 the model credited the full range either way,
     * which made every reported figure very slightly optimistic.
     */
    public static function numberBits(int $digits, bool $allowLeadingZero = false): float
    {
        if ($digits < 1) {
            return 0.0;
        }

        return $allowLeadingZero
            ? $digits * log(10, 2)
            : log(9, 2) + (($digits - 1) * log(10, 2));
    }

    /**
     * Get the strength band the given entropy falls into.
     *
     * @param  array<string, mixed>|null  $thresholds
     */
    public static function strength(float $bits, ?array $thresholds = null): Strength
    {
        return Strength::fromBits($bits, $thresholds);
    }

    /**
     * Get the numeric score, 0 through 4, for the given entropy.
     *
     * @param  array<string, mixed>|null  $thresholds
     */
    public static function score(float $bits, ?array $thresholds = null): int
    {
        return Strength::fromBits($bits, $thresholds)->score();
    }

    /**
     * Get the machine-readable band name for the given score.
     */
    public static function label(int $score): string
    {
        return Strength::fromScore($score)->value;
    }

    /**
     * Get the expected time to exhaust the space at a given guess rate.
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

    /**
     * Format a duration in seconds as a human-readable string.
     *
     * The unit count stays a float until its range has been checked. A strong
     * score divides out to more centuries than PHP_INT_MAX can hold, and
     * casting to int first wrapped the value, slipped past the ceiling guard
     * and printed "0 centuries" for what should read as an eternity.
     */
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
                // Kept as a float until the range is checked: a strong charset
                // score divides out to more centuries than PHP_INT_MAX can
                // hold, and casting first wrapped it to a meaningless integer
                // — which then slipped past the guard below and printed
                // "0 centuries" for an eternity.
                $count = $seconds / $span;

                // Past a million centuries the number stops meaning anything.
                if ($unit === 'century' && $count >= 1_000_000) {
                    return (string) trans('password-toolkit::strength.eternity');
                }

                return trans_choice('password-toolkit::strength.'.$unit, (int) $count, ['count' => (int) $count]);
            }
        }

        return trans_choice('password-toolkit::strength.second', (int) $seconds, ['count' => (int) $seconds]);
    }

    /**
     * Get a multiplier that discounts passwords with obvious internal structure.
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
