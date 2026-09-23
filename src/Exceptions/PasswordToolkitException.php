<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base for everything this package throws.
 *
 * Applications catch this one type to mean "the toolkit could not do its job",
 * and match on the concrete subclass only when they intend to handle a
 * specific cause differently.
 */
abstract class PasswordToolkitException extends RuntimeException
{
    /**
     * Create a new exception instance.
     *
     * Sealed so that new static() below is always safe: no subclass can
     * change what constructing one costs.
     */
    final public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create a new exception with the given reason as its message.
     */
    public static function because(string $reason): static
    {
        return new static($reason);
    }
}
