<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Enums\Leetspeak;
use Gabrielesbaiz\PasswordToolkit\Enums\Strength;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Support\Entropy;
use Gabrielesbaiz\PasswordToolkit\Support\StrengthReport;

describe('charset model', function () {
    it('scores an empty password as very weak', function () {
        $report = PasswordToolkit::strength('');

        expect($report->score)->toBe(0)
            ->and($report->label)->toBe('very_weak')
            ->and($report->strength)->toBe(Strength::VeryWeak)
            ->and($report->entropyBits)->toBe(0.0);
    });

    it('detects all four character classes', function () {
        expect(PasswordToolkit::strength('Abc1!xyz')->charsetFlags)->toMatchArray([
            'lower' => true, 'upper' => true, 'digits' => true, 'symbols' => true,
        ]);
    });

    it('penalises repeated characters', function () {
        expect(PasswordToolkit::strength('aaaaaaaa')->entropyBits)
            ->toBeLessThan(PasswordToolkit::strength('abcdefgh')->entropyBits);
    });

    it('penalises keyboard runs', function () {
        expect(Entropy::charsetBits('qwertyuf')['bits'])
            ->toBeLessThan(Entropy::charsetBits('mxkvpzjf')['bits']);
    });

    it('grows with length', function () {
        expect(PasswordToolkit::strength('Abc1!xyzQwerty9$_')->score)
            ->toBeGreaterThan(PasswordToolkit::strength('abc')->score);
    });
});

describe('structural model', function () {
    it('reports components alongside the password', function () {
        ['password' => $password, 'report' => $report] = PasswordToolkit::generateWithReport();

        expect($password)->toBeString()->not->toBeEmpty()
            ->and($report)->toBeInstanceOf(StrengthReport::class)
            ->and($report->components)->toHaveKeys(['name', 'adjective', 'number', 'leetspeak_bonus', 'total'])
            ->and($report->entropyBits)->toBeGreaterThan(0.0);
    });

    it('credits leetspeak with nothing', function () {
        // Leetspeak is deterministic. It does not enlarge the set of passwords
        // the package can produce, so under a model that assumes the attacker
        // knows the configuration it is worth exactly zero bits.
        $none = Entropy::structuralBits(100, 30, 4, Leetspeak::None);
        $basic = Entropy::structuralBits(100, 30, 4, Leetspeak::Basic);
        $advanced = Entropy::structuralBits(100, 30, 4, Leetspeak::Advanced);

        expect($basic['total'])->toBe($none['total'])
            ->and($advanced['total'])->toBe($none['total'])
            ->and($advanced['leetspeak_bonus'])->toBe(0.0);
    });

    it('still reports the leetspeak component', function () {
        // Callers reading the array should not have to branch on the key.
        expect(Entropy::structuralBits(100, 30, 4, Leetspeak::Advanced))
            ->toHaveKey('leetspeak_bonus');
    });

    it('accepts the legacy string spelling', function () {
        expect(Entropy::structuralBits(100, 30, 4, 'no'))
            ->toBe(Entropy::structuralBits(100, 30, 4, Leetspeak::None));
    });

    it('reports fewer bits than the charset model for a generated password', function () {
        ['password' => $password, 'report' => $structural] = PasswordToolkit::generateWithReport();

        // The whole point of the structural model: an attacker who knows the
        // package searches the pool, not the alphabet.
        expect($structural->entropyBits)->toBeLessThan(PasswordToolkit::strength($password)->entropyBits);
    });
});

describe('scoring', function () {
    it('maps bits to bands', function () {
        expect(Entropy::score(20.0))->toBe(0)
            ->and(Entropy::score(30.0))->toBe(1)
            ->and(Entropy::score(50.0))->toBe(2)
            ->and(Entropy::score(80.0))->toBe(3)
            ->and(Entropy::score(150.0))->toBe(4);
    });

    it('round-trips a band through its score', function () {
        foreach (Strength::cases() as $case) {
            expect(Strength::fromScore($case->score()))->toBe($case);
        }
    });

    it('keeps the legacy label map in step with the enum', function () {
        foreach (Entropy::LABELS as $score => $label) {
            expect(Strength::fromScore($score)->value)->toBe($label)
                ->and(Entropy::label($score))->toBe($label);
        }
    });

    it('gives every band a colour', function () {
        foreach (Strength::cases() as $case) {
            expect($case->color())->toBeIn(['red', 'amber', 'green']);
        }
    });
});

describe('crack time', function () {
    it('humanises each unit with correct grammar', function () {
        app()->setLocale('en');

        expect(Entropy::humanizeSeconds(0.5))->toBe('instant')
            ->and(Entropy::humanizeSeconds(1))->toBe('1 second')
            ->and(Entropy::humanizeSeconds(30))->toBe('30 seconds')
            ->and(Entropy::humanizeSeconds(60))->toBe('1 minute')
            ->and(Entropy::humanizeSeconds(90))->toBe('1 minute')
            ->and(Entropy::humanizeSeconds(180))->toBe('3 minutes')
            ->and(Entropy::humanizeSeconds(3700))->toBe('1 hour')
            ->and(Entropy::humanizeSeconds(90000))->toBe('1 day')
            ->and(Entropy::humanizeSeconds(40_000_000))->toBe('1 year')
            ->and(Entropy::humanizeSeconds(4e9))->toBe('1 century');
    });

    it('humanises in the active locale', function () {
        app()->setLocale('it');

        expect(Entropy::humanizeSeconds(180))->toBe('3 minuti')
            ->and(Entropy::humanizeSeconds(0.5))->toBe('istantaneo');
    });

    it('never overflows to INF', function () {
        $report = PasswordToolkit::strength(str_repeat('Aa1!', 80));

        expect(is_finite($report->crackTimeSeconds))->toBeTrue()
            ->and(json_encode($report->toArray()))->toBeString();
    });

    it('is faster to crack at a higher guess rate', function () {
        expect(Entropy::crackTime(60.0, 1e12)['seconds'])
            ->toBeLessThan(Entropy::crackTime(60.0, 1e10)['seconds']);
    });

    it('falls back to the default rate when given a nonsense one', function () {
        expect(Entropy::crackTime(60.0, 0.0))->toBe(Entropy::crackTime(60.0, 1e10));
    });
});

describe('report serialisation', function () {
    it('exposes the full array shape', function () {
        $report = PasswordToolkit::strength('Goldrake-Mitico-4271');

        expect($report->toArray())->toHaveKeys([
            'entropy_bits', 'length', 'label', 'display_label', 'score', 'color',
            'components', 'charset_flags', 'crack_time_seconds', 'crack_time_human',
        ]);
    });

    it('is json serialisable', function () {
        $report = PasswordToolkit::strength('Goldrake-Mitico-4271');

        expect(json_decode($report->toJson(), true))->toBe($report->toArray())
            ->and(json_decode((string) json_encode($report), true))->toBe($report->toArray());
    });

    it('translates the display label', function () {
        app()->setLocale('it');

        expect(PasswordToolkit::strength('')->displayLabel())->toBe('Molto debole');
    });
});

describe('number entropy', function () {
    it('credits only the range the generator can draw', function () {
        // 100000..999999 is 900,000 values, not 1,000,000. Until 2.0.0 the
        // model claimed the full decade and every figure was ~0.15 bits
        // optimistic.
        expect(Entropy::numberBits(6))->toBe(log(900_000, 2))
            ->and(Entropy::numberBits(6))->toBeLessThan(Entropy::numberBits(6, true));
    });

    it('credits the full decade when leading zeros are allowed', function () {
        expect(Entropy::numberBits(6, true))->toBe(6 * log(10, 2))
            ->and(Entropy::numberBits(1, true))->toBe(log(10, 2));
    });

    it('credits nothing without digits', function () {
        expect(Entropy::numberBits(0))->toBe(0.0)
            ->and(Entropy::structuralBits(100, 30, 0)['number'])->toBe(0.0);
    });

    it('carries the correction into the structural report', function () {
        $strict = Entropy::structuralBits(100, 30, 6, Leetspeak::None, false);
        $zeros = Entropy::structuralBits(100, 30, 6, Leetspeak::None, true);

        expect($strict['total'])->toBeLessThan($zeros['total'])
            ->and($zeros['total'] - $strict['total'])->toBeGreaterThan(0.15)
            ->and($zeros['total'] - $strict['total'])->toBeLessThan(0.16);
    });
});

describe('the second adjective', function () {
    it('is worth log2(A - 1), not log2(A)', function () {
        // Drawn without replacement: the second adjective chooses from one
        // fewer word than the first.
        $pair = Entropy::structuralBits(100, 30, 0, Leetspeak::None, false, 2);

        expect($pair['adjective'])->toBe(log(30, 2))
            ->and($pair['second_adjective'])->toBe(log(29, 2))
            ->and($pair['total'])->toBeLessThan(log(100, 2) + (2 * log(30, 2)));
    });

    it('is reported at zero when only one adjective was drawn', function () {
        expect(Entropy::structuralBits(100, 30, 0)['second_adjective'])->toBe(0.0);
    });

    it('is worth nothing when the pool cannot supply a second', function () {
        expect(Entropy::structuralBits(100, 1, 0, Leetspeak::None, false, 2)['second_adjective'])
            ->toBe(0.0);
    });

    it('raises the reported entropy of a three-word password', function () {
        config()->set('password-toolkit.dictionaries.enabled', ['star_wars']);
        PasswordToolkit::flushCache();

        config()->set('password-toolkit.word_count', 2);
        $two = PasswordToolkit::generateWithReport()['report'];

        config()->set('password-toolkit.word_count', 3);
        $three = PasswordToolkit::generateWithReport()['report'];

        expect($three->entropyBits)->toBeGreaterThan($two->entropyBits)
            ->and($three->components['second_adjective'])->toBeGreaterThan(0.0);
    });
});

describe('configurable thresholds', function () {
    it('falls back to the shipped bands with no argument', function () {
        expect(Strength::fromBits(27.9))->toBe(Strength::VeryWeak)
            ->and(Strength::fromBits(28.0))->toBe(Strength::Weak)
            ->and(Strength::fromBits(128.0))->toBe(Strength::VeryStrong)
            ->and(Strength::thresholds())->toBe(Strength::DEFAULT_THRESHOLDS);
    });

    it('moves a band', function () {
        $strict = ['weak' => 40, 'fair' => 60, 'strong' => 90, 'very_strong' => 160];

        expect(Strength::fromBits(50.0, $strict))->toBe(Strength::Weak)
            ->and(Strength::fromBits(50.0))->toBe(Strength::Fair)
            ->and(Strength::fromBits(200.0, $strict))->toBe(Strength::VeryStrong);
    });

    it('accepts a partial override', function () {
        expect(Strength::fromBits(70.0, ['strong' => 80]))->toBe(Strength::Fair)
            ->and(Strength::fromBits(70.0))->toBe(Strength::Strong);
    });

    it('rejects bands that do not ascend', function () {
        Strength::fromBits(50.0, ['strong' => 20]);
    })->throws(InvalidOptionException::class, 'must ascend');

    it('rejects an unknown band', function () {
        Strength::fromBits(50.0, ['medium' => 40]);
    })->throws(InvalidOptionException::class);

    it('rejects a non-numeric band', function () {
        Strength::fromBits(50.0, ['strong' => 'high']);
    })->throws(InvalidOptionException::class);

    it('scores a report with the configured bands', function () {
        config()->set('password-toolkit.strength.thresholds', [
            'weak' => 1, 'fair' => 2, 'strong' => 3, 'very_strong' => 4,
        ]);

        // Every band edge below what even a trivial password scores.
        expect(PasswordToolkit::strength('abc')->label)->toBe('very_strong');
    });

    it('fails a config whose bands do not ascend', function () {
        config()->set('password-toolkit.strength.thresholds', ['weak' => 200]);

        PasswordToolkit::strength('abc');
    })->throws(InvalidOptionException::class);
});

it('does not overflow when the crack time exceeds PHP_INT_MAX centuries', function () {
    // A strong charset score divides out to roughly 1e40 centuries. Casting
    // that to int before the range check wrapped it to a meaningless value,
    // which then slipped past the guard and printed "0 centuries".
    expect(Entropy::humanizeSeconds(1.5e49))->toBe(trans('password-toolkit::strength.eternity'))
        ->and(Entropy::humanizeSeconds(1e20))->toBe(trans('password-toolkit::strength.eternity'))
        ->and(Entropy::humanizeSeconds(3.1e11))->toContain('98');
});
