<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Rules;

use Closure;
use Gabrielesbaiz\PasswordToolkit\Enums\Strength;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Rejects a password whose estimated strength is below a floor.
 *
 * Scored with the charset model, because the value under validation is one the
 * user chose — the structural model only means something for a password this
 * package generated.
 */
class StrongPassword implements ValidationRule
{
    public function __construct(
        protected readonly Strength $minimum = Strength::Strong,
    ) {}

    public static function atLeast(Strength|int|string $minimum): self
    {
        return new self(match (true) {
            $minimum instanceof Strength => $minimum,
            is_int($minimum) => Strength::fromScore($minimum),
            default => Strength::from($minimum),
        });
    }

    public static function fair(): self
    {
        return new self(Strength::Fair);
    }

    public static function strong(): self
    {
        return new self(Strength::Strong);
    }

    public static function veryStrong(): self
    {
        return new self(Strength::VeryStrong);
    }

    /**
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $value = is_scalar($value) ? (string) $value : '';
        }

        $report = PasswordToolkit::strength($value);

        if ($report->score >= $this->minimum->score()) {
            return;
        }

        $fail('password-toolkit::strength.too_weak')->translate([
            'actual' => $report->displayLabel(),
            'expected' => $this->minimum->label(),
        ]);
    }
}
