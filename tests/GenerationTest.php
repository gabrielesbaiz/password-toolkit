<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Enums\Leetspeak;
use Gabrielesbaiz\PasswordToolkit\Enums\NumbersPosition;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;
use Gabrielesbaiz\PasswordToolkit\Exceptions\NoDictionariesEnabledException;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;

beforeEach(function () {
    config()->set('password-toolkit.separator_symbol', '-');
    config()->set('password-toolkit.name_separator', true);
    config()->set('password-toolkit.add_numbers', false);
    config()->set('password-toolkit.numbers_digits', 4);
    config()->set('password-toolkit.numbers_position', 'end');
    config()->set('password-toolkit.leetspeak_conversion', 'none');
    config()->set('password-toolkit.dictionaries.enabled', '*');
    config()->set('password-toolkit.dictionaries.except', []);

    PasswordToolkit::flushCache();
});

describe('generate()', function () {
    it('returns a non-empty string', function () {
        expect(PasswordToolkit::generate())->toBeString()->not->toBeEmpty();
    });

    it('throws when nothing is enabled', function () {
        onlyDictionaries();

        PasswordToolkit::generate();
    })->throws(NoDictionariesEnabledException::class);

    it('never leaks a space into the password', function () {
        foreach (PasswordToolkit::generateMany(50) as $password) {
            expect($password)->not->toContain(' ');
        }
    });

    it('uses the configured separator', function () {
        config()->set('password-toolkit.separator_symbol', '_');
        onlyDictionaries('back_to_the_future');

        expect(PasswordToolkit::generate())->toContain('_')->not->toContain('-');
    });

    it('strips word breaks when name_separator is false', function () {
        config()->set('password-toolkit.name_separator', false);
        onlyDictionaries('back_to_the_future');

        expect(substr_count(PasswordToolkit::generate(), '-'))->toBe(1);
    });

    it('accepts a null separator', function () {
        config()->set('password-toolkit.separator_symbol', null);
        onlyDictionaries('back_to_the_future');

        expect(PasswordToolkit::generate())->toMatch('/^[A-Za-z0-9]+$/');
    });
});

describe('numbers', function () {
    beforeEach(function () {
        config()->set('password-toolkit.add_numbers', true);
        onlyDictionaries('back_to_the_future');
    });

    it('appends the number at the end', function () {
        expect(PasswordToolkit::generate())->toMatch('/-\d{4}$/');
    });

    it('places the number at the start', function () {
        config()->set('password-toolkit.numbers_position', 'start');
        config()->set('password-toolkit.numbers_digits', 5);

        expect(PasswordToolkit::generate())->toMatch('/^\d{5}-/');
    });

    it('places the number in the middle', function () {
        config()->set('password-toolkit.numbers_position', 'middle');
        config()->set('password-toolkit.numbers_digits', 3);

        expect(PasswordToolkit::generate())->toMatch('/-\d{3}-/');
    });

    it('honours the digit count', function () {
        config()->set('password-toolkit.numbers_digits', 6);

        expect(PasswordToolkit::generate())->toMatch('/-\d{6}$/');
    });

    it('rejects an out-of-range digit count', function () {
        config()->set('password-toolkit.numbers_digits', 0);

        PasswordToolkit::generate();
    })->throws(InvalidOptionException::class);

    it('rejects an unknown position', function () {
        config()->set('password-toolkit.numbers_position', 'sideways');

        PasswordToolkit::generate();
    })->throws(InvalidOptionException::class);
});

describe('leetspeak', function () {
    it('applies basic mode without advanced glyphs', function () {
        config()->set('password-toolkit.leetspeak_conversion', 'basic');
        onlyDictionaries('back_to_the_future');

        expect(PasswordToolkit::generate())->not->toMatch('/[#%<|\\\\]/');
    });

    it('applies advanced mode', function () {
        config()->set('password-toolkit.leetspeak_conversion', 'advanced');
        onlyDictionaries('back_to_the_future');

        expect(PasswordToolkit::generate())->toBeString()->not->toBeEmpty();
    });

    it('still applies leetspeak when numbers are off', function () {
        config()->set('password-toolkit.leetspeak_conversion', 'basic');
        config()->set('password-toolkit.add_numbers', false);
        onlyDictionaries('back_to_the_future');

        expect(PasswordToolkit::generate())->not->toMatch('/[aeiosAEIOS]/');
    });

    it('accepts the 1.x spelling of none', function () {
        config()->set('password-toolkit.leetspeak_conversion', 'no');
        onlyDictionaries('back_to_the_future');

        expect(PasswordToolkit::generate())->toBeString();
    });

    it('rejects an unknown mode', function () {
        config()->set('password-toolkit.leetspeak_conversion', 'extreme');

        PasswordToolkit::generate();
    })->throws(InvalidOptionException::class);

    it('maps basic mode to single characters only', function () {
        foreach (Leetspeak::Basic->map() as $to) {
            expect(strlen($to))->toBe(1);
        }
    });

    it('treats basic as a subset of advanced', function () {
        expect(array_diff_key(Leetspeak::Basic->map(), Leetspeak::Advanced->map()))->toBe([]);
    });
});

describe('generateMany()', function () {
    it('returns exactly the requested count', function () {
        expect(PasswordToolkit::generateMany(25))->toHaveCount(25);
    });

    it('rejects a count below one', function () {
        PasswordToolkit::generateMany(0);
    })->throws(InvalidOptionException::class);

    it('produces mostly distinct passwords', function () {
        config()->set('password-toolkit.add_numbers', true);

        $passwords = PasswordToolkit::generateMany(50);

        expect(count(array_unique($passwords)))->toBeGreaterThan(45);
    });
});

describe('NumbersPosition', function () {
    it('arranges segments per position', function () {
        expect(NumbersPosition::Start->arrange('N', 'A', '1'))->toBe(['1', 'N', 'A'])
            ->and(NumbersPosition::Middle->arrange('N', 'A', '1'))->toBe(['N', '1', 'A'])
            ->and(NumbersPosition::End->arrange('N', 'A', '1'))->toBe(['N', 'A', '1']);
    });
});
