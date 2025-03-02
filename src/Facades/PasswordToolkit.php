<?php

namespace Gabrielesbaiz\PasswordToolkit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Gabrielesbaiz\PasswordToolkit\PasswordToolkit
 */
class PasswordToolkit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Gabrielesbaiz\PasswordToolkit\PasswordToolkit::class;
    }
}
