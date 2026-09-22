<?php

declare(strict_types=1);

function glossary(): array
{
    return json_decode((string) file_get_contents(packagePath('src/Data/Adjectives/_glossary.it-en.json')), true);
}

function adjectiveWords(string $locale, string $key): array
{
    $path = packagePath("src/Data/Adjectives/{$locale}/{$key}.json");

    return file_exists($path)
        ? array_column(json_decode((string) file_get_contents($path), true)['values'], 'name')
        : [];
}

it('translates every Italian adjective it ships', function () {
    $values = glossary()['values'];

    $lemma = static fn (string $word, string $gender): string => $gender === 'neutral'
        ? mb_strtolower($word)
        : (string) preg_replace('/[oa]$/u', '', mb_strtolower($word));

    $covered = [];

    foreach ((array) glob(packagePath('src/Data/Adjectives/it/*.json')) as $file) {
        foreach (json_decode((string) file_get_contents((string) $file), true)['values'] as $entry) {
            if (isset($values[$entry['name']])) {
                $covered[$lemma($entry['name'], $entry['gender'])] = true;
            }
        }
    }

    $missing = [];

    foreach ((array) glob(packagePath('src/Data/Adjectives/it/*.json')) as $file) {
        foreach (json_decode((string) file_get_contents((string) $file), true)['values'] as $entry) {
            if (! isset($covered[$lemma($entry['name'], $entry['gender'])])) {
                $missing[$entry['name']] = true;
            }
        }
    }

    expect(array_keys($missing))->toBe([]);
});

it('maps every glossary term to a single ASCII word', function () {
    foreach (glossary()['values'] as $italian => $english) {
        expect($english)->toMatch('/^[A-Z][a-z]+$/', "[{$italian}] maps to [{$english}]");
    }
});

it('ships an English pack for every Italian themed pack', function () {
    foreach ((array) glob(packagePath('src/Data/Adjectives/it/*.json')) as $file) {
        $key = basename((string) $file, '.json');

        expect(file_exists(packagePath("src/Data/Adjectives/en/{$key}.json")))
            ->toBeTrue("no English pack for [{$key}]");
    }
});

it('regenerates byte-identical files from the glossary', function () {
    // If this fails, someone hand-edited an English pack instead of the
    // glossary, and the next re-run would silently revert their change.
    $before = [];

    foreach ((array) glob(packagePath('src/Data/Adjectives/en/*.json')) as $file) {
        $before[(string) $file] = (string) file_get_contents((string) $file);
    }

    exec('php '.escapeshellarg(packagePath('build/build-adjectives.php')).' 2>&1', $output, $status);

    expect($status)->toBe(0, implode("\n", $output));

    foreach ($before as $file => $contents) {
        expect((string) file_get_contents($file))->toBe($contents, basename($file).' is out of step with the glossary');
    }
})->skip(! is_file(dirname(__DIR__).'/build/build-adjectives.php'), 'build script not present');

it('keeps English packs free of gendered duplicates', function () {
    foreach ((array) glob(packagePath('src/Data/Adjectives/en/*.json')) as $file) {
        $words = array_column(json_decode((string) file_get_contents((string) $file), true)['values'], 'name');

        expect($words)->toBe(array_unique($words), basename((string) $file).' has duplicates');
    }
});

it('gives every themed English pack enough adjectives to be worth having', function () {
    foreach ((array) glob(packagePath('src/Data/Adjectives/en/*.json')) as $file) {
        $count = count(json_decode((string) file_get_contents((string) $file), true)['values']);

        // Below about ten the themed pack costs more entropy than the theme is
        // worth, and _default would serve better.
        expect($count)->toBeGreaterThanOrEqual(10, basename((string) $file).' has only '.$count);
    }
});

it('gives every dictionary a themed adjective pack in both languages', function () {
    // The default pool is a fallback, not a plan. A dictionary without its own
    // vocabulary produced things like Magic-Johnson-Corposo — a basketball
    // player described with a wine word.
    foreach ((array) glob(packagePath('src/Data/Names/{People,Things}/*.json'), GLOB_BRACE) as $file) {
        $key = basename((string) $file, '.json');

        foreach (['it', 'en'] as $locale) {
            expect(file_exists(packagePath("src/Data/Adjectives/{$locale}/{$key}.json")))
                ->toBeTrue("no {$locale} adjective pack for [{$key}]");
        }
    }
});

it('keeps the default pool free of domain-bound vocabulary', function () {
    // _default was once the union of every themed pack, which is why it was
    // full of food and wine terms. It is the pool a dictionary falls back to,
    // so it has to suit a person, a place or a thing equally.
    $culinary = [
        'Corposo', 'Gustoso', 'Saporito', 'Cremoso', 'Delizioso', 'Goloso', 'Fragrante',
        'Succulento', 'Tannico', 'Fruttato', 'Speziato', 'Salato', 'Croccante', 'Filante',
        'Profumato', 'Aromatico', 'Balsamico', 'Affumicato', 'Alcolico', 'Stagionato',
    ];

    $pool = array_column(
        json_decode((string) file_get_contents(packagePath('src/Data/Adjectives/it/_default.json')), true)['values'],
        'name',
    );

    expect(array_values(array_intersect($pool, $culinary)))->toBe([]);
});

it('caps a themed pack so it stays sharp', function () {
    foreach ((array) glob(packagePath('src/Data/Adjectives/it/*.json')) as $file) {
        if (basename((string) $file) === '_default.json') {
            continue;
        }

        $values = json_decode((string) file_get_contents((string) $file), true)['values'];
        $lemmas = count(array_unique(array_map(
            static fn (array $entry): string => $entry['gender'] === 'neutral'
                ? mb_strtolower($entry['name'])
                : (string) preg_replace('/[oa]$/u', '', mb_strtolower($entry['name'])),
            $values,
        )));

        // A long themed pack dissolves back into a generic one.
        expect($lemmas)->toBeLessThanOrEqual(20, basename((string) $file).' has '.$lemmas.' lemmas');
    }
});
