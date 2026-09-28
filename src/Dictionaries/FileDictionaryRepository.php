<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Dictionaries;

use Gabrielesbaiz\PasswordToolkit\Contracts\DictionaryRepository;
use Gabrielesbaiz\PasswordToolkit\Exceptions\DictionaryNotFoundException;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;

/**
 * Reads dictionaries from JSON on disk, plus anything registered at runtime.
 *
 * 1.x re-scanned two directories and decoded a file on every single call to
 * generate(), so a batch of a thousand passwords did a thousand directory
 * walks. Everything here is decoded once and held for the life of the
 * process; flush() exists for tests and for configuration changes.
 *
 * Decoding is per dictionary rather than wholesale. An application that
 * enables one dictionary pays for one file, not for all 200: the index is a
 * glob, and the type a dictionary would filter on is the directory it sits
 * in, so both questions are answered without opening anything.
 */
final class FileDictionaryRepository implements DictionaryRepository
{
    /**
     * Where each built-in dictionary lives, and the type its directory implies.
     *
     * Built from a directory listing alone, so having it costs no decoding.
     *
     * @var array<string, array{path: string, type: string}>|null
     */
    private ?array $index = null;

    /**
     * Built-in dictionaries, decoded one at a time on first use.
     *
     * @var array<string, Dictionary>
     */
    private array $decoded = [];

    /**
     * Runtime registrations, which win over built-ins of the same key.
     *
     * @var array<string, Dictionary>
     */
    private array $registered = [];

    /**
     * Dictionaries loaded from user-configured paths.
     *
     * Keyed by the path list they came from.
     *
     * @var array<string, array<string, Dictionary>>
     */
    private array $userPaths = [];

    /**
     * Flattened entry pools, keyed by option fingerprint.
     *
     * @var array<string, array<int, Entry>>
     */
    private array $entryCache = [];

    /**
     * Create a new file dictionary repository instance.
     */
    public function __construct(private readonly string $basePath = __DIR__.'/../Data/Names') {}

    /**
     * Get every dictionary this repository knows about, keyed by dictionary key.
     *
     * @return array<string, Dictionary>
     */
    public function all(): array
    {
        return array_merge($this->builtIn(), $this->registered);
    }

    /**
     * Get the dictionaries the given options select, keyed by dictionary key.
     *
     * @return array<string, Dictionary>
     */
    public function enabled(Options $options): array
    {
        $pool = array_merge(
            $this->builtInCandidates($options),
            $this->fromPaths($options),
            $this->fromCustom($options),
            $this->registered,
        );

        return array_filter($pool, $options->selects(...));
    }

    /**
     * Get the dictionary registered under the given key.
     */
    public function find(string $key): Dictionary
    {
        // Registered dictionaries win over built-ins of the same key, which is
        // why they are consulted first rather than merged into one array.
        if (isset($this->registered[$key])) {
            return $this->registered[$key];
        }

        if (isset($this->builtInIndex()[$key])) {
            return $this->builtInDictionary($key);
        }

        throw DictionaryNotFoundException::key($key);
    }

    /**
     * Get every entry across the selected dictionaries, flattened.
     *
     * @return array<int, Entry>
     */
    public function entries(Options $options): array
    {
        $fingerprint = $options->selectionFingerprint();

        if (isset($this->entryCache[$fingerprint])) {
            return $this->entryCache[$fingerprint];
        }

        $entries = [];

        foreach ($this->enabled($options) as $dictionary) {
            foreach ($dictionary->entries as $entry) {
                $entries[] = $entry;
            }
        }

        return $this->entryCache[$fingerprint] = $entries;
    }

    /**
     * Register a dictionary at runtime.
     *
     * @param  array<int, array{name: string, gender?: string}>  $values
     */
    public function register(string $key, array $values, string $type = 'things', ?string $locale = null): void
    {
        $this->registered[$key] = Dictionary::fromArray($key, [
            'type' => $type,
            'locale' => $locale,
            'values' => $values,
        ]);

        // A new dictionary changes every flattened pool it could belong to.
        $this->entryCache = [];
    }

    /**
     * Flush the decoded dictionaries and the flattened entry pools.
     */
    public function flush(): void
    {
        $this->index = null;
        $this->decoded = [];
        $this->userPaths = [];
        $this->entryCache = [];
    }

    /**
     * Get every built-in dictionary, decoding whatever is not decoded yet.
     *
     * @return array<string, Dictionary>
     */
    private function builtIn(): array
    {
        $dictionaries = [];

        foreach (array_keys($this->builtInIndex()) as $key) {
            $dictionaries[$key] = $this->builtInDictionary($key);
        }

        return $dictionaries;
    }

    /**
     * Get only the built-in dictionaries these options could possibly select.
     *
     * Narrowing here rather than in selects() is the whole point: selects()
     * needs a Dictionary, and building one means decoding the file.
     *
     * @return array<string, Dictionary>
     */
    private function builtInCandidates(Options $options): array
    {
        $index = $this->builtInIndex();
        $dictionaries = [];

        // Named keys select outright, whatever the file turns out to contain,
        // so nothing else needs opening.
        if (is_array($options->enabled)) {
            foreach ($options->enabled as $key) {
                if (isset($index[$key])) {
                    $dictionaries[$key] = $this->builtInDictionary($key);
                }
            }

            return $dictionaries;
        }

        foreach ($index as $key => $entry) {
            // Type is the directory the file sits in and except is a list of
            // keys: both are answerable from the index alone.
            if (! in_array($entry['type'], $options->types, true)) {
                continue;
            }

            if (in_array($key, $options->except, true)) {
                continue;
            }

            $dictionaries[$key] = $this->builtInDictionary($key);
        }

        return $dictionaries;
    }

    /**
     * Get where every built-in dictionary lives, keyed by dictionary key.
     *
     * @return array<string, array{path: string, type: string}>
     */
    private function builtInIndex(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $index = [];

        foreach (['People' => 'people', 'Things' => 'things'] as $directory => $type) {
            foreach ($this->jsonFiles($this->basePath.'/'.$directory) as $key => $path) {
                $index[$key] = ['path' => $path, 'type' => $type];
            }
        }

        return $this->index = $index;
    }

    /**
     * Get one built-in dictionary, decoding it on first use.
     */
    private function builtInDictionary(string $key): Dictionary
    {
        if (isset($this->decoded[$key])) {
            return $this->decoded[$key];
        }

        $entry = $this->builtInIndex()[$key];

        return $this->decoded[$key] = $this->decode($key, $entry['path'], $entry['type'], builtIn: true);
    }

    /**
     * Get the dictionaries found in the user-configured paths.
     *
     * @return array<string, Dictionary>
     */
    private function fromPaths(Options $options): array
    {
        if ($options->paths === []) {
            return [];
        }

        $cacheKey = md5(serialize($options->paths));

        if (isset($this->userPaths[$cacheKey])) {
            return $this->userPaths[$cacheKey];
        }

        $dictionaries = [];

        foreach ($options->paths as $path) {
            foreach ($this->jsonFiles($path) as $key => $file) {
                // Type comes from the file itself here; a user directory is
                // flat, with no People/Things convention to lean on.
                $dictionaries[$key] = $this->decode($key, $file, null, builtIn: false);
            }
        }

        return $this->userPaths[$cacheKey] = $dictionaries;
    }

    /**
     * Get the dictionaries declared inline in configuration.
     *
     * @return array<string, Dictionary>
     */
    private function fromCustom(Options $options): array
    {
        $dictionaries = [];

        foreach ($options->custom as $key => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $dictionaries[(string) $key] = Dictionary::fromArray((string) $key, $definition);
        }

        return $dictionaries;
    }

    /**
     * Get every *.json in a directory, keyed by filename without the extension.
     *
     * @return array<string, string>
     */
    private function jsonFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = glob(rtrim($directory, '/').'/*.json') ?: [];
        $found = [];

        foreach ($files as $file) {
            $found[pathinfo($file, PATHINFO_FILENAME)] = $file;
        }

        ksort($found);

        return $found;
    }

    /**
     * Decode a single dictionary from its JSON file.
     */
    private function decode(string $key, string $path, ?string $type, bool $builtIn): Dictionary
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            throw InvalidOptionException::because("Dictionary [{$key}] at {$path} could not be read.");
        }

        $data = json_decode($raw, true);

        // Without this a syntax error in a user's dictionary surfaced as
        // "has no values", which sends them looking in the wrong place.
        if (! is_array($data)) {
            throw InvalidOptionException::because(
                "Dictionary [{$key}] at {$path} is not valid JSON: ".json_last_error_msg().'.',
            );
        }

        if ($type !== null) {
            $data['type'] = $type;
        }

        /** @var array<string, mixed> $data */
        return Dictionary::fromArray($key, $data, $builtIn);
    }
}
