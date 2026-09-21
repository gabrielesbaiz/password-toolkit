<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Contracts;

use Gabrielesbaiz\PasswordToolkit\Generator\Options;
use Gabrielesbaiz\PasswordToolkit\Support\StrengthReport;

/**
 * The generating half of the toolkit.
 *
 * Extracted so an application can bind its own implementation — a fixed
 * sequence in tests, a corporate policy wrapper in production — without
 * having to subclass the concrete service.
 */
interface PasswordGenerator
{
    public function generate(?Options $options = null): string;

    /**
     * @return array<int, string>
     */
    public function generateMany(int $count, ?Options $options = null): array;

    /**
     * @return array{password: string, report: StrengthReport}
     */
    public function generateWithReport(?Options $options = null): array;

    /**
     * @return array<int, array{password: string, report: StrengthReport}>
     */
    public function generateManyWithReport(int $count, ?Options $options = null): array;
}
