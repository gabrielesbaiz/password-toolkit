<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Dictionaries\AdjectiveResolver;

$validGenders = ['male', 'female', 'neutral'];

dataset('nameFiles', fn () => collect(array_merge(
    (array) glob(dirname(__DIR__).'/src/Data/Names/People/*.json'),
    (array) glob(dirname(__DIR__).'/src/Data/Names/Things/*.json'),
))->mapWithKeys(fn ($path) => [basename((string) $path) => [(string) $path]])->all());

dataset('adjectiveFiles', fn () => collect((array) glob(dirname(__DIR__).'/src/Data/Adjectives/*/*.json'))
    ->mapWithKeys(fn ($path) => [
        basename(dirname((string) $path)).'/'.basename((string) $path) => [(string) $path],
    ])->all());

dataset('locales', fn () => collect((array) glob(dirname(__DIR__).'/src/Data/Adjectives/*', GLOB_ONLYDIR))
    ->mapWithKeys(fn ($path) => [basename((string) $path) => [basename((string) $path)]])->all());

it('ships names and adjectives', function () {
    expect(glob(packagePath('src/Data/Names/People/*.json')))->not->toBeEmpty()
        ->and(glob(packagePath('src/Data/Adjectives/*/*.json')))->not->toBeEmpty();
});

it('has a valid name file', function (string $path) use ($validGenders) {
    $json = json_decode((string) file_get_contents($path), true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($json)->toBeArray()
        ->and($json['type'])->toBeIn(['people', 'things'])
        ->and($json['values'])->toBeArray()->not->toBeEmpty();

    // The file may key itself either way; the loader uses the filename.
    expect($json['key'] ?? $json['name'])->toBe(pathinfo($path, PATHINFO_FILENAME));

    foreach ($json['values'] as $entry) {
        expect($entry)->toHaveKeys(['name', 'gender'])
            ->and($entry['name'])->toBeString()->not->toBeEmpty()
            ->and($entry['gender'])->toBeIn($validGenders);
    }
})->with('nameFiles');

it('has ASCII-only names', function (string $path) {
    foreach (json_decode((string) file_get_contents($path), true)['values'] as $entry) {
        expect(preg_match('/[^\x20-\x7E]/', $entry['name']))
            ->toBe(0, "non-ASCII in {$entry['name']} ({$path})");
        expect($entry['name'])->not->toContain("'")->not->toContain('’');
    }
})->with('nameFiles');

it('caps names at three words', function (string $path) {
    foreach (json_decode((string) file_get_contents($path), true)['values'] as $entry) {
        $words = count(explode(' ', $entry['name']));

        expect($words)->toBeLessThanOrEqual(3, "{$entry['name']} in ".basename($path)." has {$words} words");
    }
})->with('nameFiles');

it('has a valid adjective file', function (string $path) use ($validGenders) {
    $json = json_decode((string) file_get_contents($path), true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($json)->toHaveKeys(['key', 'locale', 'values'])
        ->and($json['key'])->toBe(pathinfo($path, PATHINFO_FILENAME))
        ->and($json['locale'])->toBe(basename(dirname($path)))
        ->and($json['values'])->toBeArray()->not->toBeEmpty();

    foreach ($json['values'] as $entry) {
        expect($entry)->toHaveKeys(['name', 'gender'])
            ->and($entry['name'])->toBeString()->not->toBeEmpty()
            ->and($entry['gender'])->toBeIn($validGenders);
    }
})->with('adjectiveFiles');

it('has single-word ASCII adjectives', function (string $path) {
    foreach (json_decode((string) file_get_contents($path), true)['values'] as $entry) {
        expect(preg_match('/[^\x20-\x7E]/', $entry['name']))->toBe(0, "non-ASCII: {$entry['name']}")
            ->and($entry['name'])->not->toContain(' ');
    }
})->with('adjectiveFiles');

it('has unique name+gender pairs per adjective file', function (string $path) {
    $tuples = collect(json_decode((string) file_get_contents($path), true)['values'])
        ->map(fn (array $entry): string => $entry['name'].'|'.$entry['gender']);

    expect($tuples->count())->toBe($tuples->unique()->count(), "duplicates in {$path}");
})->with('adjectiveFiles');

it('ships a default adjective pool for every locale', function (string $locale) {
    expect(file_exists(packagePath("src/Data/Adjectives/{$locale}/".AdjectiveResolver::DEFAULT_KEY.'.json')))
        ->toBeTrue("locale [{$locale}] has no default pool");
})->with('locales');

it('covers every gender used by a name file', function (string $path) {
    $key = pathinfo($path, PATHINFO_FILENAME);
    $names = json_decode((string) file_get_contents($path), true)['values'];

    $adjectivePath = packagePath("src/Data/Adjectives/it/{$key}.json");
    $pool = file_exists($adjectivePath)
        ? json_decode((string) file_get_contents($adjectivePath), true)['values']
        : json_decode((string) file_get_contents(packagePath('src/Data/Adjectives/it/_default.json')), true)['values'];

    $available = collect($pool)->pluck('gender')->unique();

    foreach (collect($names)->pluck('gender')->unique() as $gender) {
        expect($available->contains($gender) || $available->contains('neutral'))
            ->toBeTrue("no Italian adjective agrees with [{$gender}] in [{$key}]");
    }
})->with('nameFiles');

it('has no config key that does not resolve to a dictionary', function () {
    $enabled = config('password-toolkit.dictionaries.enabled');

    // The shipped config enables everything, so there is no per-key list to
    // keep in sync with the filesystem any more. That was the point.
    expect($enabled)->toBe('*')
        ->and(config('password-toolkit.dictionaries.except'))->toBe([]);
});

it('declares a word order in every locale default pool', function (string $locale) {
    $path = packagePath("src/Data/Adjectives/{$locale}/".AdjectiveResolver::DEFAULT_KEY.'.json');
    $declared = json_decode((string) file_get_contents($path), true)['adjective_position'] ?? null;

    // Without this a new locale silently inherits Italian word order, which
    // reads as broken to anyone who speaks the new one.
    expect($declared)->toBeIn(['before', 'after'], "locale [{$locale}] declares no adjective_position");
})->with('locales');
