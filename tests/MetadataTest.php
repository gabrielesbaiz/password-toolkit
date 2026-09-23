<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Dictionaries\Dictionary;
use Gabrielesbaiz\PasswordToolkit\Enums\DictionaryGroup;
use Gabrielesbaiz\PasswordToolkit\Enums\Reach;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;

dataset('dictionaryFiles', fn () => collect((array) glob(dirname(__DIR__).'/src/Data/Names/{People,Things}/*.json', GLOB_BRACE))
    ->mapWithKeys(fn ($path) => [basename((string) $path) => [(string) $path]])->all());

describe('dictionary metadata', function () {
    it('declares a valid group, tags, icon and reach', function (string $path) {
        $json = json_decode((string) file_get_contents($path), true);

        expect($json)->toHaveKeys(['group', 'tags', 'icon', 'reach'])
            ->and(DictionaryGroup::tryFrom($json['group']))->not->toBeNull("bad group in {$path}")
            ->and(Reach::tryFrom($json['reach']))->not->toBeNull("bad reach in {$path}")
            ->and($json['tags'])->toBeArray()->not->toBeEmpty()
            ->and($json['icon'])->toBeString()->not->toBeEmpty();

        foreach ($json['tags'] as $tag) {
            expect($tag)->toMatch('/^[a-z][a-z0-9-]*$/', "tag [{$tag}] in {$path}");
        }
    })->with('dictionaryFiles');

    it('declares a name and resolves a label in every shipped locale', function (string $path) {
        $data = json_decode((string) file_get_contents($path), true);

        // The name lives with the dictionary so one file is all it takes to add
        // one — and so a dictionary of your own can carry a proper name without
        // publishing the package's translations.
        expect($data['name'] ?? null)->toBeString("no name in [{$path}]")
            ->and($data['name'])->not->toBe('');

        $dictionary = Dictionary::fromArray($data['key'], $data, true);

        foreach (['en', 'it'] as $locale) {
            app()->setLocale($locale);

            // Without this a picker shows a snake_case key, which is the kind of
            // thing that ships and then stays.
            expect($dictionary->label())->not->toBe($data['key'], "{$locale} label for [{$data['key']}]");
        }
    })->with('dictionaryFiles');

    it('names every group and reach in every shipped locale', function () {
        foreach (['en', 'it'] as $locale) {
            $strings = require dirname(__DIR__)."/resources/lang/{$locale}/dictionaries.php";

            foreach (DictionaryGroup::cases() as $group) {
                expect($strings['groups'][$group->value] ?? null)->toBeString("{$locale}: {$group->value}");
            }

            foreach (Reach::cases() as $reach) {
                expect($strings['reach'][$reach->value] ?? null)->toBeString("{$locale}: {$reach->value}");
            }
        }
    });

    it('gives every group at least one dictionary', function () {
        $used = PasswordToolkit::dictionaries()->pluck('group')->unique();

        // A group nobody uses is a filter that returns nothing.
        foreach (DictionaryGroup::cases() as $group) {
            expect($used)->toContain($group->value);
        }
    });
});

describe('filtering', function () {
    beforeEach(function () {
        config()->set('password-toolkit.dictionaries.enabled', '*');
        PasswordToolkit::flushCache();
    });

    it('filters by group', function () {
        $rows = PasswordToolkit::dictionaries(PasswordToolkit::make()->groups('food')->options());

        expect($rows)->not->toBeEmpty()
            ->and($rows->pluck('group')->unique()->all())->toBe(['food']);
    });

    it('accepts several groups', function () {
        $rows = PasswordToolkit::dictionaries(PasswordToolkit::make()->groups(['food', 'drink'])->options());

        expect($rows->pluck('group')->unique()->sort()->values()->all())->toBe(['drink', 'food']);
    });

    it('accepts a group enum', function () {
        expect(PasswordToolkit::make()->groups(DictionaryGroup::Sport)->options()->groups)
            ->toBe([DictionaryGroup::Sport]);
    });

    it('rejects an unknown group', function () {
        PasswordToolkit::make()->groups('biscuits');
    })->throws(InvalidOptionException::class);

    it('filters by tag', function () {
        $rows = PasswordToolkit::dictionaries(PasswordToolkit::make()->tagged('cuisine')->options());

        expect($rows)->not->toBeEmpty();

        foreach ($rows as $row) {
            expect($row['tags'])->toContain('cuisine');
        }
    });

    it('requires every tag, not any', function () {
        $both = PasswordToolkit::dictionaries(PasswordToolkit::make()->tagged(['italian', 'sweet'])->options());
        $one = PasswordToolkit::dictionaries(PasswordToolkit::make()->tagged('italian')->options());

        expect($both->count())->toBeLessThan($one->count())->toBeGreaterThan(0);
    });

    it('filters by reach, inclusively upwards', function () {
        $global = PasswordToolkit::dictionaries(PasswordToolkit::make()->reach('global')->options());
        $italian = PasswordToolkit::dictionaries(PasswordToolkit::make()->reach('italian')->options());
        $niche = PasswordToolkit::dictionaries(PasswordToolkit::make()->reach('niche')->options());

        // Asking for 'italian' accepts 'global' too: anything universally known
        // is also known to an Italian.
        expect($global->count())->toBeLessThan($italian->count())
            ->and($italian->count())->toBeLessThan($niche->count())
            ->and($niche->count())->toBe(PasswordToolkit::dictionaries()->count())
            ->and($global->pluck('reach')->unique()->all())->toBe(['global']);
    });

    it('combines filters', function () {
        $rows = PasswordToolkit::dictionaries(
            PasswordToolkit::make()->groups('sport')->reach('global')->tagged('italian')->options(),
        );

        expect($rows)->not->toBeEmpty();

        foreach ($rows as $row) {
            expect($row['group'])->toBe('sport')
                ->and($row['reach'])->toBe('global')
                ->and($row['tags'])->toContain('italian');
        }
    });

    it('generates from a filtered pool', function () {
        expect(PasswordToolkit::make()->groups('food')->reach('global')->generate())
            ->toBeString()->not->toBeEmpty();
    });

    it('lets an explicit key win over the filters', function () {
        // Asking for a dictionary by name means you want it, whatever group or
        // reach it happens to carry.
        $rows = PasswordToolkit::dictionaries(
            PasswordToolkit::make()->only('italian_dialect_words')->groups('food')->reach('global')->options(),
        );

        expect($rows->keys()->all())->toBe(['italian_dialect_words']);
    });

    it('reads the filters from config', function () {
        config()->set('password-toolkit.dictionaries.groups', ['drink']);
        config()->set('password-toolkit.dictionaries.reach', 'global');

        $options = Options::fromConfig();

        expect($options->groups)->toBe([DictionaryGroup::Drink])
            ->and($options->reach)->toBe(Reach::Global);
    });
});

describe('picker data', function () {
    it('gives a Nova picker everything a row needs', function () {
        app()->setLocale('en');

        $row = PasswordToolkit::dictionaries()->get('italian_pasta_shapes');

        expect($row)->toHaveKeys([
            'key', 'label', 'icon', 'type', 'group', 'group_label',
            'tags', 'reach', 'reach_label', 'locale', 'count', 'built_in',
        ])->and($row['label'])->toBe('Italian Pasta Shapes')
            ->and($row['icon'])->toBe('🍝')
            ->and($row['group_label'])->toBe('Food');
    });

    it('translates the labels', function () {
        app()->setLocale('it');

        $row = PasswordToolkit::dictionaries()->get('italian_pasta_shapes');

        expect($row['label'])->toBe('Formati di Pasta')
            ->and($row['group_label'])->toBe('Cibo')
            ->and($row['reach_label'])->toBe('Mondiale');
    });

    it('falls back to a readable key when no label is written', function () {
        PasswordToolkit::registerDictionary('my_own_thing', ['Example']);

        expect(PasswordToolkit::dictionaries()->get('my_own_thing')['label'])->toBe('My Own Thing');
    });

    it('adds a live sample password per dictionary', function () {
        $rows = PasswordToolkit::dictionariesWithSamples(
            PasswordToolkit::make()->only(['italian_wines', 'star_wars'])->options(),
        );

        expect($rows)->toHaveCount(2);

        foreach ($rows as $row) {
            expect($row['sample'])->toBeString()->not->toBeEmpty();
        }
    });

    it('summarises groups and tags', function () {
        $groups = PasswordToolkit::groups();

        expect($groups)->not->toBeEmpty()
            ->and($groups->first())->toHaveKeys(['value', 'label', 'icon', 'count'])
            ->and($groups->sum('count'))->toBe(PasswordToolkit::dictionaries()->count());

        expect(PasswordToolkit::tags()->first())->toHaveKeys(['value', 'count']);
    });
});

describe('console filters', function () {
    it('filters the listing by group', function () {
        $decoded = json_decode(artisanOutput('password-toolkit:generate', [
            '--list' => true, '--json' => true, '--group' => ['drink'],
        ]), true);

        expect($decoded)->not->toBeEmpty()
            ->and(array_unique(array_column($decoded, 'group')))->toBe(['drink']);
    });

    it('filters generation by reach and tag', function () {
        $this->artisan('password-toolkit:generate', ['count' => 3, '--reach' => 'global', '--tag' => ['italian']])
            ->assertSuccessful();
    });

    it('fails on an unknown group', function () {
        $this->artisan('password-toolkit:generate', ['--group' => ['biscuits']]);
    })->throws(InvalidOptionException::class);
});
