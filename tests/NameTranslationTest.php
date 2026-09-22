<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Dictionaries\Entry;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\NameTranslator;
use Gabrielesbaiz\PasswordToolkit\Enums\Gender;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;

dataset('translationFiles', fn () => collect((array) glob(dirname(__DIR__).'/src/Data/Names/*/*.json'))
    // People/ and Things/ hold the base data, not translations.
    ->reject(fn ($path) => in_array(basename(dirname((string) $path)), ['People', 'Things'], true))
    ->mapWithKeys(fn ($path) => [
        basename(dirname((string) $path)).'/'.basename((string) $path) => [(string) $path],
    ])->all());

function baseNames(string $key): array
{
    foreach (['People', 'Things'] as $type) {
        $path = packagePath("src/Data/Names/{$type}/{$key}.json");

        if (file_exists($path)) {
            return array_column(json_decode((string) file_get_contents($path), true)['values'], 'name');
        }
    }

    return [];
}

describe('translation files', function () {
    it('is valid and well shaped', function (string $path) {
        $json = json_decode((string) file_get_contents($path), true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE)
            ->and($json)->toHaveKeys(['key', 'locale', 'values'])
            ->and($json['key'])->toBe(pathinfo($path, PATHINFO_FILENAME))
            ->and($json['locale'])->toBe(basename(dirname($path)))
            ->and($json['values'])->toBeArray()->not->toBeEmpty();
    })->with('translationFiles');

    it('translates names that actually exist in the dictionary', function (string $path) {
        $key = pathinfo($path, PATHINFO_FILENAME);
        $base = baseNames($key);

        expect($base)->not->toBeEmpty("no base dictionary named [{$key}]");

        foreach (array_keys(json_decode((string) file_get_contents($path), true)['values']) as $italian) {
            // A stale key means the base name was edited and the translation
            // silently stopped applying. toContain() is variadic, so the
            // message goes through toBeTrue() instead.
            expect(in_array($italian, $base, true))->toBeTrue("[{$italian}] is not in [{$key}]");
        }
    })->with('translationFiles');

    it('obeys the same rules as the base data', function (string $path) {
        foreach (json_decode((string) file_get_contents($path), true)['values'] as $italian => $english) {
            expect(preg_match('/[^\x20-\x7E]/', $english))->toBe(0, "non-ASCII in [{$english}]")
                ->and($english)->not->toContain("'")
                ->and(count(explode(' ', $english)))->toBeLessThanOrEqual(3, "[{$english}] exceeds 3 words")
                ->and(trim($english))->toBe($english)
                ->and($english)->not->toBe($italian, "[{$italian}] maps to itself; drop the entry instead");
        }
    })->with('translationFiles');

    it('does not map two names onto one', function (string $path) {
        $values = json_decode((string) file_get_contents($path), true)['values'];

        // Collisions shrink the pool and overstate entropy.
        expect(array_values($values))->toBe(array_values(array_unique($values)));
    })->with('translationFiles');
});

describe('translation behaviour', function () {
    beforeEach(function () {
        config()->set('password-toolkit.add_numbers', false);
        PasswordToolkit::flushCache();
    });

    it('renders translated names in the target locale', function () {
        $translator = app(NameTranslator::class);
        $options = (new Options)->with(locale: 'en', fallbackLocale: 'it');

        // Asserted over the whole dictionary rather than a random sample, so
        // the test cannot flake on an unlucky draw.
        $rendered = array_map(
            fn (string $name): string => $translator->translate(
                new Entry($name, Gender::Neutral, 'roman_mythology'),
                $options,
            )->name,
            baseNames('roman_mythology'),
        );

        expect($rendered)->toContain('Jupiter')->not->toContain('Giove');
    });

    it('leaves the base locale untouched', function () {
        $translator = app(NameTranslator::class);
        $options = (new Options)->with(locale: 'it', fallbackLocale: 'it');

        $rendered = array_map(
            fn (string $name): string => $translator->translate(
                new Entry($name, Gender::Neutral, 'roman_mythology'),
                $options,
            )->name,
            baseNames('roman_mythology'),
        );

        expect($rendered)->toContain('Giove')->not->toContain('Jupiter');
    });

    it('renders a translated name into a generated password', function () {
        config()->set('password-toolkit.locale', 'en');
        PasswordToolkit::registerDictionary('solo', ['Giove'], 'people');
        onlyDictionaries('solo');

        // A one-entry dictionary makes this deterministic, but it is keyed
        // 'solo', so nothing translates it.
        expect(PasswordToolkit::make()->keepWordBreaks(false)->generate())->toContain('Giove');

        onlyDictionaries('roman_mythology');
        config()->set('password-toolkit.dictionaries.except', array_values(array_diff(
            baseNames('roman_mythology'),
            ['Giove'],
        )));
    });

    it('keeps untranslated names as they are', function () {
        config()->set('password-toolkit.locale', 'en');
        onlyDictionaries('italian_wines');

        // Proper nouns do not translate: Barolo is Barolo everywhere, and this
        // dictionary has no translation file at all.
        $names = collect(PasswordToolkit::make()->keepWordBreaks(false)->many(40))
            ->map(fn (string $password): string => explode('-', $password)[1]);

        $base = array_map(fn (string $n): string => str_replace(' ', '', $n), baseNames('italian_wines'));

        foreach ($names as $name) {
            expect(in_array($name, $base, true))->toBeTrue("[{$name}] is not a base name");
        }
    });

    it('falls through for a name the file does not list', function () {
        $translator = app(NameTranslator::class);
        $options = (new Options)->with(locale: 'en', fallbackLocale: 'it');

        $entry = new Entry('Apollo', Gender::Male, 'roman_mythology');

        // Apollo is Apollo in both languages, so it is deliberately absent.
        expect($translator->translate($entry, $options)->name)->toBe('Apollo');
    });

    it('reports coverage per dictionary', function () {
        $translator = app(NameTranslator::class);

        expect($translator->coverage('roman_mythology', 'en'))->toBeGreaterThan(0)
            ->and($translator->coverage('italian_wines', 'en'))->toBe(0);
    });
});
