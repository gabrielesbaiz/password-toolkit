<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Enums;

use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * Where the numeric segment lands in the assembled password.
 */
enum NumbersPosition: string
{
    case Start = 'start';

    case Middle = 'middle';

    case End = 'end';

    /**
     * Parse the given value into a numbers position.
     *
     * @throws InvalidOptionException
     */
    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw InvalidOptionException::because("Unknown numbers position [{$value}]. Expected start, middle or end.");
    }

    /**
     * Order the three segments according to this position.
     *
     * The two arguments are the words in reading order, whatever the locale
     * decided that order is.
     *
     * @return array<int, string>
     */
    public function arrange(string $first, string $second, string $number): array
    {
        return $this->place([$first, $second], $number, 1);
    }

    /**
     * Slot the numeric segment into any number of words.
     *
     * $boundary is where the name meets the adjectives, which is where 'middle'
     * puts the digits. With three words that keeps the two adjectives adjacent
     * — "Brave-Mighty-4271-Goldrake", not "Brave-4271-Mighty-Goldrake" — because
     * they agree with each other and with the name, and splitting the pair is
     * what makes the password stop reading as a phrase.
     *
     * @param  array<int, string>  $words  in reading order
     * @return array<int, string>
     */
    public function place(array $words, string $number, int $boundary): array
    {
        return match ($this) {
            self::Start => [$number, ...$words],
            self::End => [...$words, $number],
            self::Middle => [
                ...array_slice($words, 0, $boundary),
                $number,
                ...array_slice($words, $boundary),
            ],
        };
    }
}
