<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\PasswordToolkit as PasswordToolkitService;
use Gabrielesbaiz\PasswordToolkit\Support\Identifier;

beforeEach(function () {
    config()->set('password-toolkit.dictionaries.enabled', ['star_wars']);
    config()->set('password-toolkit.locale', 'it');

    PasswordToolkit::flushCache();
});

describe('path traversal', function () {
    // Both of these end up interpolated into {root}/{locale}/{key}.json. An
    // application that passes a request value into either would otherwise be
    // able to read any JSON file the PHP process can see.

    $traversals = [
        'relative' => '../../../../../../tmp',
        'absolute' => '/etc',
        'slashed' => 'en/../../..',
        'backslashed' => '..\\..\\windows',
        'dotted' => '..',
        'nul-ish' => "en\0/etc",
        'newline' => "en\n../..",
    ];

    foreach ($traversals as $label => $value) {
        it("rejects a {$label} locale", function () use ($value) {
            PasswordToolkit::make()->locale($value)->generate();
        })->throws(InvalidOptionException::class);

        it("rejects a {$label} dictionary key on only()", function () use ($value) {
            PasswordToolkit::make()->only([$value])->generate();
        })->throws(InvalidOptionException::class);

        it("rejects a {$label} dictionary key on registerDictionary()", function () use ($value) {
            PasswordToolkit::registerDictionary($value, ['Victim'], 'people');
        })->throws(InvalidOptionException::class);
    }

    it('rejects a traversing fallback locale', function () {
        config()->set('password-toolkit.fallback_locale', '../../etc');

        PasswordToolkit::generate();
    })->throws(InvalidOptionException::class);

    it('ignores an unusable application locale rather than trusting it', function () {
        config()->set('password-toolkit.locale', null);
        config()->set('password-toolkit.fallback_locale', 'it');

        // Laravel's own setLocale() guard permits dots, so '..' reaches this
        // package intact. It must not become a path segment.
        app()->setLocale('..');

        expect(PasswordToolkit::generate())->toBeString()->not->toBeEmpty();
    });

    it('still accepts the locale shapes real applications use', function () {
        foreach (['en', 'it', 'pt_BR', 'zh-Hant', 'en_GB'] as $locale) {
            expect(Identifier::locale($locale))->toBe($locale);
        }
    });

    it('rejects an absurdly long locale or key', function () {
        expect(fn () => Identifier::locale(str_repeat('a', 36)))->toThrow(InvalidOptionException::class)
            ->and(fn () => Identifier::key(str_repeat('a', 65)))->toThrow(InvalidOptionException::class);
    });
});

describe('untrusted dictionary content', function () {
    // The README suggests registering User::pluck('nickname'), so names are
    // application data, not package data, and are not assumed to be clean.

    it('strips characters that would break a shell or a CSV', function () {
        PasswordToolkit::registerDictionary('hostile', [
            "O'Brien; rm -rf /",
            'Robert"); DROP TABLE users;--',
            'Zoe <script>alert(1)</script>',
            "tab\there",
            "new\nline",
            "carriage\rreturn",
            "null\0byte",
            'semi;colon',
            'pipe|dollar$',
            'back\\slash',
        ], 'people');

        foreach (PasswordToolkit::make()->only('hostile')->many(60) as $password) {
            expect($password)->toMatch('/^[\p{L}\p{N}-]+$/u', "unsafe characters in [{$password}]");
        }
    });

    it('keeps the separator and nothing else when one is configured', function () {
        PasswordToolkit::registerDictionary('hostile', ["Jean-Luc O'Brien"], 'people');

        $password = PasswordToolkit::make()->only('hostile')->separator('_')->withoutNumbers()->generate();

        // The hyphen is not the configured separator, so it goes too.
        expect($password)->toMatch('/^[\p{L}\p{N}_]+$/u')->toContain('JeanLuc_OBrien');
    });

    it('strips everything non-alphanumeric when there is no separator', function () {
        PasswordToolkit::registerDictionary('hostile', ["Jean-Luc O'Brien"], 'people');

        $password = PasswordToolkit::make()->only('hostile')->separator(null)->withoutNumbers()->generate();

        expect($password)->toMatch('/^[\p{L}\p{N}]+$/u');
    });

    it('collapses runs of whitespace rather than emitting empty segments', function () {
        PasswordToolkit::registerDictionary('spaced', ["Ada   \t  Lovelace"], 'people');

        expect(PasswordToolkit::make()->only('spaced')->withoutNumbers()->generate())
            ->toContain('Ada-Lovelace')
            ->not->toContain('--');
    });

    it('never leaves a doubled or dangling separator behind', function () {
        PasswordToolkit::registerDictionary('messy', [
            "O'Brien Jr.", '...Leading', 'Trailing...', 'Bad"; rm -rf / name',
        ], 'people');

        foreach (PasswordToolkit::make()->only('messy')->many(40) as $password) {
            expect($password)->not->toContain('--')
                ->not->toStartWith('-')
                ->not->toEndWith('-');
        }
    });

    it('rejects a name with no letters or digits at registration', function () {
        PasswordToolkit::registerDictionary('bad', ['!!!'], 'people');
    })->throws(InvalidOptionException::class);

    it('reports malformed JSON as malformed JSON', function () {
        $directory = sys_get_temp_dir().'/ptk-bad-'.bin2hex(random_bytes(4));
        mkdir($directory, 0o755, true);
        file_put_contents($directory.'/broken.json', '{ "values": [ }');

        config()->set('password-toolkit.dictionaries.paths', [$directory]);
        PasswordToolkit::flushCache();

        try {
            expect(fn () => PasswordToolkit::make()->only('broken')->generate())
                ->toThrow(InvalidOptionException::class, 'is not valid JSON');
        } finally {
            unlink($directory.'/broken.json');
            rmdir($directory);
        }
    });
});

describe('randomness', function () {
    it('draws every segment from a CSPRNG', function () {
        $source = file_get_contents(__DIR__.'/../src/PasswordToolkit.php');

        // 1.x used Collection::random(), i.e. mt_rand(), for names and
        // adjectives and random_int() only for the digits.
        expect($source)->not->toContain('mt_rand')
            ->not->toContain('->random()')
            ->not->toContain('array_rand')
            ->not->toContain('shuffle(');

        expect(file_get_contents(__DIR__.'/../src/Dictionaries/AdjectiveResolver.php'))
            ->not->toContain('array_rand')
            ->toContain('random_int');
    });

    it('does not repeat itself over a large batch', function () {
        $passwords = PasswordToolkit::make()->digits(6)->many(500);

        expect(count(array_unique($passwords)))->toBeGreaterThan(495);
    });
});

describe('batch limits', function () {
    it('refuses a batch that would exhaust memory', function () {
        PasswordToolkit::generateMany(PasswordToolkitService::MAX_BATCH + 1);
    })->throws(InvalidOptionException::class);

    it('accepts a batch at the limit boundary', function () {
        expect(fn () => PasswordToolkit::generateMany(1))->not->toThrow(InvalidOptionException::class);
    });

    it('generates a unique batch', function () {
        $passwords = PasswordToolkit::make()->digits(6)->unique(200);

        expect($passwords)->toHaveCount(200)
            ->and(array_unique($passwords))->toHaveCount(200);
    });

    it('says so rather than looping when the pool is too small to be unique', function () {
        // One name against the Italian default adjective pool caps out around
        // 1,200 distinct passwords with the digits off.
        PasswordToolkit::registerDictionary('tiny', ['Only'], 'people');

        PasswordToolkit::make()->only('tiny')->withoutNumbers()->unique(5000);
    })->throws(InvalidOptionException::class, 'unique passwords');
});

describe('entropy honesty', function () {
    it('credits leetspeak with nothing in the structural model', function () {
        $plain = PasswordToolkit::structuralReport('x');
        $leet = PasswordToolkit::make()->leet('advanced')->withReport()['report'];

        // A deterministic transform cannot enlarge the space an attacker who
        // knows the config has to search.
        expect($leet->entropyBits)->toBe($plain->entropyBits);
    });

    it('reports fewer bits than the naive charset model', function () {
        ['password' => $password, 'report' => $structural] = PasswordToolkit::generateWithReport();

        expect($structural->entropyBits)
            ->toBeLessThan(PasswordToolkit::strength($password)->entropyBits);
    });
});
