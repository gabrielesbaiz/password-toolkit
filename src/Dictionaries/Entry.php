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
        $name = isset($data['name']) && is_scalar($data['name'])
            ? trim((string) preg_replace('/\s+/u', ' ', (string) $data['name']))
            : '';

        if ($name === '') {
            throw InvalidOptionException::because("Dictionary [{$dictionary}] contains an entry with no name.");
        }

        // A name is stripped down to letters and digits on its way into a
        // password. One that has none left would silently produce a password
        // missing a whole segment, so it is rejected where it is defined.
        if (preg_match('/[\p{L}\p{N}]/u', $name) !== 1) {
            throw InvalidOptionException::because(
                "Dictionary [{$dictionary}] entry [{$name}] has no letters or digits.",
            );
        }

        $gender = isset($data['gender']) && is_scalar($data['gender'])
            ? Gender::parse((string) $data['gender'])
            : Gender::Neutral;

        return new self($name, $gender, $dictionary);
    }
}
