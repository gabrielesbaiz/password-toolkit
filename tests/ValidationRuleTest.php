<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Enums\Strength;
use Gabrielesbaiz\PasswordToolkit\Exceptions\InvalidOptionException;
use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Rules\StrongPassword;
use Illuminate\Support\Facades\Validator;

function validatePassword(mixed $value, ?StrongPassword $rule = null): array
{
    $validator = Validator::make(
        ['password' => $value],
        ['password' => [$rule ?? new StrongPassword]],
    );

    return $validator->errors()->get('password');
}

it('passes a strong password', function () {
    expect(validatePassword('Goldrake-Mitico-4271-Extra'))->toBe([]);
});

it('fails a weak password', function () {
    expect(validatePassword('abc'))->not->toBe([]);
});

it('respects a lower floor', function () {
    expect(validatePassword('correct-horse', StrongPassword::fair()))->toBe([])
        ->and(validatePassword('correct-horse', StrongPassword::veryStrong()))->not->toBe([]);
});

it('builds from a score, a band and an enum', function () {
    expect(StrongPassword::atLeast(2))->toBeInstanceOf(StrongPassword::class)
        ->and(StrongPassword::atLeast('fair'))->toBeInstanceOf(StrongPassword::class)
        ->and(StrongPassword::atLeast(Strength::Fair))->toBeInstanceOf(StrongPassword::class);
});

it('names the achieved and required bands in the message', function () {
    app()->setLocale('en');

    $errors = validatePassword('abc', StrongPassword::strong());

    expect($errors[0])->toContain('Very weak')->toContain('Strong');
});

it('translates the message', function () {
    app()->setLocale('it');

    expect(validatePassword('abc', StrongPassword::strong())[0])->toContain('Molto debole');
});

it('treats a non-string value as empty', function () {
    expect(validatePassword(null))->not->toBe([])
        ->and(validatePassword(['a']))->not->toBe([]);
});

it('scores with the charset model by default', function () {
    // The structural model would score this one on the pools, not on the
    // alphabet, and reject a password the charset model calls strong.
    config()->set('password-toolkit.strength.rule_model', 'charset');

    expect(validatePassword('Goldrake-Mitico-4271-Extra'))->toBe([]);
});

it('honours the configured rule model', function () {
    config()->set('password-toolkit.dictionaries.enabled', ['star_wars']);
    config()->set('password-toolkit.strength.rule_model', 'structural');
    PasswordToolkit::flushCache();

    // A single dictionary, no digits credited beyond the configured six: the
    // structural figure lands well under 60 bits, so "strong" is out of reach
    // whatever the user typed.
    expect(validatePassword('Goldrake-Mitico-4271-Extra'))->not->toBe([]);
});

it('takes a model per rule', function () {
    config()->set('password-toolkit.dictionaries.enabled', ['star_wars']);
    config()->set('password-toolkit.strength.rule_model', 'structural');
    PasswordToolkit::flushCache();

    expect(validatePassword('Goldrake-Mitico-4271-Extra', StrongPassword::strong()->using('charset')))->toBe([]);
});

it('rejects an unknown rule model', function () {
    config()->set('password-toolkit.strength.rule_model', 'vibes');

    validatePassword('whatever');
})->throws(InvalidOptionException::class);

it('honours moved thresholds', function () {
    config()->set('password-toolkit.strength.thresholds.strong', 400);
    config()->set('password-toolkit.strength.thresholds.very_strong', 500);

    expect(validatePassword('Goldrake-Mitico-4271-Extra'))->not->toBe([]);
});
