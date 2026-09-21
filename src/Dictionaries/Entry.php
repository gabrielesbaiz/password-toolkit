<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Dictionaries;

use Gabrielesbaiz\PasswordToolkit\Enums\Gender;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * One name, and the dictionary it came from.
 *
 * The dictionary key travels with the entry because the adjective that will
 * modify it is resolved from that key.
 */
final readonly class Entry
{
    public function __construct(
        public string $name,
        public Gender $gender,
        public string $dictionary,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $dictionary): self
    {
        $name = isset($data['name']) && is_scalar($data['name']) ? trim((string) $data['name']) : '';

        if ($name === '') {
            throw InvalidOptionException::because("Dictionary [{$dictionary}] contains an entry with no name.");
        }

        $gender = isset($data['gender']) && is_scalar($data['gender'])
            ? Gender::parse((string) $data['gender'])
            : Gender::Neutral;

        return new self($name, $gender, $dictionary);
    }
}
