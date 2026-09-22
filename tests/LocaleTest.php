<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Dictionaries\AdjectiveResolver;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\Entry;
use Gabrielesbaiz\PasswordToolkit\Enums\AdjectivePosition;
use Gabrielesbaiz\PasswordToolkit\Enums\Gender;
use Gabrielesbaiz\PasswordToolkit\Exceptions\DictionaryNotFoundException;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;

function adjectiveNames(string $locale, string $key): array
{
    $path = packagePath("src/Data/Adjectives/{$locale}/{$key}.json");

    return array_column(json_decode((string) file_get_contents($path), true)['values'], 'name');
}

beforeEach(function () {
    config()->set('password-toolkit.add_numbers', false);
    config()->set('password-toolkit.dictionaries.enabled', '*');

    PasswordToolkit::flushCache();
});

it('prefers the themed file for the active locale', function () {
    config()->set('password-toolkit.locale', 'it');
    onlyDictionaries('star_wars');

    $adjective = explode('-', PasswordToolkit::generate());
    $adjective = end($adjective);

    expect(adjectiveNames('it', 'star_wars'))->toContain($adjective);
});

it('prefers the themed English pack over the default pool', function () {
    config()->set('password-toolkit.locale', 'en');
    onlyDictionaries('star_wars');

    $adjective = explode('-', PasswordToolkit::generate())[0];

    expect(adjectiveNames('en', 'star_wars'))->toContain($adjective);
});

it('falls back to the locale default pool when there is no themed file', function () {
    config()->set('password-toolkit.locale', 'en');

    // A runtime dictionary ships no adjectives of its own, so it must land on
    // en/_default.json.
    PasswordToolkit::registerDictionary('improvised', ['Whoever'], 'people');
    onlyDictionaries('improvised');

    $adjective = explode('-', PasswordToolkit::generate())[0];

    expect(adjectiveNames('en', '_default'))->toContain($adjective);
});

it('falls back to the fallback locale for an unknown locale', function () {
    config()->set('password-toolkit.locale', 'de');
    config()->set('password-toolkit.fallback_locale', 'it');
    onlyDictionaries('star_wars');

    $adjective = explode('-', PasswordToolkit::generate());
    $adjective = end($adjective);

    expect(adjectiveNames('it', 'star_wars'))->toContain($adjective);
});

it('throws when no pool resolves in either locale', function () {
    $resolver = (new AdjectiveResolver(packagePath('tests/fixtures/empty-adjectives')));

    $resolver->for(
        new Entry('Whoever', Gender::Neutral, 'nothing'),
        (new Options)->with(locale: 'xx', fallbackLocale: 'zz'),
    );
})->throws(DictionaryNotFoundException::class);

it('follows the application locale when none is configured', function () {
    config()->set('password-toolkit.locale', null);
    app()->setLocale('en');
    onlyDictionaries('star_wars');

    $adjective = explode('-', PasswordToolkit::generate())[0];

    expect(adjectiveNames('en', 'star_wars'))->toContain($adjective);
});

it('agrees with the gender of the name in Italian', function () {
    config()->set('password-toolkit.locale', 'it');

    $resolver = app(AdjectiveResolver::class);
    $options = Options::fromConfig();
    $pool = collect(json_decode(
        (string) file_get_contents(packagePath('src/Data/Adjectives/it/star_wars.json')),
        true,
    )['values'])->keyBy('name');

    foreach ([Gender::Male, Gender::Female] as $gender) {
        for ($i = 0; $i < 25; $i++) {
            $adjective = $resolver->for(new Entry('Test', $gender, 'star_wars'), $options);

            expect($pool[$adjective->name]['gender'])->toBeIn([$gender->value, 'neutral']);
        }
    }
});

it('treats every English adjective as neutral', function () {
    $values = json_decode(
        (string) file_get_contents(packagePath('src/Data/Adjectives/en/_default.json')),
        true,
    )['values'];

    expect(array_unique(array_column($values, 'gender')))->toBe(['neutral']);
});

it('reports a pool size per locale', function () {
    $resolver = app(AdjectiveResolver::class);

    expect($resolver->poolSize('star_wars', (new Options)->with(locale: 'it')))
        ->toBe(count(adjectiveNames('it', 'star_wars')))
        ->and($resolver->poolSize('star_wars', (new Options)->with(locale: 'en')))
        ->toBe(count(adjectiveNames('en', 'star_wars')));
});

describe('word order', function () {
    it('puts the adjective after the name in Italian', function () {
        config()->set('password-toolkit.locale', 'it');
        onlyDictionaries('star_wars');

        [$first, $second] = explode('-', PasswordToolkit::make()->keepWordBreaks(false)->generate());

        expect(adjectiveNames('it', 'star_wars'))->toContain($second)->not->toContain($first);
    });

    it('puts the adjective before the name in English', function () {
        config()->set('password-toolkit.locale', 'en');
        onlyDictionaries('star_wars');

        [$first, $second] = explode('-', PasswordToolkit::make()->keepWordBreaks(false)->generate());

        expect(adjectiveNames('en', 'star_wars'))->toContain($first)->not->toContain($second);
    });

    it('reads the order from the locale pack', function () {
        $resolver = app(AdjectiveResolver::class);

        expect($resolver->positionFor((new Options)->with(locale: 'it')))->toBe(AdjectivePosition::After)
            ->and($resolver->positionFor((new Options)->with(locale: 'en')))->toBe(AdjectivePosition::Before);
    });

    it('falls back to the fallback locale order for an unknown locale', function () {
        $resolver = app(AdjectiveResolver::class);

        expect($resolver->positionFor((new Options)->with(locale: 'de', fallbackLocale: 'en')))
            ->toBe(AdjectivePosition::Before)
            ->and($resolver->positionFor((new Options)->with(locale: 'de', fallbackLocale: 'it')))
            ->toBe(AdjectivePosition::After);
    });

    it('defaults to After when no pack declares an order', function () {
        $resolver = new AdjectiveResolver(packagePath('tests/fixtures/empty-adjectives'));

        expect($resolver->positionFor((new Options)->with(locale: 'xx', fallbackLocale: 'zz')))
            ->toBe(AdjectivePosition::After);
    });

    it('can be overridden in config', function () {
        config()->set('password-toolkit.locale', 'it');
        config()->set('password-toolkit.adjective_position', 'before');
        onlyDictionaries('star_wars');

        [$first] = explode('-', PasswordToolkit::make()->keepWordBreaks(false)->generate());

        expect(adjectiveNames('it', 'star_wars'))->toContain($first);
    });

    it('can be overridden on the builder', function () {
        config()->set('password-toolkit.locale', 'en');
        onlyDictionaries('star_wars');

        $password = PasswordToolkit::make()
            ->keepWordBreaks(false)
            ->adjectiveAt(AdjectivePosition::After)
            ->generate();

        expect(adjectiveNames('en', 'star_wars'))->toContain(explode('-', $password)[1]);
    });

    it('goes back to following the locale when the override is cleared', function () {
        $builder = PasswordToolkit::make()->adjectiveAt('before')->adjectiveAt(null);

        expect($builder->options()->adjectivePosition)->toBeNull();
    });

    it('rejects an unknown position', function () {
        AdjectivePosition::parse('sideways');
    })->throws(Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException::class);

    it('orders the two words', function () {
        expect(AdjectivePosition::Before->order('Name', 'Adj'))->toBe(['Adj', 'Name'])
            ->and(AdjectivePosition::After->order('Name', 'Adj'))->toBe(['Name', 'Adj']);
    });
});
