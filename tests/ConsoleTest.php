<?php

declare(strict_types=1);

use Gabrielesbaiz\PasswordToolkit\Facades\PasswordToolkit;

beforeEach(function () {
    config()->set('password-toolkit.dictionaries.enabled', ['star_wars']);
    config()->set('password-toolkit.add_numbers', true);

    PasswordToolkit::flushCache();
});

describe('password-toolkit:generate', function () {
    it('prints one password by default', function () {
        $this->artisan('password-toolkit:generate')->assertSuccessful();
    });

    it('prints the requested count', function () {
        $output = artisanOutput('password-toolkit:generate', ['count' => 5]);

        expect(array_filter(explode("\n", trim($output))))->toHaveCount(5);
    });

    it('emits a JSON array', function () {
        $decoded = json_decode(artisanOutput('password-toolkit:generate', ['count' => 3, '--json' => true]), true);

        expect($decoded)->toBeArray()->toHaveCount(3);
    });

    it('emits JSON reports', function () {
        $decoded = json_decode(
            artisanOutput('password-toolkit:generate', ['count' => 2, '--json' => true, '--report' => true]),
            true,
        );

        expect($decoded)->toHaveCount(2)
            ->and($decoded[0])->toHaveKeys(['password', 'report'])
            ->and($decoded[0]['report'])->toHaveKeys(['score', 'label', 'crack_time_human']);
    });

    it('renders a report table', function () {
        $this->artisan('password-toolkit:generate', ['count' => 2, '--report' => true])
            ->expectsOutputToContain('Crack time')
            ->assertSuccessful();
    });

    it('honours the option overrides', function () {
        $output = trim(artisanOutput('password-toolkit:generate', [
            '--only' => ['star_wars'],
            '--separator' => '_',
            '--no-numbers' => true,
        ]));

        expect($output)->toContain('_')->not->toMatch('/\d/');
    });

    it('accepts a comma-separated only list', function () {
        $decoded = json_decode(artisanOutput('password-toolkit:generate', [
            '--list' => true,
            '--json' => true,
            '--only' => ['star_wars,italian_wines'],
        ]), true);

        expect(array_column($decoded, 'key'))->toEqualCanonicalizing(['italian_wines', 'star_wars']);
    });

    it('lists the resolving dictionaries', function () {
        $this->artisan('password-toolkit:generate', ['--list' => true])
            ->expectsOutputToContain('star_wars')
            ->assertSuccessful();
    });

    it('fails the listing when nothing resolves', function () {
        $this->artisan('password-toolkit:generate', ['--list' => true, '--only' => ['nope']])
            ->assertFailed();
    });
});

describe('password-toolkit:make-dictionary', function () {
    beforeEach(function () {
        $this->directory = sys_get_temp_dir().'/ptk-make-'.bin2hex(random_bytes(4));
        config()->set('password-toolkit.dictionaries.paths', [$this->directory]);
    });

    afterEach(function () {
        if (is_dir($this->directory)) {
            foreach ((array) glob($this->directory.'/{,*/}*.json', GLOB_BRACE) as $file) {
                unlink((string) $file);
            }
            foreach ((array) glob($this->directory.'/*', GLOB_ONLYDIR) as $sub) {
                rmdir((string) $sub);
            }
            rmdir($this->directory);
        }
    });

    it('scaffolds a dictionary into the configured path', function () {
        $this->artisan('password-toolkit:make-dictionary', ['key' => 'my_team'])->assertSuccessful();

        $decoded = json_decode((string) file_get_contents($this->directory.'/my_team.json'), true);

        expect($decoded)->toHaveKeys(['key', 'type', 'values'])
            ->and($decoded['key'])->toBe('my_team')
            ->and($decoded['type'])->toBe('things');
    });

    it('scaffolds an adjective file for a locale', function () {
        $this->artisan('password-toolkit:make-dictionary', ['key' => 'my_team', '--locale' => 'en'])
            ->assertSuccessful();

        expect(file_exists($this->directory.'/en/my_team.json'))->toBeTrue();
    });

    it('generates from the scaffolded dictionary', function () {
        $this->artisan('password-toolkit:make-dictionary', ['key' => 'my_team', '--type' => 'people'])
            ->assertSuccessful();

        PasswordToolkit::flushCache();
        config()->set('password-toolkit.dictionaries.enabled', ['my_team']);

        expect(PasswordToolkit::generate())->toStartWith('Example');
    });

    it('refuses an invalid key', function () {
        $this->artisan('password-toolkit:make-dictionary', ['key' => 'My Team'])->assertFailed();
    });

    it('refuses an invalid type', function () {
        $this->artisan('password-toolkit:make-dictionary', ['key' => 'ok', '--type' => 'places'])->assertFailed();
    });

    it('refuses to overwrite without --force', function () {
        $this->artisan('password-toolkit:make-dictionary', ['key' => 'my_team'])->assertSuccessful();
        $this->artisan('password-toolkit:make-dictionary', ['key' => 'my_team'])->assertFailed();
        $this->artisan('password-toolkit:make-dictionary', ['key' => 'my_team', '--force' => true])->assertSuccessful();
    });

    it('fails when no path is configured', function () {
        config()->set('password-toolkit.dictionaries.paths', []);

        $this->artisan('password-toolkit:make-dictionary', ['key' => 'my_team'])->assertFailed();
    });
});
