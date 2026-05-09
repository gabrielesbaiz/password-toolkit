<?php

namespace Gabrielesbaiz\PasswordToolkit\Support;

class Entropy
{
    public const LABELS = [
        0 => 'very_weak',
        1 => 'weak',
        2 => 'fair',
        3 => 'strong',
        4 => 'very_strong',
    ];

    public static function charsetBits(string $password): array
    {
        $len = mb_strlen($password);
        if ($len === 0) {
            return ['bits' => 0.0, 'flags' => ['lower' => false, 'upper' => false, 'digits' => false, 'symbols' => false], 'length' => 0];
        }

        $flags = [
            'lower' => (bool) preg_match('/[a-z]/', $password),
            'upper' => (bool) preg_match('/[A-Z]/', $password),
            'digits' => (bool) preg_match('/\d/', $password),
            'symbols' => (bool) preg_match('/[^A-Za-z0-9]/', $password),
        ];

        $charset = 0;
        if ($flags['lower']) $charset += 26;
        if ($flags['upper']) $charset += 26;
        if ($flags['digits']) $charset += 10;
        if ($flags['symbols']) $charset += 32;

        $bits = $charset > 0 ? $len * log($charset, 2) : 0.0;
        $bits *= self::repetitionPenalty($password);

        return ['bits' => $bits, 'flags' => $flags, 'length' => $len];
    }

    public static function structuralBits(int $namesPool, int $adjectivesPool, int $numbersDigits, string $leetspeak): array
    {
        $components = [];
        $components['name'] = $namesPool > 0 ? log($namesPool, 2) : 0.0;
        $components['adjective'] = $adjectivesPool > 0 ? log($adjectivesPool, 2) : 0.0;
        $components['number'] = $numbersDigits > 0 ? $numbersDigits * log(10, 2) : 0.0;
        $components['leetspeak_bonus'] = match ($leetspeak) {
            'basic' => 6.0,
            'advanced' => 12.0,
            default => 0.0,
        };
        $components['total'] = $components['name'] + $components['adjective'] + $components['number'] + $components['leetspeak_bonus'];

        return $components;
    }

    public static function score(float $bits): int
    {
        return match (true) {
            $bits < 28 => 0,
            $bits < 36 => 1,
            $bits < 60 => 2,
            $bits < 128 => 3,
            default => 4,
        };
    }

    public static function label(int $score): string
    {
        return self::LABELS[$score] ?? 'unknown';
    }

    public static function crackTime(float $bits, float $guessesPerSecond = 1e10): array
    {
        $seconds = pow(2, $bits) / $guessesPerSecond;
        return ['seconds' => $seconds, 'human' => self::humanizeSeconds($seconds)];
    }

    public static function humanizeSeconds(float $s): string
    {
        if ($s < 1) return 'instant';
        if ($s < 60) return sprintf('%d seconds', (int) $s);
        if ($s < 3600) return sprintf('%d minutes', (int) ($s / 60));
        if ($s < 86400) return sprintf('%d hours', (int) ($s / 3600));
        if ($s < 31536000) return sprintf('%d days', (int) ($s / 86400));
        if ($s < 31536000 * 100) return sprintf('%d years', (int) ($s / 31536000));
        if ($s < 31536000 * 1e6) return sprintf('%d centuries', (int) ($s / (31536000 * 100)));
        return 'eternity';
    }

    private static function repetitionPenalty(string $password): float
    {
        $len = strlen($password);
        if ($len < 3) return 1.0;

        if (preg_match('/(.)\1{2,}/', $password)) return 0.6;
        if (preg_match('/(?:abc|bcd|cde|def|123|234|345|456|567|678|789|qwe|wer|ert)/i', $password)) return 0.7;

        $unique = count(array_unique(str_split(strtolower($password))));
        $ratio = $unique / $len;
        if ($ratio < 0.4) return 0.7;

        return 1.0;
    }
}
