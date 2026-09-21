<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Dictionaries;

use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * A named collection of entries: one JSON file, one config block, or one
 * runtime registration.
 */
final readonly class Dictionary
{
    public const TYPES = ['people', 'things'];

    /**
     * @param  array<int, Entry>  $entries
     */
    public function __construct(
        public string $key,
        public string $type,
        public array $entries,
        public ?string $locale = null,
        public bool $builtIn = false,
    ) {}

    /**
     * Build from the on-disk / in-config shape.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $key, array $data, bool $builtIn = false): self
    {
        $type = isset($data['type']) && is_string($data['type']) ? strtolower($data['type']) : 'things';

        if (! in_array($type, self::TYPES, true)) {
            throw InvalidOptionException::because(
                "Dictionary [{$key}] has type [{$type}]. Expected one of: ".implode(', ', self::TYPES).'.',
            );
        }

        $values = $data['values'] ?? [];

        if (! is_array($values) || $values === []) {
            throw InvalidOptionException::because("Dictionary [{$key}] has no values.");
        }

        $entries = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $value = ['name' => $value];
            }

            if (! is_array($value)) {
                throw InvalidOptionException::because("Dictionary [{$key}] contains a malformed entry.");
            }

            $entries[] = Entry::fromArray($value, $key);
        }

        $locale = isset($data['locale']) && is_string($data['locale']) ? $data['locale'] : null;

        return new self($key, $type, $entries, $locale, $builtIn);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return array{key: string, type: string, locale: string|null, count: int, built_in: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'locale' => $this->locale,
            'count' => $this->count(),
            'built_in' => $this->builtIn,
        ];
    }
}
