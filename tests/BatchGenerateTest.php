<?php

use Gabrielesbaiz\PasswordToolkit\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Support\StrengthReport;

it('returns string when count is 1 (default)', function () {
    $out = PasswordToolkit::generate();
    expect($out)->toBeString()->not->toBeEmpty();
});

it('returns string when count explicitly 1', function () {
    $out = PasswordToolkit::generate(1);
    expect($out)->toBeString();
});

it('returns array of N passwords when count > 1', function () {
    $out = PasswordToolkit::generate(5);
    expect($out)->toBeArray()
        ->and(count($out))->toBe(5)
        ->and($out[0])->toBeString();
});

it('throws on count < 1', function () {
    PasswordToolkit::generate(0);
})->throws(InvalidArgumentException::class);

it('generateManyWithReport returns array of password+report', function () {
    $out = PasswordToolkit::generateManyWithReport(3);
    expect($out)->toBeArray()
        ->and(count($out))->toBe(3);
    foreach ($out as $row) {
        expect($row)->toHaveKeys(['password', 'report'])
            ->and($row['password'])->toBeString()
            ->and($row['report'])->toBeInstanceOf(StrengthReport::class);
    }
});

it('produces unique-ish passwords across batch', function () {
    $out = PasswordToolkit::generate(20);
    $unique = count(array_unique($out));
    expect($unique)->toBeGreaterThan(10); // very high collision rate would be a red flag
});
