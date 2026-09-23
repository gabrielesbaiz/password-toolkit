<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Illuminate\Support\Facades\Artisan;

function readme(): string
{
    return (string) file_get_contents(packagePath('README.md'));
}

/**
 * The prose of the documentation site.
 *
 * The README used to be the documentation; it is now a landing card, and the
 * deep material lives in docs/. Entities are decoded so the PHP samples on the
 * page read as PHP: '=&gt;' is an arrow like any other.
 */
function docs(): string
{
    return html_entity_decode(
        (string) file_get_contents(packagePath('docs/index.html')),
        ENT_QUOTES | ENT_HTML5,
    );
}

/**
 * The documentation site plus the data it renders from.
 *
 * The dictionary catalogue is shipped to the page as JSON in docs/app.js, so a
 * search for a dictionary key has to cover both files.
 */
function docsCatalogue(): string
{
    return docs().(string) file_get_contents(packagePath('docs/app.js'));
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

    preg_match_all("/'([a-z_]+)'\s*=>/", docs(), $matches);

    $documented = collect($matches[1])->unique();
    $known = collect(array_keys($config))
        ->merge(array_keys($config['dictionaries']))
        ->merge(array_keys($config['strength']))
        // Dictionary keys used in examples, and the two dictionary types.
        ->merge(PasswordToolkit::dictionaries()->keys())
        ->merge(['people', 'things'])
        // Keys that appear in example payloads rather than as config.
        ->merge(['name', 'gender', 'password', 'report', 'email', 'must_change_password', 'pin', 'master', 'legacy'])
        ->merge(['very_weak', 'too_weak', 'weak', 'fair', 'strong', 'very_strong'])
        ->merge(['lower', 'upper', 'digits', 'symbols'])
        ->merge(['adjective', 'second_adjective', 'number', 'leetspeak_bonus', 'total'])
        ->merge(['values', 'type', 'key', 'products', 'company_products', 'team_nicknames', 'my_team'])
        // Keys of the arrays dictionaries()/groups() return, not config.
        ->merge(['label', 'icon', 'group', 'group_label', 'reach_label'])
        ->merge(['count', 'built_in', 'value', 'sample', 'locale'])
        // Keys of the array poolSizes() returns.
        ->merge(['names', 'adjectives'])
        // Keys removed in 2.0, named by the upgrade section.
        ->merge(['name_types', 'leetspeak_conversion']);

    expect($documented->diff($known)->all())->toBe([]);
});

it('names every removed 1.x key in the upgrade guide', function () {
    $upgrade = (string) file_get_contents(packagePath('UPGRADE.md'));

    foreach (['name_types', 'leetspeak_conversion', 'clearPoolCache', 'poolSizes', 'generate('] as $removed) {
        expect($upgrade)->toContain($removed);
    }
});

it('lists every built-in dictionary in the documentation', function () {
    $catalogue = docsCatalogue();

    foreach (PasswordToolkit::dictionaries()->keys() as $key) {
        expect($catalogue)->toContain((string) $key);
    }
});

it('reports the dictionary counts the documentation claims', function () {
    $dictionaries = PasswordToolkit::dictionaries();

    // Counts move every time a dictionary lands, and documentation that quietly
    // drifts out of step is worse than no number at all.
    expect(docs())
        ->toContain($dictionaries->count().' dictionaries')
        ->toContain(number_format($dictionaries->sum('count')).' names');

    // The README repeats both figures on its landing card; build/readme-tables.php
    // is what keeps them there.
    expect(readme())
        ->toContain($dictionaries->count().' dictionaries')
        ->toContain(number_format($dictionaries->sum('count')).' names');
});

it('links the files it references', function () {
    foreach (['UPGRADE.md', 'SECURITY.md', 'CONTRIBUTING.md', 'CHANGELOG.md', 'LICENSE.md'] as $file) {
        expect(readme())->toContain($file)
            ->and(file_exists(packagePath($file)))->toBeTrue("{$file} is linked but missing");
    }
});

// Removed: 'keeps the contents list in step with the headings'.
//
// It guarded a hand-written table of contents against the README's own '##'
// headings. The README is now a landing card of twelve sections with no
// contents list, so the assertion had nothing left to compare; navigation for
// the deep material lives in the documentation site's sidebar. The drift it
// was really protecting against — documentation falling behind the code — is
// still covered by the config-key and dictionary assertions above, now aimed
// at docs/ where that material moved.
