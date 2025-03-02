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

        if ($nameData->isNotEmpty()) {
            $separatorSymbol = config('password-toolkit.separator_symbol', '-');

            $nameSeparator = config('password-toolkit.name_separator', true);

            $name = $nameSeparator
                ? Str::replace(' ', $separatorSymbol, $nameData->get('name'))
                : self::zapSpaces($nameData->get('name'));

            $adjective = Str::title(self::zapSpaces(self::getRandomAdjective($nameData)));

            $addNumbers = config('password-toolkit.add_numbers', false);

            $numbersDigits = config('password-toolkit.numbers_digits', 4);

            $password = $name . $separatorSymbol . $adjective;

            if ($addNumbers) {
                $numbersPosition = config('password-toolkit.numbers_position', 'end');

                switch ($numbersPosition) {
                    case 'start':
                        $password = self::getRandomNumber($numbersDigits) . $separatorSymbol . $name . $separatorSymbol . $adjective;

                        break;

                    case 'middle':
                        $password = $name . $separatorSymbol . self::getRandomNumber($numbersDigits) . $separatorSymbol . $adjective;

                        break;

                    case 'end':
                        $password = $name . $separatorSymbol . $adjective . $separatorSymbol . self::getRandomNumber($numbersDigits);

                        break;
                }
            }

            $leetspeakConversion = config('password-toolkit.leetspeak_conversion', 'no');

            switch ($leetspeakConversion) {
                case 'basic':
                    $password = self::leetspeakBasic($password);

                    break;

                case 'advanced':
                    $password = self::leetspeakAdvanced($password);

                    break;
            }

            return $password;
        }

        return null;
    }

    /**
     * Get random name data.
     *
     * @return Collection
     */
    public static function getRandomNameData(): Collection
    {
        $peopleFolderPath = __DIR__ . '/Data/Names/People';

        $thingsFolderPath = __DIR__ . '/Data/Names/Things';

        $peopleFiles = collect(File::allFiles($peopleFolderPath))
            ->filter(fn ($file) => config('password-toolkit.name_types.people')[pathinfo($file->getFilename(), PATHINFO_FILENAME)]);

        $thingsFiles = collect(File::allFiles($thingsFolderPath))
            ->filter(fn ($file) => config('password-toolkit.name_types.things')[pathinfo($file->getFilename(), PATHINFO_FILENAME)]);

        $allFiles = $peopleFiles->merge($thingsFiles);

        $data = collect();

        $allFiles->each(function ($file) use ($data) {
            $content = collect(json_decode(File::get($file), true));

            collect($content->get('values'))
                ->each(function ($object) use ($data, $content) {
                    $object = collect($object)->put('file', $content->get('name'));

                    $data->push($object);
                });
        });

        return $allFiles->isNotEmpty()
            ? collect($data->random())
            : collect();
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

        $adjectiveData = $data->where(fn ($object) => $object['gender'] == $nameData->get('gender') || $object['gender'] == 'neutral');

        return collect($adjectiveData->random())->get('name');
    }

    /**
     * Remove all spaces from a string.
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
     * Get random number of a given lenght.
     *
     * @param  int  $length
     * @return void
     */
    protected static function getRandomNumber(int $length)
    {
        $min = pow(10, $length - 1);

        $max = pow(10, $length) - 1;

        return rand($min, $max);
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
