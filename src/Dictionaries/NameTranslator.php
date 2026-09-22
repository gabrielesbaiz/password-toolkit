<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Dictionaries;

use Gabrielesbaiz\PasswordToolkit\Generator\Options;
use Gabrielesbaiz\PasswordToolkit\Support\Identifier;

/**
 * Renders a dictionary entry's name in the active locale.
 *
 * The shipped name data is Italian, and most of it is proper nouns that do not
 * translate: Barolo is Barolo in every language, and so are Etna, Ferrari and
 * every pasta shape. A few dictionaries are different — they hold Italian dub
 * or exonym forms of something that has a real name elsewhere, so Topolino is
 * Mickey Mouse, Cartesio is Descartes and Cervino is the Matterhorn.
 *
 * Translations are therefore additive and sparse. A locale file lists only the
 * entries that actually differ; anything absent keeps the base name, and a
 * dictionary with no file for the locale is left alone entirely. That is what
 * makes it safe to translate a handful of dictionaries well rather than all of
 * them badly.
 *
 * Files live at Data/Names/{locale}/{key}.json, shaped as a simple map:
 *
 *     { "key": "disney_characters", "locale": "en",
 *       "values": { "Topolino": "Mickey Mouse" } }
 */
final class NameTranslator
{
    /**
     * Decoded maps, keyed by "{locale}/{key}". An empty array marks a miss, so
     * a dictionary with no translations is stat-ed once per process.
     *
     * @var array<string, array<string, string>>
     */
    private array $cache = [];

    /**
     * Extra lookup roots, searched before the built-in one.
     *
     * @var array<int, string>
     */
    private array $paths = [];

    public function __construct(private readonly string $basePath = __DIR__.'/../Data/Names') {}

    /**
     * @param  array<int, string>  $paths
     */
    public function usingPaths(array $paths): self
    {
        // A user dictionary keeps its names at {path}/{key}.json and its
        // adjectives at {path}/{locale}/{key}.json, so name translations get
        // their own subdirectory to avoid colliding with the adjectives.
        $this->paths = array_map(static fn (string $path): string => rtrim($path, '/').'/names', $paths);

        return $this;
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * The entry, with its name rendered in the active locale.
     *
     * Returns the same entry untouched when nothing translates it, so the
     * common case allocates nothing.
     */
    public function translate(Entry $entry, Options $options): Entry
    {
        $locale = $options->resolvedLocale();

        if ($locale === $options->fallbackLocale) {
            return $entry;
        }

        $translated = $this->map($locale, $entry->dictionary)[$entry->name] ?? null;

        return $translated === null
            ? $entry
            : new Entry($translated, $entry->gender, $entry->dictionary);
    }

    /**
     * Whether a dictionary has any translations for a locale. Used by the
     * console listing so a maintainer can see coverage at a glance.
     */
    public function coverage(string $dictionary, string $locale): int
    {
        return count($this->map($locale, $dictionary));
    }

    /**
     * @return array<string, string>
     */
    private function map(string $locale, string $dictionary): array
    {
        Identifier::locale($locale);
        Identifier::key($dictionary);

        $cacheKey = $locale.'/'.$dictionary;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        foreach ([...$this->paths, $this->basePath] as $root) {
            $path = rtrim($root, '/').'/'.$locale.'/'.$dictionary.'.json';

            if (! is_file($path)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($path), true);
            $values = is_array($decoded) && isset($decoded['values']) && is_array($decoded['values'])
                ? $decoded['values']
                : (is_array($decoded) ? $decoded : []);

            $map = [];

            foreach ($values as $from => $to) {
                if (is_string($from) && is_string($to) && $to !== '') {
                    $map[$from] = $to;
                }
            }

            return $this->cache[$cacheKey] = $map;
        }

        return $this->cache[$cacheKey] = [];
    }
}
