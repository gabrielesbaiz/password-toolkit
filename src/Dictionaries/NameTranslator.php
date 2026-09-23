<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Dictionaries;

use Gabrielesbaiz\PasswordToolkit\Generator\Options;
use Gabrielesbaiz\PasswordToolkit\Support\Identifier;

/**
 * Renders a dictionary entry's name in the active locale.
 *
 * Every dictionary declares the language its names are written in, because
 * there is no single right answer: a dictionary of Italian wines is Italian in
 * every locale — Barolo is Barolo, and so are Etna, Cacciucco and every pasta
 * shape — while one about Harry Potter is English, and Italian is the dub.
 * Storing Albus Silente as canonical and Dumbledore as a translation would be
 * backwards for an internationally published package.
 *
 * Translations are therefore additive and sparse. A locale file lists only the
 * entries that actually differ; anything absent keeps the base name, and a
 * dictionary with no file for the locale is left alone entirely. That is what
 * makes it safe to translate a handful of dictionaries well rather than all of
 * them badly.
 *
 * Lookup order, first hit wins:
 *
 *   1. {locale}/{key}.json
 *   2. {fallback}/{key}.json   — English exonyms for a locale with no pack
 *   3. the base name
 *
 * Files live at Data/Names/{locale}/{key}.json, shaped as a simple map:
 *
 *     { "key": "harry_potter", "locale": "it",
 *       "values": { "Albus Dumbledore": "Albus Silente" } }
 */
final class NameTranslator
{
    /**
     * Decoded maps, keyed by "{locale}/{key}".
     *
     * An empty array marks a miss, so a dictionary with no translations is
     * stat-ed once per process.
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

    /**
     * Create a new name translator instance.
     */
    public function __construct(private readonly string $basePath = __DIR__.'/../Data/Names') {}

    /**
     * Set the extra lookup roots, searched before the built-in one.
     *
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

    /**
     * Flush the cached translation maps.
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * Get the entry, with its name rendered in the active locale.
     *
     * Returns the same entry untouched when nothing translates it, so the
     * common case allocates nothing.
     */
    public function translate(Entry $entry, Options $options): Entry
    {
        $locale = $options->resolvedLocale();

        // The dictionary is already in this language; there is nothing to look
        // up, and no file should exist.
        if ($entry->sourceLocale !== null && $locale === $entry->sourceLocale) {
            return $entry;
        }

        foreach ([$locale, $options->fallbackLocale] as $candidate) {
            $translated = $this->map($candidate, $entry->dictionary)[$entry->name] ?? null;

            if ($translated !== null) {
                return new Entry($translated, $entry->gender, $entry->dictionary, $entry->sourceLocale);
            }
        }

        return $entry;
    }

    /**
     * Count the translations a dictionary has for the given locale.
     *
     * Used by the console listing so a maintainer can see coverage at a glance.
     */
    public function coverage(string $dictionary, string $locale): int
    {
        return count($this->map($locale, $dictionary));
    }

    /**
     * Load and cache the translation map for a locale and dictionary.
     *
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
