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

    public static function parse(string $value): self
    {
        return self::tryFrom(strtolower(trim($value)))
            ?? throw InvalidOptionException::because("Unknown numbers position [{$value}]. Expected start, middle or end.");
    }

    /**
     * Order the three segments according to this position.
     *
     * @return array<int, string>
     */
    public function arrange(string $name, string $adjective, string $number): array
    {
        return match ($this) {
            self::Start => [$number, $name, $adjective],
            self::Middle => [$name, $number, $adjective],
            self::End => [$name, $adjective, $number],
        };
    }
}
