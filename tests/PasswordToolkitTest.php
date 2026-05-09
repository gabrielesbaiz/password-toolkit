<?php

use Gabrielesbaiz\PasswordToolkit\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit as PasswordToolkitFacade;
use Illuminate\Support\Collection;

function ptkInvokeProtected(string $method, array $args = [])
{
    $ref = new ReflectionMethod(PasswordToolkit::class, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs(null, $args);
}

function ptkConfigOnlyOne(string $section, string $key): void
{
    $people = collect(config('password-toolkit.name_types.people'))->map(fn () => false)->all();
    $things = collect(config('password-toolkit.name_types.things'))->map(fn () => false)->all();
    if ($section === 'people') {
        $people[$key] = true;
    } else {
        $things[$key] = true;
    }
    config()->set('password-toolkit.name_types.people', $people);
    config()->set('password-toolkit.name_types.things', $things);
}

beforeEach(function () {
    config()->set('password-toolkit.separator_symbol', '-');
    config()->set('password-toolkit.name_separator', true);
    config()->set('password-toolkit.add_numbers', false);
    config()->set('password-toolkit.numbers_digits', 4);
    config()->set('password-toolkit.numbers_position', 'end');
    config()->set('password-toolkit.leetspeak_conversion', 'no');
});

describe('generate()', function () {
    it('returns string by default', function () {
        expect(PasswordToolkit::generate())->toBeString()->not->toBeEmpty();
    });

    it('returns null when no name types enabled', function () {
        config()->set('password-toolkit.name_types.people', []);
        config()->set('password-toolkit.name_types.things', []);
        expect(PasswordToolkit::generate())->toBeNull();
    });

    it('uses configured separator symbol', function () {
        config()->set('password-toolkit.separator_symbol', '_');
        ptkConfigOnlyOne('people', 'back_to_the_future');
        $pwd = PasswordToolkit::generate();
        expect($pwd)->toContain('_')->not->toContain('-');
    });

    it('replaces spaces in name when name_separator true', function () {
        config()->set('password-toolkit.name_separator', true);
        config()->set('password-toolkit.separator_symbol', '#');
        ptkConfigOnlyOne('people', 'back_to_the_future');
        $pwd = PasswordToolkit::generate();
        expect($pwd)->not->toContain(' ');
    });

    it('strips spaces in name when name_separator false', function () {
        config()->set('password-toolkit.name_separator', false);
        config()->set('password-toolkit.separator_symbol', '-');
        ptkConfigOnlyOne('people', 'back_to_the_future');
        $pwd = PasswordToolkit::generate();
        // exactly 1 separator (between name and adjective)
        expect(substr_count($pwd, '-'))->toBe(1);
    });

    it('appends number at end when position end', function () {
        config()->set('password-toolkit.add_numbers', true);
        config()->set('password-toolkit.numbers_digits', 4);
        config()->set('password-toolkit.numbers_position', 'end');
        ptkConfigOnlyOne('people', 'back_to_the_future');
        $pwd = PasswordToolkit::generate();
        expect($pwd)->toMatch('/-\d{4}$/');
    });

    it('places number at start when position start', function () {
        config()->set('password-toolkit.add_numbers', true);
        config()->set('password-toolkit.numbers_digits', 5);
        config()->set('password-toolkit.numbers_position', 'start');
        ptkConfigOnlyOne('people', 'back_to_the_future');
        $pwd = PasswordToolkit::generate();
        expect($pwd)->toMatch('/^\d{5}-/');
    });

    it('places number in middle when position middle', function () {
        config()->set('password-toolkit.add_numbers', true);
        config()->set('password-toolkit.numbers_digits', 3);
        config()->set('password-toolkit.numbers_position', 'middle');
        ptkConfigOnlyOne('people', 'back_to_the_future');
        $pwd = PasswordToolkit::generate();
        // pattern: name-DDD-adj
        expect($pwd)->toMatch('/-\d{3}-/');
    });

    it('respects numbers_digits length', function () {
        config()->set('password-toolkit.add_numbers', true);
        config()->set('password-toolkit.numbers_digits', 6);
        config()->set('password-toolkit.numbers_position', 'end');
        ptkConfigOnlyOne('people', 'back_to_the_future');
        $pwd = PasswordToolkit::generate();
        expect($pwd)->toMatch('/-\d{6}$/');
    });

    it('applies basic leetspeak', function () {
        config()->set('password-toolkit.leetspeak_conversion', 'basic');
        ptkConfigOnlyOne('people', 'back_to_the_future');
        $pwd = PasswordToolkit::generate();
        // basic leetspeak only ever swaps known letters - output should not contain advanced markers like '|', '#', '<', '%'
        expect($pwd)->not->toMatch('/[#%<|\\\\]/');
    });

    it('applies advanced leetspeak', function () {
        config()->set('password-toolkit.leetspeak_conversion', 'advanced');
        ptkConfigOnlyOne('people', 'back_to_the_future');
        $pwd = PasswordToolkit::generate();
        expect($pwd)->toBeString();
    });

    it('produces deterministic structure name+separator+adjective without numbers', function () {
        config()->set('password-toolkit.add_numbers', false);
        ptkConfigOnlyOne('people', 'back_to_the_future');
        $pwd = PasswordToolkit::generate();
        expect(substr_count($pwd, '-'))->toBeGreaterThanOrEqual(1);
    });

    it('works for things-only config', function () {
        $people = collect(config('password-toolkit.name_types.people'))->map(fn () => false)->all();
        $things = collect(config('password-toolkit.name_types.things'))->map(fn () => false)->all();
        $things['italian_wines'] = true;
        config()->set('password-toolkit.name_types.people', $people);
        config()->set('password-toolkit.name_types.things', $things);
        expect(PasswordToolkit::generate())->toBeString()->not->toBeEmpty();
    });
});

describe('getRandomNameData()', function () {
    it('returns Collection with name and gender and file keys', function () {
        ptkConfigOnlyOne('people', 'back_to_the_future');
        $data = PasswordToolkit::getRandomNameData();
        expect($data)->toBeInstanceOf(Collection::class)
            ->and($data->get('name'))->toBeString()
            ->and($data->get('gender'))->toBeIn(['male', 'female', 'neutral'])
            ->and($data->get('file'))->toBe('back_to_the_future');
    });

    it('returns empty Collection when no files match', function () {
        config()->set('password-toolkit.name_types.people', []);
        config()->set('password-toolkit.name_types.things', []);
        $data = PasswordToolkit::getRandomNameData();
        expect($data)->toBeInstanceOf(Collection::class)->and($data->isEmpty())->toBeTrue();
    });
});

describe('getRandomAdjective()', function () {
    it('returns string matching gender or neutral', function () {
        $name = collect(['name' => 'Marty McFly', 'gender' => 'male', 'file' => 'back_to_the_future']);
        $adj = PasswordToolkit::getRandomAdjective($name);
        expect($adj)->toBeString()->not->toBeEmpty();

        $adjPath = __DIR__ . '/../src/Data/Adjectives/back_to_the_future_adjectives.json';
        $entries = collect(json_decode(file_get_contents($adjPath), true));
        $match = $entries->first(fn ($e) => $e['name'] === $adj);
        expect($match)->not->toBeNull()
            ->and($match['gender'])->toBeIn(['male', 'neutral']);
    });

    it('filters female correctly', function () {
        $name = collect(['name' => 'Jennifer', 'gender' => 'female', 'file' => 'back_to_the_future']);
        $adj = PasswordToolkit::getRandomAdjective($name);
        $entries = collect(json_decode(file_get_contents(__DIR__ . '/../src/Data/Adjectives/back_to_the_future_adjectives.json'), true));
        $match = $entries->first(fn ($e) => $e['name'] === $adj);
        expect($match['gender'])->toBeIn(['female', 'neutral']);
    });
});

describe('protected helpers', function () {
    it('zapSpaces strips non-alphanumeric', function () {
        expect(ptkInvokeProtected('zapSpaces', ['Hello World!']))->toBe('HelloWorld');
        expect(ptkInvokeProtected('zapSpaces', ['ABC-123_x']))->toBe('ABC123x');
        expect(ptkInvokeProtected('zapSpaces', [null]))->toBeNull();
    });

    it('getRandomNumber yields N-digit int', function () {
        foreach ([1, 2, 4, 6] as $n) {
            $val = ptkInvokeProtected('getRandomNumber', [$n]);
            expect($val)->toBeInt()
                ->and(strlen((string) $val))->toBe($n);
        }
    });

    it('leetspeakBasic maps lowercase chars', function () {
        $out = ptkInvokeProtected('leetspeakBasic', ['abegilosrtz']);
        expect($out)->toBe('4839110$272');
    });

    it('leetspeakBasic preserves unknown chars and uppercase', function () {
        $out = ptkInvokeProtected('leetspeakBasic', ['Hello-World']);
        expect($out)->toBe('H3110-W021d');
    });

    it('leetspeakAdvanced uses multi-char tokens', function () {
        $out = ptkInvokeProtected('leetspeakAdvanced', ['cfhjkmnpuvwxy']);
        expect($out)->toBe('<|=#_||<|V||\\||D|_|\\/\\/\\/%`/');
    });
});

describe('Facade', function () {
    it('resolves through facade', function () {
        $pwd = PasswordToolkitFacade::generate();
        expect($pwd)->toBeString()->not->toBeEmpty();
    });
});

describe('new things categories', function () {
    $newThings = [
        'italian_pasta_shapes',
        'italian_pizza_types',
        'italian_icecream_flavors',
        'italian_street_foods',
        'italian_desserts',
        'italian_cars',
        'italian_motorcycles',
        'italian_card_games',
        'italian_carnival_masks',
        'italian_dialect_words',
        'italian_progressive_rock_bands',
        'italian_cryptids_legends',
        'italian_invented_words',
        'italian_circus_terms',
        'italian_old_jobs',
        'italian_liqueurs',
        'italian_aperitivi',
        'italian_breads',
        'italian_cured_meats',
        'italian_islands',
        'italian_volcanoes',
        'italian_lakes',
        'italian_mountains',
        'italian_rivers',
        'italian_wine_regions',
        'italian_dance_styles',
        'italian_folk_instruments',
        'italian_castles',
        'italian_train_stations_classic',
        'italian_old_currencies',
        'italian_design_objects',
        'italian_sea_creatures',
    ];

    foreach ($newThings as $thing) {
        it("generate() works for {$thing} only", function () use ($thing) {
            ptkConfigOnlyOne('things', $thing);
            $pwd = PasswordToolkit::generate();
            expect($pwd)->toBeString()->not->toBeEmpty();
        });

        it("{$thing} returns names from its own file via getRandomNameData", function () use ($thing) {
            ptkConfigOnlyOne('things', $thing);
            $data = PasswordToolkit::getRandomNameData();
            expect($data->get('file'))->toBe($thing);
        });
    }
});

describe('new people categories', function () {
    $newPeople = [
        'italian_singers_classic',
        'italian_singers_modern',
        'italian_rappers',
        'italian_olympic_legends',
        'italian_tennis_players',
        'italian_volleyball_legends',
        'italian_motogp_legends',
        'italian_journalists',
        'italian_voice_actors',
        'italian_mathematicians',
        'italian_inventors',
        'roman_emperors',
        'italian_dj_producers',
        'lupin_iii_characters',
        'disney_villains',
    ];

    foreach ($newPeople as $person) {
        it("generate() works for {$person} only", function () use ($person) {
            ptkConfigOnlyOne('people', $person);
            $pwd = PasswordToolkit::generate();
            expect($pwd)->toBeString()->not->toBeEmpty();
        });

        it("{$person} returns names from its own file via getRandomNameData", function () use ($person) {
            ptkConfigOnlyOne('people', $person);
            $data = PasswordToolkit::getRandomNameData();
            expect($data->get('file'))->toBe($person);
        });
    }
});

describe('three-word cap rule', function () {
    it('every name entry has at most 3 space-separated tokens', function () {
        $files = array_merge(
            glob(__DIR__ . '/../src/Data/Names/People/*.json'),
            glob(__DIR__ . '/../src/Data/Names/Things/*.json')
        );
        foreach ($files as $f) {
            $j = json_decode(file_get_contents($f), true);
            foreach ($j['values'] as $entry) {
                $tokens = count(explode(' ', $entry['name']));
                expect($tokens)->toBeLessThanOrEqual(
                    3,
                    "{$entry['name']} in " . basename($f) . " exceeds 3 words ({$tokens})"
                );
            }
        }
    });
});

describe('Service provider / config', function () {
    it('publishes config under expected key', function () {
        expect(config('password-toolkit'))->toBeArray()
            ->and(config('password-toolkit.separator_symbol'))->toBeString()
            ->and(config('password-toolkit.name_types.people'))->toBeArray()
            ->and(config('password-toolkit.name_types.things'))->toBeArray();
    });
});
