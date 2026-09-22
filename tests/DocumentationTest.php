<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Illuminate\Support\Facades\Artisan;

function readme(): string
{
    return (string) file_get_contents(packagePath('README.md'));
}

it('documents every artisan command it ships', function () {
    $commands = collect(array_keys(Artisan::all()))
        ->filter(fn (string $name): bool => str_starts_with($name, 'password-toolkit:'));

    expect($commands)->not->toBeEmpty();

    foreach ($commands as $command) {
        expect(readme())->toContain($command);
    }
});

it('documents only config keys that exist', function () {
    $config = require packagePath('config/password-toolkit.php');

    preg_match_all("/'([a-z_]+)'\s*=>/", readme(), $matches);

    $documented = collect($matches[1])->unique();
    $known = collect(array_keys($config))
        ->merge(array_keys($config['dictionaries']))
        ->merge(array_keys($config['strength']))
        // Keys that appear in example payloads rather than as config.
        ->merge(['name', 'gender', 'password', 'report', 'email', 'must_change_password', 'pin', 'master'])
        ->merge(['very_weak', 'weak', 'fair', 'strong', 'very_strong'])
        ->merge(['lower', 'upper', 'digits', 'symbols'])
        ->merge(['adjective', 'number', 'leetspeak_bonus', 'total'])
        ->merge(['values', 'type', 'key', 'products', 'company_products', 'team_nicknames', 'my_team'])
        // Keys of the arrays dictionaries()/groups() return, not config.
        ->merge(['label', 'description', 'icon', 'group', 'group_label', 'reach_label'])
        ->merge(['count', 'built_in', 'value', 'sample', 'locale']);

    expect($documented->diff($known)->all())->toBe([]);
});

it('names every removed 1.x key in the upgrade guide', function () {
    $upgrade = (string) file_get_contents(packagePath('UPGRADE.md'));

    foreach (['name_types', 'leetspeak_conversion', 'clearPoolCache', 'poolSizes', 'generate('] as $removed) {
        expect($upgrade)->toContain($removed);
    }
});

it('lists every built-in dictionary in the readme', function () {
    foreach (PasswordToolkit::dictionaries()->keys() as $key) {
        expect(readme())->toContain("`{$key}`");
    }
});

it('reports the dictionary counts the readme claims', function () {
    $dictionaries = PasswordToolkit::dictionaries();
    $people = $dictionaries->where('type', 'people');
    $things = $dictionaries->where('type', 'things');

    // Counts move every time a dictionary lands, and a README that quietly
    // drifts out of step is worse than no number at all.
    expect(readme())
        ->toContain($dictionaries->count().' dictionaries')
        ->toContain("**{$people->count()} of people**")
        ->toContain("**{$things->count()} of things**")
        ->toContain(number_format($people->sum('count')).' names')
        ->toContain(number_format($things->sum('count')).' names')
        ->toContain("People ({$people->count()})")
        ->toContain("Things ({$things->count()})");
});

it('links the files it references', function () {
    foreach (['UPGRADE.md', 'SECURITY.md', 'CONTRIBUTING.md', 'CHANGELOG.md', 'LICENSE.md'] as $file) {
        expect(readme())->toContain($file)
            ->and(file_exists(packagePath($file)))->toBeTrue("{$file} is linked but missing");
    }
});

it('keeps the contents list in step with the headings', function () {
    $readme = readme();

    preg_match_all('/^## (.+)$/m', $readme, $headings);
    preg_match_all('/^- \[(.+?)\]\(#/m', $readme, $listed);

    // Changelog is linked from the tail but deliberately not in the TOC.
    $expected = collect($headings[1])->reject(fn (string $h): bool => in_array($h, ['Contents', 'Changelog'], true));

    expect(collect($listed[1])->values()->all())->toBe($expected->values()->all());
});
