<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Dictionaries;

use Gabrielesbaiz\PasswordToolkit\Enums\AdjectivePosition;
use Gabrielesbaiz\PasswordToolkit\Enums\Gender;
use Gabrielesbaiz\PasswordToolkit\Exceptions\DictionaryNotFoundException;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;
use Gabrielesbaiz\PasswordToolkit\Support\Identifier;

/**
 * Finds an adjective that agrees with a given name.
 *
 * Adjectives live under src/Data/Adjectives/{locale}/. A themed file per
 * dictionary is preferred when it exists, and every locale ships a _default
 * pool so a new locale — or a user's own dictionary — works from day one
 * without authoring 91 themed files first.
 *
 * Lookup order, first hit wins:
 *
 *   1. {locale}/{dictionary}.json
 *   2. {locale}/_default.json
 *   3. {fallback}/{dictionary}.json
 *   4. {fallback}/_default.json
 */
final class AdjectiveResolver
{
    public const DEFAULT_KEY = '_default';

    /**
     * Decoded pools, keyed by "{locale}/{key}".
     *
     * False marks a miss, so a missing file is stat-ed once per process rather
     * than on every call.
     *
     * @var array<string, array<int, Entry>|false>
     */
    private array $cache = [];

    /**
     * Word order declared by each locale's _default pack, keyed by locale.
     *
     * False marks a locale whose pack does not declare one.
     *
     * @var array<string, AdjectivePosition|false>
     */
    private array $positions = [];

    /**
     * Extra lookup roots, searched before the built-in one.
     *
     * @var array<int, string>
     */
    private array $paths = [];

    /**
     * Create a new adjective resolver instance.
     */
    public function __construct(private readonly string $basePath = __DIR__.'/../Data/Adjectives') {}

    /**
     * Set the extra lookup roots, searched before the built-in one.
     *
     * @param  array<int, string>  $paths
     */
    public function usingPaths(array $paths): self
    {
        $this->paths = $paths;

        return $this;
    }

    /**
     * Flush the cached adjective pools and declared word orders.
     */
    public function flush(): void
    {
        $this->cache = [];
        $this->positions = [];
    }

    /**
     * Determine where this locale puts its adjective.
     *
     * Read from the locale's _default pack rather than from configuration,
     * because it is a fact about the language: Italian says "Goldrake Mitico",
     * English says "Legendary Goldrake". A locale that does not declare one
     * falls back to the fallback locale, then to After.
     */
    public function positionFor(Options $options): AdjectivePosition
    {
        foreach ([$options->resolvedLocale(), $options->fallbackLocale] as $locale) {
            $position = $this->declaredPosition($locale);

            if ($position instanceof AdjectivePosition) {
                return $position;
            }
        }

        return AdjectivePosition::After;
    }

    /**
     * Get an adjective agreeing with the given entry.
     *
     * @throws DictionaryNotFoundException when no pool with an agreeing
     *                                     adjective resolves in either locale
     */
    public function for(Entry $entry, Options $options): Entry
    {
        return $this->many($entry, $options, 1)[0];
    }

    /**
     * Get up to $count distinct adjectives, all agreeing with the given entry.
     *
     * Distinct by word, not by array position: two entries spelling the same
     * adjective would produce "Mitico-Mitico", which reads as a mistake rather
     * than as a phrase. A pool too small to supply them all returns what it
     * has — fewer words is a smaller password, a thrown exception is no
     * password at all — and the caller reports the entropy it actually got.
     *
     * @return array<int, Entry> at least one, never more than $count
     *
     * @throws DictionaryNotFoundException when no pool with an agreeing
     *                                     adjective resolves in either locale
     */
    public function many(Entry $entry, Options $options, int $count): array
    {
        $locale = $options->resolvedLocale();
        $fallback = $options->fallbackLocale;

        foreach ($this->candidates($entry->dictionary, $locale, $fallback) as [$candidateLocale, $key]) {
            $pool = $this->load($candidateLocale, $key);

            if ($pool === []) {
                continue;
            }

            $agreeing = array_values(array_filter(
                $pool,
                static fn (Entry $adjective): bool => $adjective->gender->agreesWith($entry->gender),
            ));

            if ($agreeing === []) {
                continue;
            }

            return $this->draw($agreeing, $count);
        }

        throw DictionaryNotFoundException::adjectives($entry->dictionary, $locale, $fallback);
    }

    /**
     * Get how many adjectives are available for the given dictionary.
     *
     * Used by the entropy model, and ungendered because the gender split is not
     * knowable up front.
     */
    public function poolSize(string $dictionary, Options $options): int
    {
        foreach ($this->candidates($dictionary, $options->resolvedLocale(), $options->fallbackLocale) as [$locale, $key]) {
            $pool = $this->load($locale, $key);

            if ($pool !== []) {
                return count($pool);
            }
        }

        return 0;
    }

    /**
     * Sample without replacement.
     *
     * @param  array<int, Entry>  $pool  non-empty
     * @return array<int, Entry>
     */
    private function draw(array $pool, int $count): array
    {
        $drawn = [];
        $taken = [];

        while (count($drawn) < $count && $pool !== []) {
            $index = random_int(0, count($pool) - 1);
            $candidate = $pool[$index];

            $drawn[] = $candidate;
            $taken[$candidate->name] = true;

            // Everything spelled like what was just drawn goes out of the hat,
            // which is also what makes the log2(A) + log2(A-1) figure the
            // entropy model reports true of the draw.
            $pool = array_values(array_filter(
                $pool,
                static fn (Entry $adjective): bool => ! isset($taken[$adjective->name]),
            ));
        }

        return $drawn;
    }

    /**
     * Get the word order the locale's _default pack declares.
     *
     * Returns false when the pack declares none.
     */
    private function declaredPosition(string $locale): AdjectivePosition|false
    {
        if (array_key_exists($locale, $this->positions)) {
            return $this->positions[$locale];
        }

        Identifier::locale($locale);

        foreach ([...$this->paths, $this->basePath] as $root) {
            $path = rtrim($root, '/').'/'.$locale.'/'.self::DEFAULT_KEY.'.json';

            if (! is_file($path)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($path), true);
            $declared = is_array($decoded) ? ($decoded['adjective_position'] ?? null) : null;

            if (is_string($declared)) {
                return $this->positions[$locale] = AdjectivePosition::parse($declared);
            }
        }

        return $this->positions[$locale] = false;
    }

    /**
     * Get the locale and pool key pairs to try, in lookup order.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function candidates(string $dictionary, string $locale, string $fallback): array
    {
        $candidates = [
            [$locale, $dictionary],
            [$locale, self::DEFAULT_KEY],
        ];

        if ($fallback !== $locale) {
            $candidates[] = [$fallback, $dictionary];
            $candidates[] = [$fallback, self::DEFAULT_KEY];
        }

        return $candidates;
    }

    /**
     * Load and cache the adjective pool for the given locale and key.
     *
     * @return array<int, Entry>
     */
    private function load(string $locale, string $key): array
    {
        // Defence in depth: these are validated on the way in, but this is the
        // method that concatenates them into a path, so it checks again.
        Identifier::locale($locale);
        Identifier::key($key);

        $cacheKey = $locale.'/'.$key;

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey] ?: [];
        }

        foreach ([...$this->paths, $this->basePath] as $root) {
            $path = rtrim($root, '/').'/'.$locale.'/'.$key.'.json';

            if (! is_file($path)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($path), true);

            // Accept both the 2.0 wrapper object and a bare array of entries,
            // so a hand-written user file can be as short as possible.
            $values = is_array($decoded) && isset($decoded['values']) && is_array($decoded['values'])
                ? $decoded['values']
                : (is_array($decoded) ? $decoded : []);

            $entries = [];

            foreach ($values as $value) {
                if (is_string($value)) {
                    $value = ['name' => $value, 'gender' => Gender::Neutral->value];
                }

                if (is_array($value)) {
                    $entries[] = Entry::fromArray($value, $key);
                }
            }

            return $this->cache[$cacheKey] = $entries;
        }

        $this->cache[$cacheKey] = false;

        return [];
    }
}
