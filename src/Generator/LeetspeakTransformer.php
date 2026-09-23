<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Generator;

use Gabrielesbaiz\PasswordToolkit\Enums\Leetspeak;

/**
 * Applies a leetspeak substitution table to an assembled password.
 *
 * 1.x carried two hardcoded tables where one was a strict subset of the other.
 * The table now lives on the enum and this class only walks it.
 */
final class LeetspeakTransformer
{
    /**
     * Apply the given leetspeak substitution table to the text.
     */
    public function apply(string $text, Leetspeak $mode): string
    {
        $map = $mode->map();

        if ($map === []) {
            return $text;
        }

        $out = '';

        // mb_str_split, not str_split: a user dictionary is not bound by the
        // ASCII rule the built-in data follows, and splitting a multi-byte
        // character into bytes would corrupt it.
        foreach (mb_str_split($text) as $char) {
            $out .= $map[mb_strtolower($char)] ?? $char;
        }

        return $out;
    }
}
