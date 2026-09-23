<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Support;

use Gabrielesbaiz\PasswordToolkit\Enums\Strength;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * The result of scoring one password.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class StrengthReport implements Arrayable, Jsonable, JsonSerializable
{
    /** Machine-readable band, e.g. "very_strong". */
    public string $label;

    /** Numeric band, 0 through 4. */
    public int $score;

    /**
     * Create a new strength report instance.
     *
     * @param  array<string, float>  $components
     * @param  array{lower: bool, upper: bool, digits: bool, symbols: bool}  $charsetFlags
     */
    public function __construct(
        public float $entropyBits,
        public int $length,
        public Strength $strength,
        public array $components,
        public array $charsetFlags,
        public float $crackTimeSeconds,
        public string $crackTimeHuman,
    ) {
        // Mirrored as plain properties because 1.x exposed them that way and
        // call sites read $report->label / $report->score directly.
        $this->label = $strength->value;
        $this->score = $strength->score();
    }

    /**
     * Get the translated, human-facing band name.
     */
    public function displayLabel(): string
    {
        return $this->strength->label();
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entropy_bits' => round($this->entropyBits, 2),
            'length' => $this->length,
            'label' => $this->label,
            'display_label' => $this->strength->label(),
            'score' => $this->score,
            'color' => $this->strength->color(),
            'components' => array_map(static fn (float $bits): float => round($bits, 2), $this->components),
            'charset_flags' => $this->charsetFlags,
            'crack_time_seconds' => $this->crackTimeSeconds,
            'crack_time_human' => $this->crackTimeHuman,
        ];
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }
}
