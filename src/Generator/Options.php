<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Generator;

use Gabrielesbaiz\PasswordToolkit\Dictionaries\Dictionary;
use Gabrielesbaiz\PasswordToolkit\Enums\AdjectivePosition;
use Gabrielesbaiz\PasswordToolkit\Enums\Casing;
use Gabrielesbaiz\PasswordToolkit\Enums\DictionaryGroup;
use Gabrielesbaiz\PasswordToolkit\Enums\Leetspeak;
use Gabrielesbaiz\PasswordToolkit\Enums\NumbersPosition;
use Gabrielesbaiz\PasswordToolkit\Enums\Reach;
use Gabrielesbaiz\PasswordToolkit\Enums\Strength;
use Gabrielesbaiz\PasswordToolkit\Enums\StrengthModel;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;
use Gabrielesbaiz\PasswordToolkit\Support\Identifier;

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
     * Create a new options instance.
     *
     * @param  array<int, string>|string  $enabled  '*' for every dictionary
     * @param  array<int, string>  $except
     * @param  array<int, string>  $types
     * @param  array<int, DictionaryGroup>  $groups  empty means every group
     * @param  array<int, string>  $tags  a dictionary must carry all of them
     * @param  array<int, string>  $paths
     * @param  array<string, array<string, mixed>>  $custom
     * @param  array<string, mixed>  $strengthThresholds  partial overrides of the band edges, in bits
     */
    public function __construct(
        public array|string $enabled = '*',
        public array $except = [],
        public array $types = ['people', 'things'],
        public array $groups = [],
        public array $tags = [],
        public ?Reach $reach = null,
        public array $paths = [],
        public array $custom = [],
        public ?string $locale = null,
        public string $fallbackLocale = 'en',
        public ?string $separator = '-',
        public bool $nameSeparator = true,
        public bool $addNumbers = true,
        public int $numbersDigits = 6,
        public NumbersPosition $numbersPosition = NumbersPosition::End,
        public bool $numbersAllowLeadingZero = false,
        public ?AdjectivePosition $adjectivePosition = null,
        public Leetspeak $leetspeak = Leetspeak::None,
        public Casing $casing = Casing::Title,
        public int $wordCount = 2,
        public int $uniqueAttemptsMultiplier = 10,
        public float $guessesPerSecond = 1e10,
        public array $strengthThresholds = [],
        public StrengthModel $ruleModel = StrengthModel::Charset,
    ) {
        if ($this->numbersDigits < 1 || $this->numbersDigits > 18) {
            throw InvalidOptionException::because(
                "numbers_digits must be between 1 and 18, got {$this->numbersDigits}.",
            );
        }

        // Two or three, and nothing else. Four words of a 200-word pool add
        // less than eight bits between them and stop being a phrase anyone can
        // repeat down a phone line; digits are the cheaper lever by far.
        if ($this->wordCount < 2 || $this->wordCount > 3) {
            throw InvalidOptionException::because(
                "word_count must be 2 or 3, got {$this->wordCount}.",
            );
        }

        if ($this->uniqueAttemptsMultiplier < 1) {
            throw InvalidOptionException::because(
                "unique_attempts_multiplier must be 1 or more, got {$this->uniqueAttemptsMultiplier}.",
            );
        }

        // Validated on the way in rather than at scoring time, so a config typo
        // fails where it was written instead of inside a report.
        Strength::thresholds($this->strengthThresholds);

        foreach ($this->types as $type) {
            if (! in_array($type, ['people', 'things'], true)) {
                throw InvalidOptionException::because(
                    "Unknown dictionary type [{$type}]. Expected people or things.",
                );
            }
        }

        // Locales and dictionary keys are interpolated into a filesystem path,
        // so they are validated the moment they enter rather than where they
        // are used. An application passing a request value into ->locale()
        // could otherwise read any JSON file the process can see.
        if ($this->locale !== null) {
            Identifier::locale($this->locale);
        }

        Identifier::locale($this->fallbackLocale);

        foreach (is_array($this->enabled) ? $this->enabled : [] as $key) {
            Identifier::key($key);
        }

        foreach ($this->except as $key) {
            Identifier::key($key);
        }
    }

    /**
     * Create a new options instance from the published config.
     *
     * Translates the 1.x config shape when it is the one still present.
     */
    public static function fromConfig(): self
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('password-toolkit', []);

        ['enabled' => $enabled, 'except' => $except] = self::resolveSelection($config);

        $dictionaries = is_array($config['dictionaries'] ?? null) ? $config['dictionaries'] : [];

        $separator = array_key_exists('separator_symbol', $config) ? $config['separator_symbol'] : '-';

        $strength = is_array($config['strength'] ?? null) ? $config['strength'] : [];

        return new self(
            enabled: $enabled,
            except: $except,
            types: self::stringList($dictionaries['types'] ?? ['people', 'things']),
            groups: array_map(
                DictionaryGroup::parse(...),
                self::stringList($dictionaries['groups'] ?? []),
            ),
            tags: self::stringList($dictionaries['tags'] ?? []),
            reach: is_string($dictionaries['reach'] ?? null) && $dictionaries['reach'] !== ''
                ? Reach::parse($dictionaries['reach'])
                : null,
            paths: self::stringList($dictionaries['paths'] ?? []),
            custom: is_array($dictionaries['custom'] ?? null) ? $dictionaries['custom'] : [],
            locale: is_string($config['locale'] ?? null) ? $config['locale'] : null,
            fallbackLocale: is_string($config['fallback_locale'] ?? null) ? $config['fallback_locale'] : 'en',
            separator: is_string($separator) ? $separator : null,
            nameSeparator: (bool) ($config['name_separator'] ?? true),
            addNumbers: (bool) ($config['add_numbers'] ?? true),
            numbersDigits: (int) ($config['numbers_digits'] ?? 6),
            numbersPosition: NumbersPosition::parse((string) ($config['numbers_position'] ?? 'end')),
            numbersAllowLeadingZero: (bool) ($config['numbers_allow_leading_zero'] ?? false),
            adjectivePosition: is_string($config['adjective_position'] ?? null)
                ? AdjectivePosition::parse($config['adjective_position'])
                : null,
            leetspeak: Leetspeak::parse((string) ($config['leetspeak_conversion'] ?? 'none')),
            casing: Casing::parse((string) ($config['case'] ?? 'title')),
            wordCount: (int) ($config['word_count'] ?? 2),
            uniqueAttemptsMultiplier: (int) ($config['unique_attempts_multiplier'] ?? 10),
            guessesPerSecond: (float) ($strength['guesses_per_second'] ?? 1e10),
            strengthThresholds: self::keyedMap($strength['thresholds'] ?? null),
            ruleModel: StrengthModel::parse((string) ($strength['rule_model'] ?? 'charset')),
        );
    }

    /**
     * Get the locale adjectives are looked up in.
     */
    public function resolvedLocale(): string
    {
        if (is_string($this->locale) && $this->locale !== '') {
            return $this->locale;
        }

        $appLocale = app()->getLocale();

        // An application is free to set a locale this package cannot safely
        // turn into a path. Fall back rather than refuse to generate.
        return Identifier::isLocale($appLocale) ? $appLocale : $this->fallbackLocale;
    }

    /**
     * Determine whether a dictionary is selected by this option set.
     *
     * Named keys win outright: asking for a dictionary by name means you want
     * it, whatever group or reach it happens to carry.
     */
    public function selects(Dictionary $dictionary): bool
    {
        if (in_array($dictionary->key, $this->except, true)) {
            return false;
        }

        if (is_array($this->enabled)) {
            return in_array($dictionary->key, $this->enabled, true);
        }

        if (! in_array($dictionary->type, $this->types, true)) {
            return false;
        }

        if ($this->groups !== [] && ! in_array($dictionary->group, $this->groups, true)) {
            return false;
        }

        foreach ($this->tags as $tag) {
            if (! $dictionary->hasTag($tag)) {
                return false;
            }
        }

        return $this->reach === null || $dictionary->reach->satisfies($this->reach);
    }

    /**
     * Get a stable key for caching the resolved dictionary set.
     */
    public function selectionFingerprint(): string
    {
        return md5(serialize([
            $this->enabled,
            $this->except,
            $this->types,
            array_map(static fn (DictionaryGroup $group): string => $group->value, $this->groups),
            $this->tags,
            $this->reach?->value,
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
            groups: $changes['groups'] ?? $this->groups,
            tags: $changes['tags'] ?? $this->tags,
            reach: array_key_exists('reach', $changes) ? $changes['reach'] : $this->reach,
            paths: $changes['paths'] ?? $this->paths,
            custom: $changes['custom'] ?? $this->custom,
            locale: array_key_exists('locale', $changes) ? $changes['locale'] : $this->locale,
            fallbackLocale: $changes['fallbackLocale'] ?? $this->fallbackLocale,
            separator: array_key_exists('separator', $changes) ? $changes['separator'] : $this->separator,
            nameSeparator: $changes['nameSeparator'] ?? $this->nameSeparator,
            addNumbers: $changes['addNumbers'] ?? $this->addNumbers,
            numbersDigits: $changes['numbersDigits'] ?? $this->numbersDigits,
            numbersPosition: $changes['numbersPosition'] ?? $this->numbersPosition,
            numbersAllowLeadingZero: $changes['numbersAllowLeadingZero'] ?? $this->numbersAllowLeadingZero,
            adjectivePosition: array_key_exists('adjectivePosition', $changes)
                ? $changes['adjectivePosition']
                : $this->adjectivePosition,
            leetspeak: $changes['leetspeak'] ?? $this->leetspeak,
            casing: $changes['casing'] ?? $this->casing,
            wordCount: $changes['wordCount'] ?? $this->wordCount,
            uniqueAttemptsMultiplier: $changes['uniqueAttemptsMultiplier'] ?? $this->uniqueAttemptsMultiplier,
            guessesPerSecond: $changes['guessesPerSecond'] ?? $this->guessesPerSecond,
            strengthThresholds: $changes['strengthThresholds'] ?? $this->strengthThresholds,
            ruleModel: $changes['ruleModel'] ?? $this->ruleModel,
        );
    }

    /**
     * Resolve which dictionaries the config selects.
     *
     * Honours the 1.x `name_types` block if the application has not
     * re-published its config.
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
     * Normalise a config sub-array's keys to strings.
     *
     * The values stay mixed on purpose: whoever consumes them validates them,
     * which keeps one definition of what a valid value is.
     *
     * @return array<string, mixed>
     */
    private static function keyedMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }

    /**
     * Normalise a config value to a list of non-empty strings.
     *
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
