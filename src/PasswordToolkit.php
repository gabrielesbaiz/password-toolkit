<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit;

use Gabrielesbaiz\PasswordToolkit\Contracts\DictionaryRepository;
use Gabrielesbaiz\PasswordToolkit\Contracts\PasswordGenerator;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\AdjectiveResolver;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\Entry;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\NameTranslator;
use Gabrielesbaiz\PasswordToolkit\Enums\DictionaryGroup;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;
use Gabrielesbaiz\PasswordToolkit\Exceptions\NoDictionariesEnabledException;
use Gabrielesbaiz\PasswordToolkit\Generator\LeetspeakTransformer;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;
use Gabrielesbaiz\PasswordToolkit\Generator\PasswordBuilder;
use Gabrielesbaiz\PasswordToolkit\Support\Entropy;
use Gabrielesbaiz\PasswordToolkit\Support\StrengthReport;
use Illuminate\Support\Collection;

/**
 * Assembles memorable passwords from a name, an adjective and some digits.
 *
 * The adjective agrees with the name, and the numeric segment is optional.
 *
 * This is an ordinary object bound as a singleton, not a static class. That is
 * what makes the facade genuinely swappable in tests, and what lets an
 * application replace the dictionary source without subclassing.
 */
class PasswordToolkit implements PasswordGenerator
{
    /**
     * Upper bound on one batch.
     *
     * Not a security boundary, a blast radius: generateMany() builds the whole
     * array in memory, so a count that reached it from a request parameter
     * would exhaust the process. Anything legitimately larger should be
     * chunked by the caller.
     */
    public const MAX_BATCH = 100_000;

    /**
     * Create a new password toolkit instance.
     */
    public function __construct(
        protected readonly DictionaryRepository $dictionaries,
        protected readonly AdjectiveResolver $adjectives,
        protected readonly NameTranslator $names,
        protected readonly LeetspeakTransformer $leetspeak = new LeetspeakTransformer,
    ) {}

    /**
     * Start a fluent, per-call override chain.
     */
    public function make(?Options $options = null): PasswordBuilder
    {
        return new PasswordBuilder($this, $options ?? Options::fromConfig());
    }

    /**
     * Generate a single password.
     *
     * @throws NoDictionariesEnabledException
     */
    public function generate(?Options $options = null): string
    {
        $options ??= Options::fromConfig();

        return $this->assemble($this->pickName($options), $options);
    }

    /**
     * Generate a batch of passwords.
     *
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
     * Generate a batch with no repeats.
     *
     * The pool is finite, so this can be asked for more passwords than exist.
     * Rather than spin forever it gives up after a bounded number of attempts
     * and says how many it managed — a silent short array would be worse.
     *
     * @return array<int, string>
     */
    public function generateUnique(int $count, ?Options $options = null): array
    {
        $this->guardCount($count);

        $options ??= Options::fromConfig();
        $entries = $this->entries($options);

        $passwords = [];
        $attempts = 0;

        // The base of 100 is what makes a small batch practical: asking for
        // three passwords out of a space of thirty needs far more than thirty
        // attempts to land three distinct ones.
        $limit = ($count * $options->uniqueAttemptsMultiplier) + 100;

        while (count($passwords) < $count && $attempts < $limit) {
            $attempts++;
            $password = $this->assemble($entries[random_int(0, count($entries) - 1)], $options);
            $passwords[$password] = true;
        }

        if (count($passwords) < $count) {
            throw InvalidOptionException::because(sprintf(
                'Could only generate %d unique passwords out of %d requested in %d attempts. '
                .'Enable more dictionaries, or add digits to widen the space.',
                count($passwords),
                $count,
                $attempts,
            ));
        }

        return array_keys($passwords);
    }

    /**
     * Generate one password alongside its strength report.
     *
     * @return array{password: string, report: StrengthReport}
     */
    public function generateWithReport(?Options $options = null): array
    {
        $options ??= Options::fromConfig();
        $password = $this->generate($options);

        return ['password' => $password, 'report' => $this->structuralReport($password, $options)];
    }

    /**
     * Generate a batch of passwords, each with its strength report.
     *
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
            strength: Entropy::strength($bits, $options->strengthThresholds),
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
            $options->numbersAllowLeadingZero,
            // A pool of one cannot supply a second distinct adjective, and the
            // generator falls back to one word rather than throwing — so the
            // report credits what the draw could actually have done.
            $options->wordCount > 2 && $pools['adjectives'] > 1 ? 2 : 1,
        );

        $bits = $components['total'];
        $crack = Entropy::crackTime($bits, $options->guessesPerSecond);

        return new StrengthReport(
            entropyBits: $bits,
            length: mb_strlen($password),
            strength: Entropy::strength($bits, $options->strengthThresholds),
            components: $components,
            charsetFlags: Entropy::charsetBits($password)['flags'],
            crackTimeSeconds: $crack['seconds'],
            crackTimeHuman: $crack['human'],
        );
    }

    /**
     * Get how large the name and adjective pools currently are.
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
     * Get every dictionary currently visible, as plain arrays.
     *
     * @return Collection<string, array{key: string, label: string, icon: string|null, type: string, group: string|null, group_label: string|null, tags: array<int, string>, reach: string, reach_label: string, locale: string|null, count: int, built_in: bool}>
     */
    public function dictionaries(?Options $options = null): Collection
    {
        $pool = $options === null
            ? $this->dictionaries->all()
            : $this->dictionaries->enabled($options);

        return collect($pool)->map(fn ($dictionary) => $dictionary->toArray());
    }

    /**
     * Get the dictionaries, with a live sample password each.
     *
     * The sample is what makes a picker useful — a row saying "Italian Pasta
     * Shapes, 41 entries" tells you much less than one showing
     * "Fusilli-Gustoso-4271".
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function dictionariesWithSamples(?Options $options = null): Collection
    {
        $options ??= Options::fromConfig();

        /** @var Collection<string, array<string, mixed>> $rows */
        $rows = collect($this->dictionaries->enabled($options))->map(
            fn ($dictionary): array => $dictionary->toArray() + [
                'sample' => $this->generate($options->with(enabled: [$dictionary->key])),
            ],
        );

        return $rows;
    }

    /**
     * Get the thematic groups in play, with how many dictionaries each holds.
     *
     * Everything a picker needs to render a group filter without hardcoding the
     * vocabulary.
     *
     * @return Collection<int, array{value: string, label: string, icon: string, count: int}>
     */
    public function groups(?Options $options = null): Collection
    {
        return $this->dictionaries($options ?? Options::fromConfig())
            ->groupBy('group')
            ->map(fn (Collection $rows, string $group): array => [
                'value' => $group,
                'label' => DictionaryGroup::from($group)->label(),
                'icon' => DictionaryGroup::from($group)->icon(),
                'count' => $rows->count(),
            ])
            ->values();
    }

    /**
     * Get every tag in play, with how many dictionaries carry it.
     *
     * @return Collection<int, array{value: string, count: int}>
     */
    public function tags(?Options $options = null): Collection
    {
        return $this->dictionaries($options ?? Options::fromConfig())
            ->flatMap(static fn (array $row): array => $row['tags'])
            ->countBy()
            ->map(fn (int $count, string $tag): array => ['value' => $tag, 'count' => $count])
            ->sortBy('value')
            ->values();
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
        $this->names->flush();
    }

    /**
     * Drop every cached dictionary and adjective pool.
     *
     * @deprecated 2.0 Use flushCache(). Removed in 3.0.
     */
    public function clearPoolCache(): void
    {
        $this->flushCache();
    }

    /**
     * Get the flattened entry pool, failing when nothing is enabled.
     *
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

    /**
     * Draw one name, uniformly across every enabled entry.
     *
     * @throws NoDictionariesEnabledException
     */
    protected function pickName(Options $options): Entry
    {
        $entries = $this->entries($options);

        // Uniform across every enabled *name*, not across files. 1.x picked a
        // file first, which made a 20-entry dictionary as likely as a
        // 200-entry one and inflated the reported entropy.
        return $entries[random_int(0, count($entries) - 1)];
    }

    /**
     * Assemble one password from a name entry and the given options.
     */
    protected function assemble(Entry $entry, Options $options): string
    {
        $separator = $options->separator ?? '';

        // Most names are proper nouns and come back untouched; only the
        // dictionaries that hold Italian dub or exonym forms have anything to
        // translate.
        $entry = $this->names->translate($entry, $options);

        // Names may come from a dictionary the application supplied — the
        // README suggests User::pluck() — so nothing is trusted to be clean.
        // Whitespace becomes the separator or disappears, and everything that
        // is neither a letter, a digit, nor the separator is removed. Without
        // this a nickname carrying a quote, a semicolon or a newline would end
        // up inside the password.
        $name = $this->sanitize(
            $options->nameSeparator
                ? str_replace(' ', $separator, $entry->name)
                : str_replace(' ', '', $entry->name),
            $separator,
        );

        $name = $options->casing->applyToName($name);

        // One adjective, or two when word_count is 3. Both agree with the
        // name's gender, because the resolver filters the pool before drawing
        // — "Goldrake-Mitico-Potente" only reads as Italian if every word
        // agrees with every other.
        $adjectives = array_map(
            fn (Entry $adjective): string => $options->casing->apply($this->alphanumeric($adjective->name)),
            $this->adjectives->many($entry, $options, $options->wordCount - 1),
        );

        // Word order is a property of the language, not a preference: Italian
        // says "Goldrake Mitico", English says "Legendary Goldrake". The locale
        // declares it; config may override.
        $position = $options->adjectivePosition ?? $this->adjectives->positionFor($options);
        $words = $position->words($name, $adjectives);

        $password = $options->addNumbers
            ? implode($separator, $options->numbersPosition->place(
                $words,
                $this->randomNumber($options->numbersDigits, $options->numbersAllowLeadingZero),
                $position->boundary(count($adjectives)),
            ))
            : implode($separator, $words);

        return $this->leetspeak->apply($password, $options->leetspeak);
    }

    /**
     * Strip everything that is not a letter or a digit.
     *
     * An adjective is a single word, so nothing else is allowed through.
     */
    protected function alphanumeric(string $value): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]/u', '', $value);
    }

    /**
     * Strip everything that is neither a letter, a digit, nor the separator.
     *
     * A password that carries a quote, a semicolon, a newline or a control
     * character breaks shells, CSV exports and copy-paste — and is exactly what
     * an unsanitised name from an application's own dictionary would produce.
     */
    protected function sanitize(string $value, string $separator): string
    {
        if ($separator === '') {
            return $this->alphanumeric($value);
        }

        $quoted = preg_quote($separator, '/');

        // Skip over separator occurrences, strip everything else that is not
        // alphanumeric.
        $value = (string) preg_replace('/(?:'.$quoted.')(*SKIP)(*FAIL)|[^\p{L}\p{N}]/u', '', $value);

        // Stripping can leave separators adjacent — "O'Brien Jr." collapses to
        // "OBrien-Jr-" — so runs are folded back to one and the edges trimmed.
        $value = (string) preg_replace('/(?:'.$quoted.')+/u', $separator, $value);

        return trim($value, $separator);
    }

    /**
     * Get the numeric segment, as a fixed-width string.
     *
     * Without leading zeros the draw starts at 10^(d-1), so "042" can never
     * appear and six digits are 900,000 values rather than 1,000,000. That is
     * the historical behaviour and stays the default because a segment that
     * never starts with zero is easier to read back; allowing them buys the
     * missing 0.15 bits, at the cost of a password nobody can dictate without
     * saying "zero four two".
     */
    protected function randomNumber(int $digits, bool $allowLeadingZero = false): string
    {
        if ($allowLeadingZero) {
            return str_pad(
                (string) random_int(0, (10 ** $digits) - 1),
                $digits,
                '0',
                STR_PAD_LEFT,
            );
        }

        return (string) random_int(10 ** ($digits - 1), (10 ** $digits) - 1);
    }

    /**
     * Validate the requested batch size.
     *
     * The ceiling is a blast radius rather than a security boundary: a batch is
     * built entirely in memory, so a count arriving from a request parameter
     * has to be refused before it exhausts the process.
     *
     * @throws InvalidOptionException
     */
    protected function guardCount(int $count): void
    {
        if ($count < 1) {
            throw InvalidOptionException::because("Count must be 1 or more, got {$count}.");
        }

        if ($count > self::MAX_BATCH) {
            throw InvalidOptionException::because(
                'Count must be at most '.self::MAX_BATCH.", got {$count}. Generate in chunks.",
            );
        }
    }
}
