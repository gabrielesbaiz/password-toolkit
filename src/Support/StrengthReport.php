<?php

namespace Gabrielesbaiz\PasswordToolkit\Support;

class StrengthReport
{
    public function __construct(
        public readonly float $entropyBits,
        public readonly int $length,
        public readonly string $label,
        public readonly int $score,
        public readonly array $components,
        public readonly array $charsetFlags,
        public readonly float $crackTimeSeconds,
        public readonly string $crackTimeHuman,
    ) {
    }

    public function toArray(): array
    {
        return [
            'entropy_bits' => round($this->entropyBits, 2),
            'length' => $this->length,
            'label' => $this->label,
            'score' => $this->score,
            'components' => $this->components,
            'charset_flags' => $this->charsetFlags,
            'crack_time_seconds' => $this->crackTimeSeconds,
            'crack_time_human' => $this->crackTimeHuman,
        ];
    }
}
