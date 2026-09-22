<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Generator;

use Gabrielesbaiz\PasswordToolkit\Contracts\PasswordGenerator;
use Gabrielesbaiz\PasswordToolkit\Enums\AdjectivePosition;
use Gabrielesbaiz\PasswordToolkit\Enums\DictionaryGroup;
use Gabrielesbaiz\PasswordToolkit\Enums\Leetspeak;
use Gabrielesbaiz\PasswordToolkit\Enums\NumbersPosition;
use Gabrielesbaiz\PasswordToolkit\Enums\Reach;
use Gabrielesbaiz\PasswordToolkit\Support\StrengthReport;

/**
 * Fluent per-call overrides, so a caller can deviate from the published
 * config without mutating it.
 *
 * Every method returns a new builder wrapping a new Options, which makes a
 * partially-configured builder safe to keep on a property and reuse.
 */
final readonly class PasswordBuilder
{
    public function __construct(
        private PasswordGenerator $generator,
        private Options $options,
    ) {}

    public function __toString(): string
    {
        return $this->generate();
    }

    public function options(): Options
    {
        return $this->options;
    }

    /**
     * Restrict generation to these dictionary keys.
     *
     * @param  array<int, string>|string  $keys
     */
    public function only(array|string $keys): self
    {
        return $this->derive(enabled: array_values((array) $keys));
    }

    /**
     * Exclude these dictionary keys.
     *
     * @param  array<int, string>|string  $keys
     */
    public function except(array|string $keys): self
    {
        return $this->derive(except: array_values((array) $keys));
    }

    /**
     * Restrict to 'people', 'things', or both.
     *
     * @param  array<int, string>|string  $types
     */
    public function types(array|string $types): self
    {
        return $this->derive(types: array_values((array) $types));
    }

    /**
     * Add a directory of user dictionaries for this call.
     *
     * @param  array<int, string>|string  $paths
     */
    public function paths(array|string $paths): self
    {
        return $this->derive(paths: array_values(array_unique([...$this->options->paths, ...(array) $paths])));
    }

    /**
     * Restrict to one or more thematic groups: food, screen, sport and so on.
     *
     * @param  array<int, DictionaryGroup|string>|DictionaryGroup|string  $groups
     */
    public function groups(array|DictionaryGroup|string $groups): self
    {
        // Not (array) $groups: casting an enum yields its properties, not the
        // enum itself.
        $groups = is_array($groups) ? $groups : [$groups];

        return $this->derive(groups: array_map(
            static fn (DictionaryGroup|string $group): DictionaryGroup => is_string($group)
                ? DictionaryGroup::parse($group)
                : $group,
            array_values($groups),
        ));
    }

    /**
     * Restrict to dictionaries carrying all of these tags.
     *
     * @param  array<int, string>|string  $tags
     */
    public function tagged(array|string $tags): self
    {
        return $this->derive(tags: array_values((array) $tags));
    }

    /**
     * Restrict to dictionaries at least this widely recognisable.
     *
     * ->reach('global') is the sensible default for an international audience:
     * a password is only memorable if the reader knows the word.
     */
    public function reach(Reach|string|null $reach): self
    {
        return $this->derive(reach: is_string($reach) ? Reach::parse($reach) : $reach);
    }

    public function locale(?string $locale): self
    {
        return $this->derive(locale: $locale);
    }

    public function fallbackLocale(string $locale): self
    {
        return $this->derive(fallbackLocale: $locale);
    }

    public function separator(?string $separator): self
    {
        return $this->derive(separator: $separator);
    }

    /**
     * Whether spaces inside a multi-word name become the separator (true) or
     * are stripped entirely (false).
     */
    public function keepWordBreaks(bool $keep = true): self
    {
        return $this->derive(nameSeparator: $keep);
    }

    public function digits(int $digits): self
    {
        return $this->derive(addNumbers: true, numbersDigits: $digits);
    }

    public function withoutNumbers(): self
    {
        return $this->derive(addNumbers: false);
    }

    /**
     * Force the adjective before or after the name, overriding what the locale
     * declares. Pass null to go back to following the locale.
     */
    public function adjectiveAt(AdjectivePosition|string|null $position): self
    {
        return $this->derive(adjectivePosition: is_string($position)
            ? AdjectivePosition::parse($position)
            : $position);
    }

    public function numbersAt(NumbersPosition|string $position): self
    {
        return $this->derive(numbersPosition: is_string($position) ? NumbersPosition::parse($position) : $position);
    }

    public function leet(Leetspeak|string $mode = Leetspeak::Basic): self
    {
        return $this->derive(leetspeak: is_string($mode) ? Leetspeak::parse($mode) : $mode);
    }

    public function guessesPerSecond(float $rate): self
    {
        return $this->derive(guessesPerSecond: $rate);
    }

    public function generate(): string
    {
        return $this->generator->generate($this->options);
    }

    /**
     * @return array<int, string>
     */
    public function many(int $count): array
    {
        return $this->generator->generateMany($count, $this->options);
    }

    /**
     * A batch with no repeats.
     *
     * @return array<int, string>
     */
    public function unique(int $count): array
    {
        return $this->generator->generateUnique($count, $this->options);
    }

    /**
     * @return array{password: string, report: StrengthReport}
     */
    public function withReport(): array
    {
        return $this->generator->generateWithReport($this->options);
    }

    /**
     * @return array<int, array{password: string, report: StrengthReport}>
     */
    public function manyWithReport(int $count): array
    {
        return $this->generator->generateManyWithReport($count, $this->options);
    }

    private function derive(mixed ...$changes): self
    {
        return new self($this->generator, $this->options->with(...$changes));
    }
}
