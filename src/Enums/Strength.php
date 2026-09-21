<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Enums;

/**
 * The five-band strength scale, keyed off estimated entropy.
 *
 * The thresholds follow the usual convention: 28 bits is where a password
 * stops being trivially brute-forceable online, 60 is where an offline attack
 * against a fast hash starts to cost real money, and 128 is the point past
 * which the password is no longer the weakest link.
 */
enum Strength: string
{
    case VeryWeak = 'very_weak';

    case Weak = 'weak';

    case Fair = 'fair';

    case Strong = 'strong';

    case VeryStrong = 'very_strong';

    public static function fromBits(float $bits): self
    {
        return match (true) {
            $bits < 28.0 => self::VeryWeak,
            $bits < 36.0 => self::Weak,
            $bits < 60.0 => self::Fair,
            $bits < 128.0 => self::Strong,
            default => self::VeryStrong,
        };
    }

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
     * Numeric score, 0 through 4, for callers driving a meter.
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
     * Translated, human-facing label.
     */
    public function label(): string
    {
        return (string) trans('password-toolkit::strength.'.$this->value);
    }

    /**
     * A colour hint for meters, in the traffic-light convention.
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
