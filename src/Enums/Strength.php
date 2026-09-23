<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Enums;

use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * The five-band strength scale, keyed off estimated entropy.
 *
 * The default thresholds follow the usual convention: 28 bits is where a
 * password stops being trivially brute-forceable online, 60 is where an offline
 * attack against a fast hash starts to cost real money, and 128 is the point
 * past which the password is no longer the weakest link.
 *
 * They are defaults, not law. What counts as "strong enough" depends on the
 * hash behind it and on what the password guards, so an application can move
 * the bands with `strength.thresholds` — the values arrive as an argument
 * because an enum case has no business reading configuration.
 */
enum Strength: string
{
    case VeryWeak = 'very_weak';

    case Weak = 'weak';

    case Fair = 'fair';

    case Strong = 'strong';

    case VeryStrong = 'very_strong';

    /**
     * The shipped bands, in bits.
     *
     * @var array<string, float>
     */
    public const DEFAULT_THRESHOLDS = [
        'weak' => 28.0,
        'fair' => 36.0,
        'strong' => 60.0,
        'very_strong' => 128.0,
    ];

    /**
     * Get the band the given entropy falls into.
     *
     * @param  array<string, mixed>|null  $thresholds  partial overrides, in bits
     */
    public static function fromBits(float $bits, ?array $thresholds = null): self
    {
        $bands = self::thresholds($thresholds);

        return match (true) {
            $bits < $bands['weak'] => self::VeryWeak,
            $bits < $bands['fair'] => self::Weak,
            $bits < $bands['strong'] => self::Fair,
            $bits < $bands['very_strong'] => self::Strong,
            default => self::VeryStrong,
        };
    }

    /**
     * Get the four band edges, with any overrides applied and checked.
     *
     * Validated here rather than at every call site: bands that are not
     * ascending do not describe a scale at all, and silently sorting them would
     * hand back a scoring rule nobody wrote.
     *
     * @param  array<string, mixed>|null  $overrides
     * @return array<string, float>
     */
    public static function thresholds(?array $overrides = null): array
    {
        if ($overrides === null || $overrides === []) {
            return self::DEFAULT_THRESHOLDS;
        }

        $bands = self::DEFAULT_THRESHOLDS;

        foreach ($overrides as $band => $bits) {
            if (! array_key_exists($band, $bands)) {
                throw InvalidOptionException::because(sprintf(
                    'Unknown strength threshold [%s]. Expected %s.',
                    $band,
                    implode(', ', array_keys($bands)),
                ));
            }

            if (! is_numeric($bits)) {
                throw InvalidOptionException::because("Strength threshold [{$band}] must be a number.");
            }

            $bands[$band] = (float) $bits;
        }

        $previous = 0.0;

        foreach ($bands as $band => $bits) {
            if ($bits <= $previous) {
                throw InvalidOptionException::because(sprintf(
                    'Strength thresholds must ascend, got %s at [%s] after %s.',
                    $bits,
                    $band,
                    $previous,
                ));
            }

            $previous = $bits;
        }

        return $bands;
    }

    /**
     * Get the band for the given numeric score, 0 through 4.
     */
    public static function fromScore(int $score): self
    {
        return match ($score) {
            0 => self::VeryWeak,
            1 => self::Weak,
            2 => self::Fair,
            3 => self::Strong,
            default => self::VeryStrong,
        };
    }

    /**
     * Get the numeric score, 0 through 4, for callers driving a meter.
     */
    public function score(): int
    {
        return match ($this) {
            self::VeryWeak => 0,
            self::Weak => 1,
            self::Fair => 2,
            self::Strong => 3,
            self::VeryStrong => 4,
        };
    }

    /**
     * Get the translated, human-facing label.
     */
    public function label(): string
    {
        return (string) trans('password-toolkit::strength.'.$this->value);
    }

    /**
     * Get a colour hint for meters, in the traffic-light convention.
     */
    public function color(): string
    {
        return match ($this) {
            self::VeryWeak, self::Weak => 'red',
            self::Fair => 'amber',
            self::Strong, self::VeryStrong => 'green',
        };
    }
}
