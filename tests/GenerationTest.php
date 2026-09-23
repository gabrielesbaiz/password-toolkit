<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Dictionaries\AdjectiveResolver;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\Entry;
use Gabrielesbaiz\PasswordToolkit\Enums\Casing;
use Gabrielesbaiz\PasswordToolkit\Enums\Gender;
use Gabrielesbaiz\PasswordToolkit\Enums\Leetspeak;
use Gabrielesbaiz\PasswordToolkit\Enums\NumbersPosition;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;
use Gabrielesbaiz\PasswordToolkit\Exceptions\NoDictionariesEnabledException;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;

/**
 * Every adjective a dictionary can draw in a locale, keyed by word.
 *
 * Themed pool first, locale default behind it, which is the order the resolver
 * itself searches in.
 */
function adjectivePool(string $locale, string $key): array
{
    $pool = [];

    foreach ([AdjectiveResolver::DEFAULT_KEY, $key] as $file) {
        $path = packagePath("src/Data/Adjectives/{$locale}/{$file}.json");

        foreach (json_decode((string) file_get_contents($path), true)['values'] as $entry) {
            $pool[$entry['name']] = $entry;
        }
    }

    return $pool;
}

beforeEach(function () {
    config()->set('password-toolkit.separator_symbol', '-');
    config()->set('password-toolkit.name_separator', true);
    config()->set('password-toolkit.add_numbers', false);
    config()->set('password-toolkit.numbers_digits', 4);
    config()->set('password-toolkit.numbers_position', 'end');
    config()->set('password-toolkit.leetspeak_conversion', 'none');
    config()->set('password-toolkit.dictionaries.enabled', '*');
    config()->set('password-toolkit.dictionaries.except', []);

    PasswordToolkit::flushCache();
});

describe('generate()', function () {
    it('returns a non-empty string', function () {
        expect(PasswordToolkit::generate())->toBeString()->not->toBeEmpty();
    });

    it('throws when nothing is enabled', function () {
        onlyDictionaries();

        PasswordToolkit::generate();
    })->throws(NoDictionariesEnabledException::class);

    it('never leaks a space into the password', function () {
        foreach (PasswordToolkit::generateMany(50) as $password) {
            expect($password)->not->toContain(' ');
        }
    });

    it('uses the configured separator', function () {
        config()->set('password-toolkit.separator_symbol', '_');
        onlyDictionaries('back_to_the_future');

        expect(PasswordToolkit::generate())->toContain('_')->not->toContain('-');
    });

    it('strips word breaks when name_separator is false', function () {
        config()->set('password-toolkit.name_separator', false);
        onlyDictionaries('back_to_the_future');

        expect(substr_count(PasswordToolkit::generate(), '-'))->toBe(1);
    });

    it('accepts a null separator', function () {
        config()->set('password-toolkit.separator_symbol', null);
        onlyDictionaries('back_to_the_future');

        expect(PasswordToolkit::generate())->toMatch('/^[A-Za-z0-9]+$/');
    });
});

describe('numbers', function () {
    beforeEach(function () {
        config()->set('password-toolkit.add_numbers', true);
        onlyDictionaries('back_to_the_future');
    });

    it('appends the number at the end', function () {
        expect(PasswordToolkit::generate())->toMatch('/-\d{4}$/');
    });

    it('places the number at the start', function () {
        config()->set('password-toolkit.numbers_position', 'start');
        config()->set('password-toolkit.numbers_digits', 5);

        expect(PasswordToolkit::generate())->toMatch('/^\d{5}-/');
    });

    it('places the number in the middle', function () {
        config()->set('password-toolkit.numbers_position', 'middle');
        config()->set('password-toolkit.numbers_digits', 3);

        expect(PasswordToolkit::generate())->toMatch('/-\d{3}-/');
    });

    it('honours the digit count', function () {
        config()->set('password-toolkit.numbers_digits', 6);

        expect(PasswordToolkit::generate())->toMatch('/-\d{6}$/');
    });

    it('rejects an out-of-range digit count', function () {
        config()->set('password-toolkit.numbers_digits', 0);

        PasswordToolkit::generate();
    })->throws(InvalidOptionException::class);

    it('rejects an unknown position', function () {
        config()->set('password-toolkit.numbers_position', 'sideways');

        PasswordToolkit::generate();
    })->throws(InvalidOptionException::class);
});

describe('leetspeak', function () {
    it('applies basic mode without advanced glyphs', function () {
        config()->set('password-toolkit.leetspeak_conversion', 'basic');
        onlyDictionaries('back_to_the_future');

        expect(PasswordToolkit::generate())->not->toMatch('/[#%<|\\\\]/');
    });

    it('applies advanced mode', function () {
        config()->set('password-toolkit.leetspeak_conversion', 'advanced');
        onlyDictionaries('back_to_the_future');

        expect(PasswordToolkit::generate())->toBeString()->not->toBeEmpty();
    });

    it('still applies leetspeak when numbers are off', function () {
        config()->set('password-toolkit.leetspeak_conversion', 'basic');
        config()->set('password-toolkit.add_numbers', false);
        onlyDictionaries('back_to_the_future');

        expect(PasswordToolkit::generate())->not->toMatch('/[aeiosAEIOS]/');
    });

    it('accepts the 1.x spelling of none', function () {
        config()->set('password-toolkit.leetspeak_conversion', 'no');
        onlyDictionaries('back_to_the_future');

        expect(PasswordToolkit::generate())->toBeString();
    });

    it('rejects an unknown mode', function () {
        config()->set('password-toolkit.leetspeak_conversion', 'extreme');

        PasswordToolkit::generate();
    })->throws(InvalidOptionException::class);

    it('maps basic mode to single characters only', function () {
        foreach (Leetspeak::Basic->map() as $to) {
            expect(strlen($to))->toBe(1);
        }
    });

    it('treats basic as a subset of advanced', function () {
        expect(array_diff_key(Leetspeak::Basic->map(), Leetspeak::Advanced->map()))->toBe([]);
    });
});

describe('generateMany()', function () {
    it('returns exactly the requested count', function () {
        expect(PasswordToolkit::generateMany(25))->toHaveCount(25);
    });

    it('rejects a count below one', function () {
        PasswordToolkit::generateMany(0);
    })->throws(InvalidOptionException::class);

    it('produces mostly distinct passwords', function () {
        config()->set('password-toolkit.add_numbers', true);

        $passwords = PasswordToolkit::generateMany(50);

        expect(count(array_unique($passwords)))->toBeGreaterThan(45);
    });
});

describe('NumbersPosition', function () {
    it('arranges segments per position', function () {
        expect(NumbersPosition::Start->arrange('N', 'A', '1'))->toBe(['1', 'N', 'A'])
            ->and(NumbersPosition::Middle->arrange('N', 'A', '1'))->toBe(['N', '1', 'A'])
            ->and(NumbersPosition::End->arrange('N', 'A', '1'))->toBe(['N', 'A', '1']);
    });
});

describe('casing', function () {
    beforeEach(function () {
        onlyDictionaries('back_to_the_future');
    });

    it('leaves a name spelled as the dictionary wrote it under title case', function () {
        // The default has to keep producing exactly what it produced before
        // casing was configurable: MB_CASE_TITLE would flatten McFly to Mcfly,
        // and nine of this dictionary's seventeen names carry one.
        $passwords = PasswordToolkit::generateMany(200);

        expect(implode(' ', $passwords))->toContain('McFly')->not->toContain('Mcfly');
    });

    it('title-cases the adjective', function () {
        config()->set('password-toolkit.case', 'title');

        expect(PasswordToolkit::generate())->toMatch('/-[A-Z][a-z]+$/');
    });

    it('lower-cases every word', function () {
        config()->set('password-toolkit.case', 'lower');

        expect(PasswordToolkit::generate())->toMatch('/^[a-z-]+$/');
    });

    it('upper-cases every word', function () {
        config()->set('password-toolkit.case', 'upper');

        expect(PasswordToolkit::generate())->toMatch('/^[A-Z-]+$/');
    });

    it('preserves both words as stored', function () {
        PasswordToolkit::registerDictionary('odd_case', [['name' => 'mIxEd', 'gender' => 'neutral']]);
        onlyDictionaries('odd_case');
        config()->set('password-toolkit.case', 'preserve');

        // Adjectives ship Title Case, so preserve only differs visibly on the
        // name — which is the half a casing mode is most likely to mangle.
        expect(PasswordToolkit::generate())->toStartWith('mIxEd-')->toMatch('/-[A-Z][a-z]+$/');
    });

    it('never touches the digits', function () {
        config()->set('password-toolkit.add_numbers', true);
        config()->set('password-toolkit.numbers_digits', 6);

        foreach (['lower', 'upper', 'preserve', 'title'] as $case) {
            config()->set('password-toolkit.case', $case);

            expect(PasswordToolkit::generate())->toMatch('/-\d{6}$/');
        }
    });

    it('rejects an unknown case', function () {
        config()->set('password-toolkit.case', 'sentence');

        PasswordToolkit::generate();
    })->throws(InvalidOptionException::class);

    it('applies each mode to a single word', function () {
        expect(Casing::Title->apply('mitico'))->toBe('Mitico')
            ->and(Casing::Lower->apply('MITICO'))->toBe('mitico')
            ->and(Casing::Upper->apply('mitico'))->toBe('MITICO')
            ->and(Casing::Preserve->apply('mItIcO'))->toBe('mItIcO')
            ->and(Casing::Title->applyToName('McFly'))->toBe('McFly')
            ->and(Casing::Upper->applyToName('McFly'))->toBe('MCFLY');
    });
});

describe('leading zeros', function () {
    beforeEach(function () {
        config()->set('password-toolkit.add_numbers', true);
        config()->set('password-toolkit.numbers_digits', 4);
        onlyDictionaries('back_to_the_future');
    });

    it('never starts the segment with a zero by default', function () {
        foreach (PasswordToolkit::generateMany(300) as $password) {
            expect($password)->toMatch('/-[1-9]\d{3}$/');
        }
    });

    it('keeps the width when leading zeros are allowed', function () {
        config()->set('password-toolkit.numbers_allow_leading_zero', true);

        foreach (PasswordToolkit::generateMany(300) as $password) {
            expect($password)->toMatch('/-\d{4}$/');
        }
    });

    it('does produce a leading zero when allowed', function () {
        config()->set('password-toolkit.numbers_allow_leading_zero', true);
        config()->set('password-toolkit.numbers_digits', 2);

        // 1 in 10 per draw; 200 draws without one would be a 1-in-10^9 fluke.
        $passwords = PasswordToolkit::generateMany(200);

        expect(array_filter($passwords, fn (string $p): bool => (bool) preg_match('/-0\d$/', $p)))
            ->not->toBeEmpty();
    });
});

describe('word_count', function () {
    beforeEach(function () {
        config()->set('password-toolkit.add_numbers', false);
        config()->set('password-toolkit.word_count', 3);
        config()->set('password-toolkit.name_separator', false);
        onlyDictionaries('star_wars');
    });

    it('builds two words by default', function () {
        config()->set('password-toolkit.word_count', 2);

        expect(substr_count(PasswordToolkit::generate(), '-'))->toBe(1);
    });

    it('builds three when asked', function () {
        expect(substr_count(PasswordToolkit::generate(), '-'))->toBe(2);
    });

    it('puts both adjectives after the name in Italian', function () {
        config()->set('password-toolkit.locale', 'it');
        PasswordToolkit::flushCache();

        $adjectives = adjectivePool('it', 'star_wars');

        foreach (PasswordToolkit::generateMany(50) as $password) {
            [, $first, $second] = explode('-', $password);

            expect($adjectives)->toHaveKey($first)->toHaveKey($second);
        }
    });

    it('puts both adjectives before the name in English', function () {
        config()->set('password-toolkit.locale', 'en');
        PasswordToolkit::flushCache();

        $adjectives = adjectivePool('en', 'star_wars');

        foreach (PasswordToolkit::generateMany(50) as $password) {
            [$first, $second] = explode('-', $password);

            expect($adjectives)->toHaveKey($first)->toHaveKey($second);
        }
    });

    it('agrees both Italian adjectives with the gender of the name', function () {
        config()->set('password-toolkit.locale', 'it');
        PasswordToolkit::flushCache();

        $adjectives = adjectivePool('it', 'star_wars');
        $names = collect(json_decode(
            (string) file_get_contents(packagePath('src/Data/Names/People/star_wars.json')),
            true,
        )['values'])->mapWithKeys(fn (array $entry): array => [
            str_replace(' ', '', $entry['name']) => $entry['gender'],
        ]);

        foreach (PasswordToolkit::generateMany(100) as $password) {
            [$name, $first, $second] = explode('-', $password);

            // Italian names are translated on the way out, so only assert on
            // the ones this dictionary actually declares a gender for.
            if (! $names->has($name)) {
                continue;
            }

            expect($adjectives[$first]['gender'])->toBeIn([$names[$name], 'neutral'])
                ->and($adjectives[$second]['gender'])->toBeIn([$names[$name], 'neutral']);
        }
    });

    it('never draws the same adjective twice', function () {
        foreach (PasswordToolkit::generateMany(200) as $password) {
            $segments = explode('-', $password);

            expect($segments[1])->not->toBe($segments[2]);
        }
    });

    it('falls back to one adjective when the pool cannot supply two', function () {
        // A pool of exactly one agreeing adjective: fewer words is a shorter
        // password, throwing would be no password at all.
        $resolver = new AdjectiveResolver(packagePath('tests/fixtures/adjectives'));

        $drawn = $resolver->many(
            new Entry('Test', Gender::Neutral, 'lonely'),
            (new Options)->with(locale: 'it', fallbackLocale: 'it'),
            2,
        );

        expect($drawn)->toHaveCount(1)
            ->and($drawn[0]->name)->toBe('Unico');
    });

    it('rejects a word count outside 2 or 3', function () {
        config()->set('password-toolkit.word_count', 4);

        PasswordToolkit::generate();
    })->throws(InvalidOptionException::class);

    it('places the digits between the name and the adjective pair', function () {
        config()->set('password-toolkit.add_numbers', true);
        config()->set('password-toolkit.numbers_digits', 4);
        config()->set('password-toolkit.numbers_position', 'middle');
        config()->set('password-toolkit.locale', 'it');
        PasswordToolkit::flushCache();

        // Italian leads with the name, so 'middle' is right after it — which
        // keeps the two agreeing adjectives adjacent.
        expect(PasswordToolkit::generate())->toMatch('/^[A-Za-z]+-\d{4}-[A-Za-z]+-[A-Za-z]+$/');
    });

    it('places the digits before the name in English', function () {
        config()->set('password-toolkit.add_numbers', true);
        config()->set('password-toolkit.numbers_digits', 4);
        config()->set('password-toolkit.numbers_position', 'middle');
        config()->set('password-toolkit.locale', 'en');
        PasswordToolkit::flushCache();

        expect(PasswordToolkit::generate())->toMatch('/^[A-Za-z]+-[A-Za-z]+-\d{4}-[A-Za-z]+$/');
    });
});

describe('generateUnique()', function () {
    beforeEach(function () {
        config()->set('password-toolkit.add_numbers', false);
        onlyDictionaries('back_to_the_future');
    });

    it('returns distinct passwords', function () {
        $passwords = PasswordToolkit::generateUnique(5);

        expect($passwords)->toHaveCount(5)
            ->and(array_unique($passwords))->toHaveCount(5);
    });

    it('gives up rather than spinning when the pool is too small', function () {
        // One name, one agreeing adjective per gender: the space cannot hold
        // fifty distinct passwords however long it draws.
        PasswordToolkit::registerDictionary('solo', [['name' => 'Ada', 'gender' => 'female']]);
        onlyDictionaries('solo');
        config()->set('password-toolkit.unique_attempts_multiplier', 1);

        PasswordToolkit::generateUnique(5000);
    })->throws(InvalidOptionException::class, 'Enable more dictionaries');

    it('honours the attempts multiplier', function () {
        config()->set('password-toolkit.unique_attempts_multiplier', 3);

        expect(Options::fromConfig()->uniqueAttemptsMultiplier)->toBe(3);

        // 3 * 2 + 100 attempts against a 17-name pool still fills a pair.
        expect(PasswordToolkit::generateUnique(2))->toHaveCount(2);
    });

    it('rejects a multiplier below one', function () {
        config()->set('password-toolkit.unique_attempts_multiplier', 0);

        PasswordToolkit::generateUnique(2);
    })->throws(InvalidOptionException::class);
});
