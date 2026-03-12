<?php

namespace Gabrielesbaiz\PasswordToolkit;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class PasswordToolkit
{
    /**
     * Generate password.
     *
     * @return string|null
     */
    public static function generate(): ?string
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
