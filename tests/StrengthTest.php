<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Enums\Leetspeak;
use Gabrielesbaiz\PasswordToolkit\Enums\Strength;
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
