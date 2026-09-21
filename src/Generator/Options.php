<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Generator;

use Gabrielesbaiz\PasswordToolkit\Enums\AdjectivePosition;
use Gabrielesbaiz\PasswordToolkit\Enums\Leetspeak;
use Gabrielesbaiz\PasswordToolkit\Enums\NumbersPosition;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;

/**
 * Everything the generator needs for one password, resolved once.
 *
 * 1.x called config() eight times inside the generation loop. Resolving to an
 * immutable value object instead means a batch reads configuration once, the
 * builder can override any field without touching global state, and the
 * repository can cache against a stable fingerprint.
 */
final readonly class Options
{
    /**
     * @param  array<int, string>|string  $enabled  '*' for every dictionary
     * @param  array<int, string>  $except
     * @param  array<int, string>  $types
     * @param  array<int, string>  $paths
     * @param  array<string, array<string, mixed>>  $custom
     */
    public function __construct(
        public array|string $enabled = '*',
        public array $except = [],
        public array $types = ['people', 'things'],
        public array $paths = [],
        public array $custom = [],
        public ?string $locale = null,
        public string $fallbackLocale = 'it',
        public ?string $separator = '-',
        public bool $nameSeparator = true,
        public bool $addNumbers = true,
        public int $numbersDigits = 4,
        public NumbersPosition $numbersPosition = NumbersPosition::End,
        public ?AdjectivePosition $adjectivePosition = null,
        public Leetspeak $leetspeak = Leetspeak::None,
        public float $guessesPerSecond = 1e10,
    ) {
        if ($this->numbersDigits < 1 || $this->numbersDigits > 18) {
            throw InvalidOptionException::because(
                "numbers_digits must be between 1 and 18, got {$this->numbersDigits}.",
            );
        }

        foreach ($this->types as $type) {
            if (! in_array($type, ['people', 'things'], true)) {
                throw InvalidOptionException::because(
                    "Unknown dictionary type [{$type}]. Expected people or things.",
                );
            }
        }
    }

    /**
     * Build from the published config, translating the 1.x shape if present.
     */
    public static function fromConfig(): self
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('password-toolkit', []);

        ['enabled' => $enabled, 'except' => $except] = self::resolveSelection($config);

        $dictionaries = is_array($config['dictionaries'] ?? null) ? $config['dictionaries'] : [];

        $separator = array_key_exists('separator_symbol', $config) ? $config['separator_symbol'] : '-';

        return new self(
            enabled: $enabled,
            except: $except,
            types: self::stringList($dictionaries['types'] ?? ['people', 'things']),
            paths: self::stringList($dictionaries['paths'] ?? []),
            custom: is_array($dictionaries['custom'] ?? null) ? $dictionaries['custom'] : [],
            locale: is_string($config['locale'] ?? null) ? $config['locale'] : null,
            fallbackLocale: is_string($config['fallback_locale'] ?? null) ? $config['fallback_locale'] : 'it',
            separator: is_string($separator) ? $separator : null,
            nameSeparator: (bool) ($config['name_separator'] ?? true),
            addNumbers: (bool) ($config['add_numbers'] ?? true),
            numbersDigits: (int) ($config['numbers_digits'] ?? 4),
            numbersPosition: NumbersPosition::parse((string) ($config['numbers_position'] ?? 'end')),
            adjectivePosition: is_string($config['adjective_position'] ?? null)
                ? AdjectivePosition::parse($config['adjective_position'])
                : null,
            leetspeak: Leetspeak::parse((string) ($config['leetspeak_conversion'] ?? 'none')),
            guessesPerSecond: (float) (config('password-toolkit.strength.guesses_per_second') ?? 1e10),
        );
    }

    /**
     * The locale adjectives are looked up in.
     */
    public function resolvedLocale(): string
    {
        if (is_string($this->locale) && $this->locale !== '') {
            return $this->locale;
        }

        $appLocale = app()->getLocale();

        return $appLocale !== '' ? $appLocale : $this->fallbackLocale;
    }

    /**
     * Whether a dictionary key is selected by this option set.
     */
    public function selects(string $key, string $type): bool
    {
        if (! in_array($type, $this->types, true)) {
            return false;
        }

        if (in_array($key, $this->except, true)) {
            return false;
        }

        return $this->enabled === '*' || in_array($key, (array) $this->enabled, true);
    }

    /**
     * A stable key for caching the resolved dictionary set.
     */
    public function selectionFingerprint(): string
    {
        return md5(serialize([
            $this->enabled,
            $this->except,
            $this->types,
            $this->paths,
            array_keys($this->custom),
        ]));
    }

    /**
     * Clone with the named properties replaced.
     *
     * Named arguments keep this readable at the call site and let the builder
     * stay a thin wrapper.
     */
    public function with(mixed ...$changes): self
    {
        /** @var array<string, mixed> $changes */
        return new self(
            enabled: $changes['enabled'] ?? $this->enabled,
            except: $changes['except'] ?? $this->except,
            types: $changes['types'] ?? $this->types,
            paths: $changes['paths'] ?? $this->paths,
            custom: $changes['custom'] ?? $this->custom,
            locale: array_key_exists('locale', $changes) ? $changes['locale'] : $this->locale,
            fallbackLocale: $changes['fallbackLocale'] ?? $this->fallbackLocale,
            separator: array_key_exists('separator', $changes) ? $changes['separator'] : $this->separator,
            nameSeparator: $changes['nameSeparator'] ?? $this->nameSeparator,
            addNumbers: $changes['addNumbers'] ?? $this->addNumbers,
            numbersDigits: $changes['numbersDigits'] ?? $this->numbersDigits,
            numbersPosition: $changes['numbersPosition'] ?? $this->numbersPosition,
            adjectivePosition: array_key_exists('adjectivePosition', $changes)
                ? $changes['adjectivePosition']
                : $this->adjectivePosition,
            leetspeak: $changes['leetspeak'] ?? $this->leetspeak,
            guessesPerSecond: $changes['guessesPerSecond'] ?? $this->guessesPerSecond,
        );
    }

    /**
     * Resolve which dictionaries are selected, honouring the 1.x `name_types`
     * block if the application has not re-published its config.
     *
     * @param  array<string, mixed>  $config
     * @return array{enabled: array<int, string>|string, except: array<int, string>}
     */
    private static function resolveSelection(array $config): array
    {
        $dictionaries = is_array($config['dictionaries'] ?? null) ? $config['dictionaries'] : [];

        if (isset($dictionaries['enabled']) || ! isset($config['name_types'])) {
            $enabled = $dictionaries['enabled'] ?? '*';

            return [
                'enabled' => is_array($enabled) ? self::stringList($enabled) : '*',
                'except' => self::stringList($dictionaries['except'] ?? []),
            ];
        }

        // Legacy shape: name_types.people / name_types.things, one boolean per
        // dictionary. Translate it rather than fail, so a 1.x application still
        // generates passwords immediately after `composer update`.
        @trigger_error(
            'The password-toolkit [name_types] config block is deprecated and will be removed in 3.0. '
            .'Re-publish the config with --force and use [dictionaries.enabled] / [dictionaries.except].',
            E_USER_DEPRECATED,
        );

        $legacy = is_array($config['name_types']) ? $config['name_types'] : [];
        $flags = array_merge(
            is_array($legacy['people'] ?? null) ? $legacy['people'] : [],
            is_array($legacy['things'] ?? null) ? $legacy['things'] : [],
        );

        $enabled = [];

        foreach ($flags as $key => $on) {
            if ($on) {
                $enabled[] = (string) $key;
            }
        }

        return ['enabled' => $enabled, 'except' => []];
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = $value === '' ? [] : explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $value,
        ), static fn (string $item): bool => $item !== ''));
    }
}
