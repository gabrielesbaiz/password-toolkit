<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Dictionaries;

use Gabrielesbaiz\PasswordToolkit\Enums\DictionaryGroup;
use Gabrielesbaiz\PasswordToolkit\Enums\Reach;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;
use Gabrielesbaiz\PasswordToolkit\Support\Identifier;

/**
 * A named collection of entries: one JSON file, one config block, or one
 * runtime registration.
 */
final readonly class Dictionary
{
    public const TYPES = ['people', 'things'];

    /**
     * @param  array<int, Entry>  $entries
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public string $key,
        public string $type,
        public array $entries,
        public ?string $locale = null,
        public bool $builtIn = false,
        public ?DictionaryGroup $group = null,
        public array $tags = [],
        public ?string $icon = null,
        public Reach $reach = Reach::Global,
    ) {}

    /**
     * Build from the on-disk / in-config shape.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $key, array $data, bool $builtIn = false): self
    {
        // The key becomes part of the adjective lookup path.
        Identifier::key($key);

        $type = isset($data['type']) && is_string($data['type']) ? strtolower($data['type']) : 'things';

        if (! in_array($type, self::TYPES, true)) {
            throw InvalidOptionException::because(
                "Dictionary [{$key}] has type [{$type}]. Expected one of: ".implode(', ', self::TYPES).'.',
            );
        }

        // The language the names themselves are in. A dictionary about Italian
        // wines is Italian in every locale; one about Harry Potter is English.
        $locale = isset($data['locale']) && is_string($data['locale']) ? $data['locale'] : null;

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

            $entries[] = Entry::fromArray($value, $key, $locale);
        }

        return new self(
            key: $key,
            type: $type,
            entries: $entries,
            locale: $locale,
            builtIn: $builtIn,
            group: isset($data['group']) && is_string($data['group'])
                ? DictionaryGroup::parse($data['group'])
                : null,
            tags: array_values(array_filter(
                is_array($data['tags'] ?? null) ? $data['tags'] : [],
                'is_string',
            )),
            icon: isset($data['icon']) && is_string($data['icon']) ? $data['icon'] : null,
            // A dictionary that does not say is assumed to travel: a user's own
            // dictionary is by definition meaningful to its own users.
            reach: isset($data['reach']) && is_string($data['reach'])
                ? Reach::parse($data['reach'])
                : Reach::Global,
        );
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function hasTag(string $tag): bool
    {
        return in_array(strtolower($tag), array_map('strtolower', $this->tags), true);
    }

    /**
     * Translated display name, falling back to the key made readable.
     */
    public function label(): string
    {
        $key = 'password-toolkit::dictionaries.labels.'.$this->key;
        $translated = trans($key);

        return is_string($translated) && $translated !== $key
            ? $translated
            : ucwords(str_replace('_', ' ', $this->key));
    }

    /**
     * Translated one-line description, or null when none is written yet.
     */
    public function description(): ?string
    {
        $key = 'password-toolkit::dictionaries.descriptions.'.$this->key;
        $translated = trans($key);

        return is_string($translated) && $translated !== $key ? $translated : null;
    }

    /**
     * Everything a picker needs to render this dictionary as one row.
     *
     * @return array{key: string, label: string, description: string|null, icon: string|null, type: string, group: string|null, group_label: string|null, tags: array<int, string>, reach: string, reach_label: string, locale: string|null, count: int, built_in: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label(),
            'description' => $this->description(),
            'icon' => $this->icon ?? $this->group?->icon(),
            'type' => $this->type,
            'group' => $this->group?->value,
            'group_label' => $this->group?->label(),
            'tags' => $this->tags,
            'reach' => $this->reach->value,
            'reach_label' => $this->reach->label(),
            'locale' => $this->locale,
            'count' => $this->count(),
            'built_in' => $this->builtIn,
        ];
    }
}
