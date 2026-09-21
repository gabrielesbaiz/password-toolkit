<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Console;

use Gabrielesbaiz\PasswordToolkit\Generator\Options;
use Gabrielesbaiz\PasswordToolkit\Generator\PasswordBuilder;
use Gabrielesbaiz\PasswordToolkit\PasswordToolkit;
use Illuminate\Console\Command;

class GeneratePasswordCommand extends Command
{
    protected $signature = 'password-toolkit:generate
        {count=1 : How many passwords to generate}
        {--report : Include a strength report for each password}
        {--json : Emit JSON instead of a table}
        {--list : List the dictionaries that resolve, and generate nothing}
        {--locale= : Adjective locale, defaults to the application locale}
        {--only=* : Restrict to these dictionary keys}
        {--except=* : Exclude these dictionary keys}
        {--type=* : Restrict to people, things, or both}
        {--separator= : Separator between segments}
        {--digits= : Length of the numeric segment}
        {--position= : Where the numbers go: start, middle or end}
        {--leet= : Leetspeak mode: none, basic or advanced}
        {--no-numbers : Omit the numeric segment}';

    protected $description = 'Generate memorable passwords';

    public function handle(PasswordToolkit $toolkit): int
    {
        $builder = $this->applyOptions($toolkit->make());

        if ($this->option('list')) {
            return $this->listDictionaries($toolkit, $builder->options());
        }

        $count = max(1, (int) $this->argument('count'));

        if (! $this->option('report')) {
            $passwords = $builder->many($count);

            if ($this->option('json')) {
                $this->line((string) json_encode($passwords, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }

            foreach ($passwords as $password) {
                $this->line($password);
            }

            return self::SUCCESS;
        }

        $rows = $builder->manyWithReport($count);

        if ($this->option('json')) {
            $this->line((string) json_encode(
                array_map(static fn (array $row): array => [
                    'password' => $row['password'],
                    'report' => $row['report']->toArray(),
                ], $rows),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return self::SUCCESS;
        }

        $this->table(
            ['Password', 'Score', 'Strength', 'Entropy', 'Crack time'],
            array_map(static fn (array $row): array => [
                $row['password'],
                $row['report']->score,
                $row['report']->displayLabel(),
                round($row['report']->entropyBits, 1).' bits',
                $row['report']->crackTimeHuman,
            ], $rows),
        );

        return self::SUCCESS;
    }

    protected function applyOptions(PasswordBuilder $builder): PasswordBuilder
    {
        if (is_string($locale = $this->option('locale')) && $locale !== '') {
            $builder = $builder->locale($locale);
        }

        if ($only = $this->arrayOption('only')) {
            $builder = $builder->only($only);
        }

        if ($except = $this->arrayOption('except')) {
            $builder = $builder->except($except);
        }

        if ($types = $this->arrayOption('type')) {
            $builder = $builder->types($types);
        }

        if (is_string($separator = $this->option('separator'))) {
            $builder = $builder->separator($separator);
        }

        if ($this->option('no-numbers')) {
            $builder = $builder->withoutNumbers();
        } elseif (is_string($digits = $this->option('digits')) && $digits !== '') {
            $builder = $builder->digits((int) $digits);
        }

        if (is_string($position = $this->option('position')) && $position !== '') {
            $builder = $builder->numbersAt($position);
        }

        if (is_string($leet = $this->option('leet')) && $leet !== '') {
            $builder = $builder->leet($leet);
        }

        return $builder;
    }

    protected function listDictionaries(PasswordToolkit $toolkit, Options $options): int
    {
        $dictionaries = $toolkit->dictionaries($options);

        if ($dictionaries->isEmpty()) {
            $this->components->warn('No dictionaries resolve with the current selection.');

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($dictionaries->values()->all(), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->table(
            ['Key', 'Type', 'Entries', 'Source'],
            $dictionaries
                ->map(static fn (array $row): array => [
                    $row['key'],
                    $row['type'],
                    $row['count'],
                    $row['built_in'] ? 'built-in' : 'custom',
                ])
                ->values()
                ->all(),
        );

        $pools = $toolkit->poolSizes($options);
        $this->newLine();
        $this->components->info("{$dictionaries->count()} dictionaries, {$pools['names']} names, ~{$pools['adjectives']} adjectives each.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function arrayOption(string $name): array
    {
        /** @var array<int, string> $values */
        $values = (array) $this->option($name);

        // Accept both repeated flags and one comma-separated value.
        $flattened = [];

        foreach ($values as $value) {
            foreach (explode(',', $value) as $part) {
                if (($part = trim($part)) !== '') {
                    $flattened[] = $part;
                }
            }
        }

        return $flattened;
    }
}
