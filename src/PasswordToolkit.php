<?php

namespace Gabrielesbaiz\PasswordToolkit;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Gabrielesbaiz\PasswordToolkit\Support\Entropy;
use Gabrielesbaiz\PasswordToolkit\Support\StrengthReport;

class PasswordToolkit
{
    protected static ?array $poolCache = null;

    /**
     * Generate password and return both string and strength report.
     */
    public static function generateWithReport(): array
    {
        $password = self::generate();
        if ($password === null) {
            return ['password' => null, 'report' => null];
        }
        return ['password' => $password, 'report' => self::structuralReport($password)];
    }

    /**
     * Strength report for an arbitrary password (charset model).
     */
    public static function strength(string $password): StrengthReport
    {
        $cs = Entropy::charsetBits($password);
        $bits = $cs['bits'];
        $score = Entropy::score($bits);
        $gps = (float) config('password-toolkit.strength.guesses_per_second', 1e10);
        $crack = Entropy::crackTime($bits, $gps);

        return new StrengthReport(
            entropyBits: $bits,
            length: $cs['length'],
            label: Entropy::label($score),
            score: $score,
            components: ['charset_bits' => $bits],
            charsetFlags: $cs['flags'],
            crackTimeSeconds: $crack['seconds'],
            crackTimeHuman: $crack['human'],
        );
    }

    /**
     * Strength report using structural model (knowledge of dictionaries).
     */
    public static function structuralReport(string $password): StrengthReport
    {
        [$names, $adjectives] = self::poolSizes();
        $digits = config('password-toolkit.add_numbers', false) ? (int) config('password-toolkit.numbers_digits', 4) : 0;
        $leet = config('password-toolkit.leetspeak_conversion', 'no');

        $components = Entropy::structuralBits($names, $adjectives, $digits, $leet);
        $bits = $components['total'];
        $score = Entropy::score($bits);
        $cs = Entropy::charsetBits($password);
        $gps = (float) config('password-toolkit.strength.guesses_per_second', 1e10);
        $crack = Entropy::crackTime($bits, $gps);

        return new StrengthReport(
            entropyBits: $bits,
            length: mb_strlen($password),
            label: Entropy::label($score),
            score: $score,
            components: $components,
            charsetFlags: $cs['flags'],
            crackTimeSeconds: $crack['seconds'],
            crackTimeHuman: $crack['human'],
        );
    }

    /**
     * Count entries across enabled dictionaries (cached).
     *
     * @return array{0:int,1:int} [namesPool, adjectivesPool]
     */
    public static function poolSizes(): array
    {
        if (self::$poolCache !== null) {
            return self::$poolCache;
        }

        $peopleConfig = config('password-toolkit.name_types.people', []);
        $thingsConfig = config('password-toolkit.name_types.things', []);

        $names = 0;
        $adjectiveTotals = [];

        $scan = function (string $dir, array $cfg) use (&$names, &$adjectiveTotals) {
            if (! is_dir($dir)) return;
            foreach (File::allFiles($dir) as $file) {
                $key = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                if (! ($cfg[$key] ?? false)) continue;
                $data = json_decode(File::get($file), true);
                $names += count($data['values'] ?? []);
                $adjFile = __DIR__ . '/Data/Adjectives/' . $key . '_adjectives.json';
                if (is_file($adjFile)) {
                    $adj = json_decode(File::get($adjFile), true);
                    $adjectiveTotals[] = count($adj ?? []);
                }
            }
        };

        $scan(__DIR__ . '/Data/Names/People', $peopleConfig);
        $scan(__DIR__ . '/Data/Names/Things', $thingsConfig);

        $avgAdj = empty($adjectiveTotals) ? 0 : (int) (array_sum($adjectiveTotals) / count($adjectiveTotals));

        return self::$poolCache = [$names, $avgAdj];
    }

    public static function clearPoolCache(): void
    {
        self::$poolCache = null;
    }

    /**
     * Generate password(s).
     *
     * When $count === 1 (default) returns a single password string (or null
     * if no dictionaries are available). When $count > 1 returns an array of
     * passwords; entries that fail to generate are skipped.
     *
     * @param  int $count
     * @return string|array|null
     */
    public static function generate(int $count = 1): string|array|null
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('Count must be >= 1.');
        }

        if ($count > 1) {
            $out = [];
            for ($i = 0; $i < $count; $i++) {
                $p = self::generateOne();
                if ($p !== null) {
                    $out[] = $p;
                }
            }
            return $out;
        }

        return self::generateOne();
    }

    /**
     * Generate a batch of passwords each with its strength report.
     *
     * @param  int $count
     * @return array<int, array{password: string, report: \Gabrielesbaiz\PasswordToolkit\Support\StrengthReport}>
     */
    public static function generateManyWithReport(int $count): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('Count must be >= 1.');
        }

        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $p = self::generateOne();
            if ($p === null) continue;
            $out[] = ['password' => $p, 'report' => self::structuralReport($p)];
        }
        return $out;
    }

    /**
     * Internal single-password generator (extracted body of legacy generate()).
     */
    protected static function generateOne(): ?string
    {
        $nameData = self::getRandomNameData();

        if ($nameData->isEmpty()) {
            return null;
        }

        $separator = config('password-toolkit.separator_symbol', '-');

        $nameSeparator = config('password-toolkit.name_separator', true);

        $addNumbers = config('password-toolkit.add_numbers', false);

        $numbersDigits = config('password-toolkit.numbers_digits', 4);

        $numbersPosition = config('password-toolkit.numbers_position', 'end');

        $leetspeakConversion = config('password-toolkit.leetspeak_conversion', 'no');

        $name = $nameSeparator
            ? Str::replace(' ', $separator, $nameData->get('name'))
            : self::zapSpaces($nameData->get('name'));

        $adjective = Str::title(self::zapSpaces(self::getRandomAdjective($nameData)));

        if ($addNumbers) {
            $number = (string) self::getRandomNumber($numbersDigits);

            $password = match ($numbersPosition) {
                'start' => implode($separator, [$number, $name, $adjective]),

                'middle' => implode($separator, [$name, $number, $adjective]),

                default => implode($separator, [$name, $adjective, $number]),
            };
        } else {
            $password = $name . $separator . $adjective;
        }

        return match ($leetspeakConversion) {
            'basic' => self::leetspeakBasic($password),

            'advanced' => self::leetspeakAdvanced($password),

            default => $password,
        };
    }

    /**
     * Get random name data.
     *
     * @return Collection
     */
    public static function getRandomNameData(): Collection
    {
        $peopleConfig = config('password-toolkit.name_types.people', []);

        $thingsConfig = config('password-toolkit.name_types.things', []);

        $allFiles = collect(File::allFiles(__DIR__ . '/Data/Names/People'))
            ->filter(fn ($file) => $peopleConfig[pathinfo($file->getFilename(), PATHINFO_FILENAME)] ?? false)
            ->merge(
                collect(File::allFiles(__DIR__ . '/Data/Names/Things'))
                    ->filter(fn ($file) => $thingsConfig[pathinfo($file->getFilename(), PATHINFO_FILENAME)] ?? false)
            );

        if ($allFiles->isEmpty()) {
            return collect();
        }

        $content = collect(json_decode(File::get($allFiles->random()), true));

        $values = collect($content->get('values'))
            ->map(fn ($object) => collect($object)->put('file', $content->get('name')));

        return $values->isNotEmpty() ? collect($values->random()) : collect();
    }

    /**
     * Get random adjective.
     *
     * @param  Collection $nameData
     * @return string
     */
    public static function getRandomAdjective(Collection $nameData): string
    {
        $filePath = __DIR__ . '/Data/Adjectives/' . $nameData->get('file') . '_adjectives.json';

        $data = collect(json_decode(File::get($filePath), true));

        $adjectiveData = $data->filter(fn ($object) => $object['gender'] === $nameData->get('gender') || $object['gender'] === 'neutral');

        return collect($adjectiveData->random())->get('name');
    }

    /**
     * Remove all non-alphanumeric characters from a string.
     *
     * @param  string|null $string
     * @return string|null
     */
    protected static function zapSpaces(?string $string): ?string
    {
        return $string !== null
            ? preg_replace('/[^a-zA-Z0-9]/', '', $string)
            : null;
    }

    /**
     * Get random number of a given length.
     *
     * @param  int $length
     * @return int
     */
    protected static function getRandomNumber(int $length): int
    {
        $min = (int) pow(10, $length - 1);
        $max = (int) pow(10, $length) - 1;

        return random_int($min, $max);
    }

    /**
     * Format text with leetspeak basic.
     *
     * @param  string $text
     * @return string
     */
    protected static function leetspeakBasic(string $text): string
    {
        $leetMap = [
            'a' => '4',
            'b' => '8',
            'e' => '3',
            'g' => '9',
            'i' => '1',
            'l' => '1',
            'o' => '0',
            'q' => '9',
            'r' => '2',
            's' => '$',
            't' => '7',
            'z' => '2',
        ];

        return implode('', array_map(fn ($char) => $leetMap[strtolower($char)] ?? $char, str_split($text)));
    }

    /**
     * Format text with leetspeak advanced.
     *
     * @param  string $text
     * @return string
     */
    protected static function leetspeakAdvanced(string $text): string
    {
        $leetMap = [
            'a' => '4',
            'b' => '8',
            'c' => '<',
            'e' => '3',
            'f' => '|=',
            'g' => '9',
            'h' => '#',
            'i' => '1',
            'j' => '_|',
            'k' => '|<',
            'l' => '1',
            'm' => '|V|',
            'n' => '|\\|',
            'o' => '0',
            'p' => '|D',
            'q' => '9',
            'r' => '2',
            's' => '$',
            't' => '7',
            'u' => '|_|',
            'v' => '\\/',
            'w' => '\\/\\/',
            'x' => '%',
            'y' => '`/',
            'z' => '2',
        ];

        return implode('', array_map(fn ($char) => $leetMap[strtolower($char)] ?? $char, str_split($text)));
    }
}
