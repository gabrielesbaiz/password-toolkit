<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Facades;

use Gabrielesbaiz\PasswordToolkit\Generator\Options;
use Gabrielesbaiz\PasswordToolkit\Generator\PasswordBuilder;
use Gabrielesbaiz\PasswordToolkit\PasswordToolkit as PasswordToolkitService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PasswordBuilder make(Options|null $options = null)
 * @method static string generate(Options|null $options = null)
 * @method static array<int, string> generateMany(int $count, Options|null $options = null)
 * @method static array{password: string, report: \Gabrielesbaiz\PasswordToolkit\Support\StrengthReport} generateWithReport(Options|null $options = null)
 * @method static array<int, array{password: string, report: \Gabrielesbaiz\PasswordToolkit\Support\StrengthReport}> generateManyWithReport(int $count, Options|null $options = null)
 * @method static \Gabrielesbaiz\PasswordToolkit\Support\StrengthReport strength(string $password, Options|null $options = null)
 * @method static \Gabrielesbaiz\PasswordToolkit\Support\StrengthReport structuralReport(string $password, Options|null $options = null)
 * @method static array{names: int, adjectives: int} poolSizes(Options|null $options = null)
 * @method static \Illuminate\Support\Collection dictionaries(Options|null $options = null)
 * @method static void registerDictionary(string $key, array<int, array{name: string, gender?: string}|string> $values, string $type = 'things', string|null $locale = null)
 * @method static void flushCache()
 *
 * @see PasswordToolkitService
 */
class PasswordToolkit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PasswordToolkitService::class;
    }
}
