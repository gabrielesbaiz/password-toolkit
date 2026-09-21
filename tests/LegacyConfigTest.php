<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;
use Gabrielesbaiz\PasswordToolkit\Generator\Options;

beforeEach(function () {
    // Simulate a config file published by 1.x: no `dictionaries` block at all,
    // one boolean per dictionary under `name_types`.
    config()->set('password-toolkit.dictionaries', null);
    config()->set('password-toolkit.name_types', [
        'people' => ['star_wars' => true, 'cartoons' => false],
        'things' => ['italian_wines' => true, 'italian_lakes' => false],
    ]);

    PasswordToolkit::flushCache();
});

it('translates name_types into a selection', function () {
    $options = withoutDeprecations(fn () => Options::fromConfig());

    expect($options->enabled)->toEqualCanonicalizing(['star_wars', 'italian_wines']);
});

it('still generates from a 1.x config', function () {
    $password = withoutDeprecations(fn () => PasswordToolkit::generate());

    expect($password)->toBeString()->not->toBeEmpty();
});

it('only selects the dictionaries that were switched on', function () {
    $keys = withoutDeprecations(fn () => PasswordToolkit::dictionaries(Options::fromConfig()))->keys();

    expect($keys->all())->toEqualCanonicalizing(['star_wars', 'italian_wines']);
});

it('emits a deprecation', function () {
    $messages = [];

    set_error_handler(function (int $level, string $message) use (&$messages): bool {
        $messages[] = $message;

        return true;
    }, E_USER_DEPRECATED);

    try {
        Options::fromConfig();
    } finally {
        restore_error_handler();
    }

    expect($messages)->not->toBeEmpty()
        ->and($messages[0])->toContain('name_types')
        ->and($messages[0])->toContain('deprecated');
});

it('prefers the new block when both are present', function () {
    config()->set('password-toolkit.dictionaries', ['enabled' => ['italian_lakes']]);

    expect(Options::fromConfig()->enabled)->toBe(['italian_lakes']);
});

it('accepts the 1.x leetspeak spelling', function () {
    config()->set('password-toolkit.leetspeak_conversion', 'no');

    expect(withoutDeprecations(fn () => Options::fromConfig())->leetspeak->value)->toBe('none');
});
