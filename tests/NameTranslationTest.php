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

/**
 * Every name in a dictionary, as the given locale would render it.
 */
function renderAll(string $key, string $locale): array
{
    $translator = app(NameTranslator::class);
    $options = (new Options)->with(locale: $locale, fallbackLocale: 'en');
    $source = sourceLocale($key);

    return array_map(
        fn (string $name): string => $translator->translate(
            new Entry($name, Gender::Neutral, $key, $source),
            $options,
        )->name,
        baseNames($key),
    );
}

function sourceLocale(string $key): ?string
{
    foreach (['People', 'Things'] as $type) {
        $path = packagePath("src/Data/Names/{$type}/{$key}.json");

        if (file_exists($path)) {
            return json_decode((string) file_get_contents($path), true)['locale'] ?? null;
        }
    }

    return null;
}

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
        // roman_mythology is an English-base dictionary now, so Italian is the
        // translation. Asserted over the whole dictionary rather than a random
        // sample, so the test cannot flake on an unlucky draw.
        $rendered = renderAll('roman_mythology', 'it');

        expect($rendered)->toContain('Giove')->not->toContain('Jupiter');
    });

    it('leaves a dictionary already in the active language untouched', function () {
        expect(renderAll('roman_mythology', 'en'))->toContain('Jupiter')->not->toContain('Giove');
    });

    it('translates an Italian-base dictionary into English', function () {
        // italian_monuments is the other direction: the subject is Italian, so
        // the base stays Italian and English is the overlay.
        expect(renderAll('italian_monuments', 'en'))->toContain('Colosseum')->not->toContain('Colosseo');
    });

    it('gives an unsupported locale the fallback rendering', function () {
        // French has no packs, so it should land on English rather than on
        // whatever the base happens to be.
        expect(renderAll('italian_monuments', 'fr'))->toContain('Colosseum')
            ->and(renderAll('roman_mythology', 'fr'))->toContain('Jupiter');
    });

    it('renders a translated name into a generated password', function () {
        config()->set('password-toolkit.locale', 'it');
        onlyDictionaries('harry_potter');

        $names = collect(PasswordToolkit::make()->keepWordBreaks(false)->many(60))
            ->map(fn (string $password): string => explode('-', $password)[0]);

        // Italian readers know him as Silente, and the base now says Dumbledore.
        expect($names)->not->toContain('Dumbledore');
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
        $options = (new Options)->with(locale: 'it', fallbackLocale: 'en');

        $entry = new Entry('Apollo', Gender::Male, 'roman_mythology', 'en');

        // Apollo is Apollo in both languages, so it is deliberately absent.
        expect($translator->translate($entry, $options)->name)->toBe('Apollo');
    });

    it('reports coverage per dictionary', function () {
        $translator = app(NameTranslator::class);

        expect($translator->coverage('roman_mythology', 'it'))->toBeGreaterThan(0)
            ->and($translator->coverage('italian_monuments', 'en'))->toBeGreaterThan(0)
            ->and($translator->coverage('italian_wines', 'en'))->toBe(0);
    });
});

describe('source locale', function () {
    it('is declared by every built-in dictionary', function () {
        foreach ((array) glob(packagePath('src/Data/Names/{People,Things}/*.json'), GLOB_BRACE) as $file) {
            $json = json_decode((string) file_get_contents((string) $file), true);

            // Without this the translator cannot tell "nothing to translate"
            // from "not translated yet".
            expect($json['locale'] ?? null)->toBeIn(['en', 'it'], basename((string) $file).' declares none');
        }
    });

    it('never ships a translation file for a dictionary already in that language', function () {
        foreach ((array) glob(packagePath('src/Data/Names/*/*.json')) as $file) {
            $locale = basename(dirname((string) $file));

            if (in_array($locale, ['People', 'Things'], true)) {
                continue;
            }

            $key = basename((string) $file, '.json');

            expect(sourceLocale($key))->not->toBe(
                $locale,
                "[{$key}] is already in [{$locale}] but ships a {$locale}/ overlay",
            );
        }
    });

    it('keeps dictionaries about Italian subjects on an Italian base', function () {
        // Barolo is Barolo in every language. Calling these English-base would
        // be mislabelling, not internationalising.
        foreach (['italian_wines', 'italian_pasta_shapes', 'italian_volcanoes', 'italian_cyclists'] as $key) {
            expect(sourceLocale($key))->toBe('it');
        }
    });

    it('puts dictionaries about non-Italian subjects on an English base', function () {
        foreach (['harry_potter', 'star_wars', 'disney_characters', 'greek_mythology', 'roman_emperors'] as $key) {
            expect(sourceLocale($key))->toBe('en');
        }
    });

    it('holds the original name in the base, not the dub', function () {
        expect(baseNames('harry_potter'))->toContain('Albus Dumbledore')->not->toContain('Albus Silente')
            ->and(baseNames('roman_mythology'))->toContain('Jupiter')->not->toContain('Giove')
            ->and(baseNames('disney_characters'))->toContain('Mickey Mouse')->not->toContain('Topolino');
    });

    it('still renders Italian for an Italian reader', function () {
        expect(renderAll('harry_potter', 'it'))->toContain('Albus Silente')
            ->and(renderAll('disney_characters', 'it'))->toContain('Topolino')
            ->and(renderAll('italian_wines', 'it'))->toContain('Barolo');
    });
});
