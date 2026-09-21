<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Dictionaries\Dictionary;
use Gabrielesbaiz\PasswordToolkit\Dictionaries\FileDictionaryRepository;
use Gabrielesbaiz\PasswordToolkit\Enums\Gender;
use Gabrielesbaiz\PasswordToolkit\Exceptions\DictionaryNotFoundException;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;

beforeEach(function () {
    config()->set('password-toolkit.dictionaries.enabled', '*');
    config()->set('password-toolkit.dictionaries.except', []);
    config()->set('password-toolkit.dictionaries.types', ['people', 'things']);
    config()->set('password-toolkit.dictionaries.paths', []);
    config()->set('password-toolkit.dictionaries.custom', []);

    PasswordToolkit::flushCache();
});

describe('selection', function () {
    it('lists every built-in dictionary', function () {
        expect(PasswordToolkit::dictionaries())->toHaveCount(91);
    });

    it('honours except', function () {
        config()->set('password-toolkit.dictionaries.except', ['star_wars']);

        expect(PasswordToolkit::dictionaries(Options::fromConfig())->keys())->not->toContain('star_wars');
    });

    it('honours types', function () {
        config()->set('password-toolkit.dictionaries.types', ['people']);

        $types = PasswordToolkit::dictionaries(Options::fromConfig())->pluck('type')->unique();

        expect($types->all())->toBe(['people']);
    });

    it('flattens every selected dictionary into one pool', function () {
        onlyDictionaries('star_wars', 'back_to_the_future');

        $repository = app(FileDictionaryRepository::class);
        $options = Options::fromConfig();
        $sizes = collect($repository->enabled($options))->map->count();

        expect($repository->entries($options))->toHaveCount($sizes->sum());
    });

    it('picks names uniformly across entries, not across files', function () {
        // A file-first picker shows a one-entry dictionary as often as a
        // nine-entry one. An entry-first picker has to favour the larger pool
        // in proportion to its size.
        PasswordToolkit::registerDictionary('tiny', ['Alpha']);
        PasswordToolkit::registerDictionary('big', [
            'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf', 'Hotel', 'India', 'Juliett',
        ]);
        onlyDictionaries('tiny', 'big');

        $tiny = collect(PasswordToolkit::generateMany(1000))
            ->filter(fn (string $password): bool => str_starts_with($password, 'Alpha-'))
            ->count();

        // Expected share is 1/10. A file-first picker would land near 1/2.
        expect($tiny / 1000)->toBeGreaterThan(0.05)->toBeLessThan(0.18);
    });
});

describe('custom dictionaries', function () {
    it('loads one defined inline in config', function () {
        config()->set('password-toolkit.dictionaries.custom', [
            'company_products' => [
                'type' => 'things',
                'values' => [['name' => 'Orbit', 'gender' => 'neutral']],
            ],
        ]);
        onlyDictionaries('company_products');

        expect(PasswordToolkit::generate())->toStartWith('Orbit-');
    });

    it('loads one registered at runtime', function () {
        PasswordToolkit::registerDictionary('team', [['name' => 'Gabriele', 'gender' => 'male']], 'people');
        onlyDictionaries('team');

        expect(PasswordToolkit::generate())->toStartWith('Gabriele-');
    });

    it('accepts a bare list of names', function () {
        PasswordToolkit::registerDictionary('team', ['Solo Name']);
        onlyDictionaries('team');

        expect(PasswordToolkit::generate())->toStartWith('Solo-Name-');
    });

    it('loads one from a configured path', function () {
        $directory = sys_get_temp_dir().'/ptk-'.bin2hex(random_bytes(4));
        mkdir($directory.'/en', 0o755, true);

        file_put_contents($directory.'/crew.json', json_encode([
            'key' => 'crew', 'type' => 'people',
            'values' => [['name' => 'Ada', 'gender' => 'female']],
        ]));
        file_put_contents($directory.'/en/crew.json', json_encode([
            'key' => 'crew', 'locale' => 'en',
            'values' => [['name' => 'Brilliant', 'gender' => 'neutral']],
        ]));

        config()->set('password-toolkit.dictionaries.paths', [$directory]);
        config()->set('password-toolkit.locale', 'en');
        onlyDictionaries('crew');

        // The resolver caches its search roots, so it has to be rebuilt to see
        // a path added after boot.
        PasswordToolkit::clearResolvedInstances();
        app()->forgetInstance(Gabrielesbaiz\PasswordToolkit\Dictionaries\AdjectiveResolver::class);
        app()->forgetInstance(Gabrielesbaiz\PasswordToolkit\PasswordToolkit::class);

        // Adjective from the path's en/crew.json, name from its crew.json —
        // in English order, adjective first.
        expect(PasswordToolkit::generate())->toStartWith('Brilliant-Ada');

        array_map('unlink', glob($directory.'/en/*.json') ?: []);
        array_map('unlink', glob($directory.'/*.json') ?: []);
        rmdir($directory.'/en');
        rmdir($directory);
    });

    it('rejects a dictionary with no values', function () {
        Dictionary::fromArray('empty', ['type' => 'things', 'values' => []]);
    })->throws(InvalidOptionException::class);

    it('rejects an unknown type', function () {
        Dictionary::fromArray('odd', ['type' => 'places', 'values' => [['name' => 'X']]]);
    })->throws(InvalidOptionException::class);

    it('rejects an entry with no name', function () {
        Dictionary::fromArray('odd', ['type' => 'things', 'values' => [['gender' => 'male']]]);
    })->throws(InvalidOptionException::class);

    it('defaults a missing gender to neutral', function () {
        $dictionary = Dictionary::fromArray('odd', ['type' => 'things', 'values' => [['name' => 'X']]]);

        expect($dictionary->entries[0]->gender)->toBe(Gender::Neutral);
    });

    it('throws for an unknown key', function () {
        app(FileDictionaryRepository::class)->find('nope');
    })->throws(DictionaryNotFoundException::class);
});

describe('pool sizes', function () {
    it('counts the names in the selection', function () {
        onlyDictionaries('star_wars');

        $repository = app(FileDictionaryRepository::class);
        $expected = $repository->find('star_wars')->count();

        expect(PasswordToolkit::poolSizes()['names'])->toBe($expected);
    });

    it('is recomputed after the selection changes', function () {
        onlyDictionaries('star_wars');
        $one = PasswordToolkit::poolSizes()['names'];

        onlyDictionaries('star_wars', 'back_to_the_future');
        $two = PasswordToolkit::poolSizes()['names'];

        expect($two)->toBeGreaterThan($one);
    });
});
