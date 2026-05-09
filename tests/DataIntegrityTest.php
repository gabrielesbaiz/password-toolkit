<?php

$peopleDir = __DIR__ . '/../src/Data/Names/People';
$thingsDir = __DIR__ . '/../src/Data/Names/Things';
$adjDir    = __DIR__ . '/../src/Data/Adjectives';

$peopleFiles = glob($peopleDir . '/*.json');
$thingsFiles = glob($thingsDir . '/*.json');
$adjFiles    = glob($adjDir . '/*.json');

$nameFiles = array_merge($peopleFiles, $thingsFiles);

$validGenders = ['male', 'female', 'neutral'];

it('has at least one name file and one adjective file', function () use ($nameFiles, $adjFiles) {
    expect(count($nameFiles))->toBeGreaterThan(0);
    expect(count($adjFiles))->toBeGreaterThan(0);
});

dataset('nameFiles', fn () => collect(array_merge(
    glob(__DIR__ . '/../src/Data/Names/People/*.json'),
    glob(__DIR__ . '/../src/Data/Names/Things/*.json')
))->mapWithKeys(fn ($p) => [basename($p) => [$p]])->all());

dataset('adjectiveFiles', fn () => collect(glob(__DIR__ . '/../src/Data/Adjectives/*.json'))
    ->mapWithKeys(fn ($p) => [basename($p) => [$p]])->all());

dataset('peopleFiles', fn () => collect(glob(__DIR__ . '/../src/Data/Names/People/*.json'))
    ->mapWithKeys(fn ($p) => [basename($p) => [$p]])->all());

dataset('thingsFiles', fn () => collect(glob(__DIR__ . '/../src/Data/Names/Things/*.json'))
    ->mapWithKeys(fn ($p) => [basename($p) => [$p]])->all());

it('name file is valid JSON with expected schema', function (string $path) use ($validGenders) {
    $raw = file_get_contents($path);
    $json = json_decode($raw, true);
    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($json)->toHaveKeys(['name', 'type', 'values']);
    expect($json['name'])->toBe(pathinfo($path, PATHINFO_FILENAME));
    expect($json['type'])->toBeIn(['people', 'things']);
    expect($json['values'])->toBeArray()->not->toBeEmpty();

    foreach ($json['values'] as $entry) {
        expect($entry)->toHaveKeys(['name', 'gender']);
        expect($entry['name'])->toBeString()->not->toBeEmpty();
        expect($entry['gender'])->toBeIn($validGenders);
    }
})->with('nameFiles');

it('name entries are ASCII-only (no accents/apostrophes)', function (string $path) {
    $json = json_decode(file_get_contents($path), true);
    foreach ($json['values'] as $entry) {
        expect(preg_match('/[^\x20-\x7E]/', $entry['name']))->toBe(0, "non-ASCII in {$entry['name']} ({$path})");
        expect($entry['name'])->not->toContain("'");
        expect($entry['name'])->not->toContain('’');
    }
})->with('nameFiles');

it('adjective file is valid JSON with expected schema', function (string $path) use ($validGenders) {
    $raw = file_get_contents($path);
    $json = json_decode($raw, true);
    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($json)->toBeArray()->not->toBeEmpty();
    foreach ($json as $entry) {
        expect($entry)->toHaveKeys(['name', 'gender']);
        expect($entry['name'])->toBeString()->not->toBeEmpty();
        expect($entry['gender'])->toBeIn($validGenders);
    }
})->with('adjectiveFiles');

it('adjective entries cover all genders required for matching name file', function (string $path) {
    $name = pathinfo($path, PATHINFO_FILENAME);
    $adjPath = __DIR__ . '/../src/Data/Adjectives/' . $name . '_adjectives.json';
    expect(file_exists($adjPath))->toBeTrue("missing adjective file for {$name}");

    $names = json_decode(file_get_contents($path), true)['values'];
    $adjs = json_decode(file_get_contents($adjPath), true);
    $adjGenders = collect($adjs)->pluck('gender')->unique();

    $usedGenders = collect($names)->pluck('gender')->unique();
    foreach ($usedGenders as $g) {
        $hasMatch = $adjGenders->contains($g) || $adjGenders->contains('neutral');
        expect($hasMatch)->toBeTrue("no adjective for gender {$g} in {$name}");
    }
})->with('nameFiles');

it('every name file has a matching key in config', function (string $path) {
    $name = pathinfo($path, PATHINFO_FILENAME);
    $cfg = require __DIR__ . '/../config/password-toolkit.php';
    $merged = array_merge($cfg['name_types']['people'], $cfg['name_types']['things']);
    expect(array_key_exists($name, $merged))->toBeTrue("config missing entry for {$name}");
})->with('nameFiles');

it('people file is in people config and things in things config', function (string $path) {
    $name = pathinfo($path, PATHINFO_FILENAME);
    $cfg = require __DIR__ . '/../config/password-toolkit.php';
    expect(array_key_exists($name, $cfg['name_types']['people']))->toBeTrue();
})->with('peopleFiles');

it('things file mapped under things config', function (string $path) {
    $name = pathinfo($path, PATHINFO_FILENAME);
    $cfg = require __DIR__ . '/../config/password-toolkit.php';
    expect(array_key_exists($name, $cfg['name_types']['things']))->toBeTrue();
})->with('thingsFiles');

it('config keys all reference existing files', function () {
    $cfg = require __DIR__ . '/../config/password-toolkit.php';
    foreach ($cfg['name_types']['people'] as $key => $_) {
        expect(file_exists(__DIR__ . '/../src/Data/Names/People/' . $key . '.json'))->toBeTrue("missing People/{$key}.json");
    }
    foreach ($cfg['name_types']['things'] as $key => $_) {
        expect(file_exists(__DIR__ . '/../src/Data/Names/Things/' . $key . '.json'))->toBeTrue("missing Things/{$key}.json");
    }
});

it('adjective (name,gender) tuples are unique within a file', function (string $path) {
    $json = json_decode(file_get_contents($path), true);
    $tuples = collect($json)->map(fn ($e) => $e['name'] . '|' . $e['gender']);
    expect($tuples->count())->toBe($tuples->unique()->count(), "duplicate (name,gender) in {$path}");
})->with('adjectiveFiles');
