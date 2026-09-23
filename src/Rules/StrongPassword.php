<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Rules;

use Closure;
use Gabrielesbaiz\PasswordToolkit\Enums\Strength;
use Gabrielesbaiz\PasswordToolkit\Enums\StrengthModel;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Rejects a password whose estimated strength is below a floor.
 *
 * Scored with the charset model by default, because the value under validation
 * is one the user chose: you know nothing about how they chose it, so the only
 * honest question is how many strings of that length over that alphabet exist.
 * The structural model means something for a password this package generated
 * and nothing at all for one typed into a signup form — set
 * `strength.rule_model` to 'structural' only where the rule is guarding
 * generated passwords.
 */
class StrongPassword implements ValidationRule
{
    /**
     * Create a new strong password rule instance.
     */
    public function __construct(
        protected readonly Strength $minimum = Strength::Strong,
        protected readonly ?StrengthModel $model = null,
    ) {}

    /**
     * Create a rule requiring at least the given strength.
     */
    public static function atLeast(Strength|int|string $minimum): self
    {
        return new self(match (true) {
            $minimum instanceof Strength => $minimum,
            is_int($minimum) => Strength::fromScore($minimum),
            default => Strength::from($minimum),
        });
    }

    /**
     * Create a rule requiring at least fair strength.
     */
    public static function fair(): self
    {
        return new self(Strength::Fair);
    }

    /**
     * Create a rule requiring at least strong strength.
     */
    public static function strong(): self
    {
        return new self(Strength::Strong);
    }

    /**
     * Create a rule requiring at least very strong strength.
     */
    public static function veryStrong(): self
    {
        return new self(Strength::VeryStrong);
    }

    /**
     * Score with one model regardless of what the config says.
     */
    public function using(StrengthModel|string $model): self
    {
        return new self($this->minimum, is_string($model) ? StrengthModel::parse($model) : $model);
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $value = is_scalar($value) ? (string) $value : '';
        }

        $options = Options::fromConfig();

        $report = ($this->model ?? $options->ruleModel) === StrengthModel::Structural
            ? PasswordToolkit::structuralReport($value, $options)
            : PasswordToolkit::strength($value, $options);

        if ($report->score >= $this->minimum->score()) {
            return;
        }

        $fail('password-toolkit::strength.too_weak')->translate([
            'actual' => $report->displayLabel(),
            'expected' => $this->minimum->label(),
        ]);
    }
}
