<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Console;

use Illuminate\Console\Command;

class MakeDictionaryCommand extends Command
{
    protected $signature = 'password-toolkit:make-dictionary
        {key : The dictionary key, in snake_case}
        {--type=things : people or things}
        {--path= : Where to write it, defaults to the first configured dictionary path}
        {--locale= : Also scaffold an adjective file for this locale}
        {--force : Overwrite an existing file}';

    protected $description = 'Scaffold a dictionary of your own';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $key = (string) $this->argument('key');

        if (preg_match('/^[a-z0-9_]+$/', $key) !== 1) {
            $this->components->error("[{$key}] is not a valid key. Use lowercase letters, digits and underscores.");

            return self::FAILURE;
        }

        $type = (string) $this->option('type');

        if (! in_array($type, ['people', 'things'], true)) {
            $this->components->error("[{$type}] is not a valid type. Use people or things.");

            return self::FAILURE;
        }

        $directory = $this->directory();

        if ($directory === null) {
            $this->components->error(
                'No dictionary path configured. Add one to password-toolkit.dictionaries.paths, or pass --path.',
            );

            return self::FAILURE;
        }

        $path = rtrim($directory, '/')."/{$key}.json";

        if (file_exists($path) && ! $this->option('force')) {
            $this->components->error("{$path} already exists. Pass --force to overwrite it.");

            return self::FAILURE;
        }

        $this->ensureDirectory($directory);
        $this->write($path, [
            'key' => $key,
            'type' => $type,
            'values' => [
                ['name' => 'Example One', 'gender' => 'neutral'],
                ['name' => 'Example Two', 'gender' => 'neutral'],
            ],
        ]);

        $this->components->info("Dictionary scaffolded at {$path}");

        if (is_string($locale = $this->option('locale')) && $locale !== '') {
            $adjectivePath = rtrim($directory, '/')."/{$locale}/{$key}.json";

            if (file_exists($adjectivePath) && ! $this->option('force')) {
                $this->components->warn("{$adjectivePath} already exists, left alone.");

                return self::SUCCESS;
            }

            $this->ensureDirectory(dirname($adjectivePath));
            $this->write($adjectivePath, [
                'key' => $key,
                'locale' => $locale,
                'values' => [
                    ['name' => 'Bright', 'gender' => 'neutral'],
                    ['name' => 'Swift', 'gender' => 'neutral'],
                ],
            ]);

            $this->components->info("Adjectives scaffolded at {$adjectivePath}");
        }

        $this->newLine();
        $this->components->bulletList([
            'Add the directory to password-toolkit.dictionaries.paths if it is not there yet.',
            'Check it resolves: php artisan password-toolkit:generate --list',
            "Use it alone: php artisan password-toolkit:generate --only={$key}",
        ]);

        return self::SUCCESS;
    }

    /**
     * Resolve the directory to scaffold into, or null when none is configured.
     */
    protected function directory(): ?string
    {
        if (is_string($path = $this->option('path')) && $path !== '') {
            return $path;
        }

        /** @var array<int, string> $paths */
        $paths = (array) config('password-toolkit.dictionaries.paths', []);

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                return $path;
            }
        }

        return null;
    }

    /**
     * Create the directory when it does not exist yet.
     */
    protected function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }
    }

    /**
     * Write the scaffolded data to a JSON file.
     *
     * @param  array<string, mixed>  $data
     */
    protected function write(string $path, array $data): void
    {
        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );
    }
}
