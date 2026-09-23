<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Contracts;

use Gabrielesbaiz\PasswordToolkit\Dictionaries\Dictionary;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\Entry;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;

/**
 * Where name dictionaries come from.
 *
 * The built-in implementation reads JSON off disk and caches it. Swapping this
 * binding is how you back the dictionaries with a database, a remote service,
 * or a fixture in a test.
 */
interface DictionaryRepository
{
    /**
     * Get every dictionary this repository knows about, keyed by dictionary key.
     *
     * @return array<string, Dictionary>
     */
    public function all(): array;

    /**
     * Get the dictionaries the given options select, keyed by dictionary key.
     *
     * @return array<string, Dictionary>
     */
    public function enabled(Options $options): array;

    /**
     * Get the dictionary registered under the given key.
     */
    public function find(string $key): Dictionary;

    /**
     * Get every entry across the selected dictionaries, flattened.
     *
     * Flattening is what makes selection uniform per name rather than per
     * file, so a 20-entry dictionary no longer carries the same weight as a
     * 200-entry one.
     *
     * @return array<int, Entry>
     */
    public function entries(Options $options): array;

    /**
     * Register a dictionary at runtime.
     *
     * @param  array<int, array{name: string, gender?: string}>  $values
     */
    public function register(string $key, array $values, string $type = 'things', ?string $locale = null): void;

    /**
     * Flush any cached dictionaries and entry pools.
     */
    public function flush(): void;
}
