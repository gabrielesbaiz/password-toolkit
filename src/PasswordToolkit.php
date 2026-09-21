<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit;

use Gabrielesbaiz\PasswordToolkit\Contracts\DictionaryRepository;
use Gabrielesbaiz\PasswordToolkit\Contracts\PasswordGenerator;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\AdjectiveResolver;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\Entry;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;
use Gabrielesbaiz\PasswordToolkit\Exceptions\NoDictionariesEnabledException;
use Gabrielesbaiz\PasswordToolkit\Generator\LeetspeakTransformer;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;
use Gabrielesbaiz\PasswordToolkit\Generator\PasswordBuilder;
use Gabrielesbaiz\PasswordToolkit\Support\Entropy;
use Gabrielesbaiz\PasswordToolkit\Support\StrengthReport;
use Illuminate\Support\Collection;

/**
 * Assembles memorable passwords from a name, an agreeing adjective and an
 * optional numeric segment.
 *
 * This is an ordinary object bound as a singleton, not a static class. That is
 * what makes the facade genuinely swappable in tests, and what lets an
 * application replace the dictionary source without subclassing.
 */
class PasswordToolkit implements PasswordGenerator
{
    public function __construct(
        protected readonly DictionaryRepository $dictionaries,
        protected readonly AdjectiveResolver $adjectives,
        protected readonly LeetspeakTransformer $leetspeak = new LeetspeakTransformer,
    ) {}

    /**
     * Start a fluent, per-call override chain.
     */
    public function make(?Options $options = null): PasswordBuilder
    {
        return new PasswordBuilder($this, $options ?? Options::fromConfig());
    }

    public function generate(?Options $options = null): string
    {
        $options ??= Options::fromConfig();

        return $this->assemble($this->pickName($options), $options);
    }

    /**
     * @return array<int, string>
     */
    public function generateMany(int $count, ?Options $options = null): array
    {
        $this->guardCount($count);

        $options ??= Options::fromConfig();
        $entries = $this->entries($options);
        $passwords = [];

        // Resolve the pool once, then assemble in a tight loop. 1.x re-scanned
        // the data directory for every single password.
        for ($i = 0; $i < $count; $i++) {
            $passwords[] = $this->assemble($entries[random_int(0, count($entries) - 1)], $options);
        }

        return $passwords;
    }

    /**
     * @return array{password: string, report: StrengthReport}
     */
    public function generateWithReport(?Options $options = null): array
    {
        $options ??= Options::fromConfig();
        $password = $this->generate($options);

        return ['password' => $password, 'report' => $this->structuralReport($password, $options)];
    }

    /**
     * @return array<int, array{password: string, report: StrengthReport}>
     */
    public function generateManyWithReport(int $count, ?Options $options = null): array
    {
        $options ??= Options::fromConfig();

        return array_map(
            fn (string $password): array => [
                'password' => $password,
                'report' => $this->structuralReport($password, $options),
            ],
            $this->generateMany($count, $options),
        );
    }

    /**
     * Score an arbitrary password under the charset model.
     *
     * Use this for passwords you did not generate — a user's chosen one, say.
     */
    public function strength(string $password, ?Options $options = null): StrengthReport
    {
        $options ??= Options::fromConfig();
        $charset = Entropy::charsetBits($password);
        $bits = $charset['bits'];
        $crack = Entropy::crackTime($bits, $options->guessesPerSecond);

        return new StrengthReport(
            entropyBits: $bits,
            length: $charset['length'],
            strength: Entropy::strength($bits),
            components: ['charset_bits' => $bits],
            charsetFlags: $charset['flags'],
            crackTimeSeconds: $crack['seconds'],
            crackTimeHuman: $crack['human'],
        );
    }

    /**
     * Score a password under the structural model.
     *
     * This is the honest number for a password this package produced: an
     * attacker who knows the package searches the pool, not the alphabet.
     */
    public function structuralReport(string $password, ?Options $options = null): StrengthReport
    {
        $options ??= Options::fromConfig();

        $pools = $this->poolSizes($options);
        $components = Entropy::structuralBits(
            $pools['names'],
            $pools['adjectives'],
            $options->addNumbers ? $options->numbersDigits : 0,
            $options->leetspeak,
        );

        $bits = $components['total'];
        $crack = Entropy::crackTime($bits, $options->guessesPerSecond);

        return new StrengthReport(
            entropyBits: $bits,
            length: mb_strlen($password),
            strength: Entropy::strength($bits),
            components: $components,
            charsetFlags: Entropy::charsetBits($password)['flags'],
            crackTimeSeconds: $crack['seconds'],
            crackTimeHuman: $crack['human'],
        );
    }

    /**
     * How large the name and adjective pools currently are.
     *
     * The adjective figure is the mean across the selected dictionaries, since
     * which one applies depends on the name that gets picked.
     *
     * @return array{names: int, adjectives: int}
     */
    public function poolSizes(?Options $options = null): array
    {
        $options ??= Options::fromConfig();

        $names = 0;
        $adjectiveCounts = [];

        foreach ($this->dictionaries->enabled($options) as $dictionary) {
            $names += $dictionary->count();
            $adjectiveCounts[] = $this->adjectives->poolSize($dictionary->key, $options);
        }

        $adjectives = $adjectiveCounts === []
            ? 0
            : (int) round(array_sum($adjectiveCounts) / count($adjectiveCounts));

        return ['names' => $names, 'adjectives' => $adjectives];
    }

    /**
     * Every dictionary currently visible, as plain arrays.
     *
     * @return Collection<string, array{key: string, type: string, locale: string|null, count: int, built_in: bool}>
     */
    public function dictionaries(?Options $options = null): Collection
    {
        $pool = $options === null
            ? $this->dictionaries->all()
            : $this->dictionaries->enabled($options);

        return collect($pool)->map(fn ($dictionary) => $dictionary->toArray());
    }

    /**
     * Add a dictionary at runtime, e.g. from a service provider.
     *
     * @param  array<int, array{name: string, gender?: string}|string>  $values
     */
    public function registerDictionary(string $key, array $values, string $type = 'things', ?string $locale = null): void
    {
        $this->dictionaries->register($key, $values, $type, $locale);
    }

    /**
     * Drop every cached dictionary and adjective pool.
     *
     * Call this after changing configuration at runtime — tests mostly.
     */
    public function flushCache(): void
    {
        $this->dictionaries->flush();
        $this->adjectives->flush();
    }

    /**
     * @deprecated 2.0 Use flushCache(). Removed in 3.0.
     */
    public function clearPoolCache(): void
    {
        $this->flushCache();
    }

    /**
     * @return array<int, Entry>
     */
    protected function entries(Options $options): array
    {
        $entries = $this->dictionaries->entries($options);

        if ($entries === []) {
            throw NoDictionariesEnabledException::make();
        }

        return $entries;
    }

    protected function pickName(Options $options): Entry
    {
        $entries = $this->entries($options);

        // Uniform across every enabled *name*, not across files. 1.x picked a
        // file first, which made a 20-entry dictionary as likely as a
        // 200-entry one and inflated the reported entropy.
        return $entries[random_int(0, count($entries) - 1)];
    }

    protected function assemble(Entry $entry, Options $options): string
    {
        $separator = $options->separator ?? '';

        $name = $options->nameSeparator
            ? str_replace(' ', $separator, $entry->name)
            : $this->alphanumeric($entry->name);

        $adjective = $this->alphanumeric($this->adjectives->for($entry, $options)->name);

        $adjective = mb_convert_case($adjective, MB_CASE_TITLE);

        $password = $options->addNumbers
            ? implode($separator, $options->numbersPosition->arrange(
                $name,
                $adjective,
                (string) $this->randomNumber($options->numbersDigits),
            ))
            : $name.$separator.$adjective;

        return $this->leetspeak->apply($password, $options->leetspeak);
    }

    /**
     * Strip everything that is not a letter or a digit.
     *
     * Adjectives and un-separated names must not smuggle a space or an
     * apostrophe into the password, where it would break shells and copy-paste.
     */
    protected function alphanumeric(string $value): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]/u', '', $value);
    }

    protected function randomNumber(int $digits): int
    {
        $min = 10 ** ($digits - 1);
        $max = (10 ** $digits) - 1;

        return random_int($min, $max);
    }

    protected function guardCount(int $count): void
    {
        if ($count < 1) {
            throw InvalidOptionException::because("Count must be 1 or more, got {$count}.");
        }
    }
}
