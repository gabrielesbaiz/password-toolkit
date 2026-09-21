<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Enums\Leetspeak;
use Gabrielesbaiz\PasswordToolkit\Enums\NumbersPosition;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Generator\PasswordBuilder;
use Gabrielesbaiz\PasswordToolkit\Support\StrengthReport;

beforeEach(function () {
    config()->set('password-toolkit.separator_symbol', '-');
    config()->set('password-toolkit.add_numbers', true);
    config()->set('password-toolkit.numbers_digits', 4);
    config()->set('password-toolkit.leetspeak_conversion', 'none');
    config()->set('password-toolkit.dictionaries.enabled', '*');

    PasswordToolkit::flushCache();
});

it('returns a builder from make()', function () {
    expect(PasswordToolkit::make())->toBeInstanceOf(PasswordBuilder::class);
});

it('does not mutate the builder it was derived from', function () {
    $base = PasswordToolkit::make()->only(['star_wars']);
    $derived = $base->separator('_')->digits(6);

    expect($base->options()->separator)->toBe('-')
        ->and($base->options()->numbersDigits)->toBe(4)
        ->and($derived->options()->separator)->toBe('_')
        ->and($derived->options()->numbersDigits)->toBe(6);
});

it('restricts to the given dictionaries', function () {
    $names = collect(PasswordToolkit::make()->only('star_wars')->withoutNumbers()->many(20))
        ->map(fn (string $password): string => explode('-', $password)[0]);

    $starWars = collect(json_decode(
        (string) file_get_contents(packagePath('src/Data/Names/People/star_wars.json')),
        true,
    )['values'])->map(fn (array $entry): string => explode(' ', $entry['name'])[0]);

    expect($names->diff($starWars)->all())->toBe([]);
});

it('excludes the given dictionaries', function () {
    expect(PasswordToolkit::make()->except('star_wars')->options()->except)->toBe(['star_wars']);
});

it('restricts to a type', function () {
    $types = PasswordToolkit::dictionaries(PasswordToolkit::make()->types('things')->options())
        ->pluck('type')
        ->unique();

    expect($types->all())->toBe(['things']);
});

it('overrides the separator', function () {
    expect(PasswordToolkit::make()->only('star_wars')->separator('+')->generate())->toContain('+');
});

it('drops the numeric segment', function () {
    expect(PasswordToolkit::make()->only('star_wars')->withoutNumbers()->generate())
        ->not->toMatch('/\d/');
});

it('moves the numeric segment', function () {
    expect(PasswordToolkit::make()->only('star_wars')->numbersAt(NumbersPosition::Start)->generate())
        ->toMatch('/^\d{4}-/');
});

it('accepts a string position and leet mode', function () {
    $options = PasswordToolkit::make()->numbersAt('middle')->leet('advanced')->options();

    expect($options->numbersPosition)->toBe(NumbersPosition::Middle)
        ->and($options->leetspeak)->toBe(Leetspeak::Advanced);
});

it('switches locale for one call only', function () {
    $english = PasswordToolkit::make()->only('star_wars')->locale('en')->withoutNumbers()->generate();

    // English leads with the adjective.
    $adjective = explode('-', $english)[0];

    $pool = array_column(json_decode(
        (string) file_get_contents(packagePath('src/Data/Adjectives/en/_default.json')),
        true,
    )['values'], 'name');

    expect($pool)->toContain($adjective)
        ->and(config('password-toolkit.locale'))->toBeNull();
});

it('keeps or strips word breaks', function () {
    $kept = PasswordToolkit::make()->only('star_wars')->keepWordBreaks()->withoutNumbers()->many(20);
    $stripped = PasswordToolkit::make()->only('star_wars')->keepWordBreaks(false)->withoutNumbers()->many(20);

    expect(collect($kept)->contains(fn (string $p): bool => substr_count($p, '-') > 1))->toBeTrue()
        ->and(collect($stripped)->every(fn (string $p): bool => substr_count($p, '-') === 1))->toBeTrue();
});

it('generates batches and reports', function () {
    expect(PasswordToolkit::make()->only('star_wars')->many(3))->toHaveCount(3)
        ->and(PasswordToolkit::make()->only('star_wars')->withReport())->toHaveKeys(['password', 'report'])
        ->and(PasswordToolkit::make()->only('star_wars')->manyWithReport(2))->toHaveCount(2);
});

it('reports against the builder options, not the config', function () {
    ['report' => $report] = PasswordToolkit::make()->only('star_wars')->withReport();

    expect($report)->toBeInstanceOf(StrengthReport::class)
        ->and($report->components['name'])->toBeLessThan(
            PasswordToolkit::structuralReport('x')->components['name'],
        );
});

it('casts to a password when used as a string', function () {
    expect((string) PasswordToolkit::make()->only('star_wars'))->toBeString()->not->toBeEmpty();
});
