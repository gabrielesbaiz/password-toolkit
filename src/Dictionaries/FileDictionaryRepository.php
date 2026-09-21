<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Dictionaries;

use Gabrielesbaiz\PasswordToolkit\Contracts\DictionaryRepository;
use Gabrielesbaiz\PasswordToolkit\Exceptions\DictionaryNotFoundException;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;

/**
 * Reads dictionaries from JSON on disk, plus anything registered at runtime.
 *
 * 1.x re-scanned two directories and decoded a file on every single call to
 * generate(), so a batch of a thousand passwords did a thousand directory
 * walks. Everything here is decoded once and held for the life of the
 * process; flush() exists for tests and for configuration changes.
 */
final class FileDictionaryRepository implements DictionaryRepository
{
    /**
     * Built-in dictionaries, decoded lazily.
     *
     * @var array<string, Dictionary>|null
     */
    private ?array $builtIn = null;

    /**
     * Runtime registrations, which win over built-ins of the same key.
     *
     * @var array<string, Dictionary>
     */
    private array $registered = [];

    /**
     * Dictionaries loaded from user-configured paths, keyed by the path list
     * they came from.
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

    public function __construct(private readonly string $basePath = __DIR__.'/../Data/Names') {}

    public function all(): array
    {
        return array_merge($this->builtIn(), $this->registered);
    }

    public function enabled(Options $options): array
    {
        $pool = array_merge(
            $this->builtIn(),
            $this->fromPaths($options),
            $this->fromCustom($options),
            $this->registered,
        );

        return array_filter(
            $pool,
            static fn (Dictionary $dictionary): bool => $options->selects($dictionary->key, $dictionary->type),
        );
    }

    public function find(string $key): Dictionary
    {
        return $this->all()[$key] ?? throw DictionaryNotFoundException::key($key);
    }

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

    public function flush(): void
    {
        $this->builtIn = null;
        $this->userPaths = [];
        $this->entryCache = [];
    }

    /**
     * @return array<string, Dictionary>
     */
    private function builtIn(): array
    {
        if ($this->builtIn !== null) {
            return $this->builtIn;
        }

        $dictionaries = [];

        foreach (['People' => 'people', 'Things' => 'things'] as $directory => $type) {
            foreach ($this->jsonFiles($this->basePath.'/'.$directory) as $key => $path) {
                $dictionaries[$key] = $this->decode($key, $path, $type, builtIn: true);
            }
        }

        return $this->builtIn = $dictionaries;
    }

    /**
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
     * Every *.json in a directory, keyed by filename without the extension.
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

    private function decode(string $key, string $path, ?string $type, bool $builtIn): Dictionary
    {
        /** @var array<string, mixed> $data */
        $data = (array) json_decode((string) file_get_contents($path), true);

        if ($type !== null) {
            $data['type'] = $type;
        }

        return Dictionary::fromArray($key, $data, $builtIn);
    }
}
