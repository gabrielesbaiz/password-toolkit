<?php

use Gabrielesbaiz\PasswordToolkit\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Support\Entropy;
use Gabrielesbaiz\PasswordToolkit\Support\StrengthReport;

it('returns very_weak for empty input', function () {
    $r = PasswordToolkit::strength('');
    expect($r->score)->toBe(0)
        ->and($r->label)->toBe('very_weak')
        ->and($r->entropyBits)->toBe(0.0);
});

it('detects all four charset classes', function () {
    $r = PasswordToolkit::strength('Abc1!xyz');
    expect($r->charsetFlags)->toMatchArray([
        'lower' => true, 'upper' => true, 'digits' => true, 'symbols' => true,
    ]);
});

it('penalizes repeated characters', function () {
    $plain = PasswordToolkit::strength('abcdefgh');
    $rep = PasswordToolkit::strength('aaaaaaaa');
    expect($rep->entropyBits)->toBeLessThan($plain->entropyBits);
});

it('scales score with length', function () {
    $short = PasswordToolkit::strength('abc');
    $long = PasswordToolkit::strength('Abc1!xyzQwerty9$_');
    expect($long->score)->toBeGreaterThan($short->score);
});

it('produces a structural report from generateWithReport', function () {
    $out = PasswordToolkit::generateWithReport();
    if ($out['password'] === null) {
        // No dictionaries enabled in test env — skip.
        expect($out['report'])->toBeNull();
        return;
    }
    expect($out['report'])->toBeInstanceOf(StrengthReport::class)
        ->and($out['report']->components)->toHaveKeys(['name', 'adjective', 'number', 'leetspeak_bonus', 'total'])
        ->and($out['report']->entropyBits)->toBeGreaterThan(0);
});

it('humanizes crack time', function () {
    expect(Entropy::humanizeSeconds(0.5))->toBe('instant')
        ->and(Entropy::humanizeSeconds(90))->toBe('1 minutes')
        ->and(Entropy::humanizeSeconds(3700))->toBe('1 hours')
        ->and(Entropy::humanizeSeconds(90000))->toBe('1 days')
        ->and(Entropy::humanizeSeconds(40000000))->toBe('1 years');
});

it('maps bits to score thresholds', function () {
    expect(Entropy::score(20))->toBe(0)
        ->and(Entropy::score(30))->toBe(1)
        ->and(Entropy::score(50))->toBe(2)
        ->and(Entropy::score(80))->toBe(3)
        ->and(Entropy::score(150))->toBe(4);
});

it('adds leetspeak bonus to structural bits', function () {
    $no = Entropy::structuralBits(100, 30, 4, 'no');
    $basic = Entropy::structuralBits(100, 30, 4, 'basic');
    $adv = Entropy::structuralBits(100, 30, 4, 'advanced');
    expect($basic['total'])->toBeGreaterThan($no['total'])
        ->and($adv['total'])->toBeGreaterThan($basic['total']);
});
