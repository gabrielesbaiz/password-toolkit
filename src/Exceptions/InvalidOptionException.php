<?php

declare(strict_types=1);

namespace Gabrielesbaiz\PasswordToolkit\Exceptions;

/**
 * A configuration or builder value the generator cannot make sense of.
 *
 * 1.x threw a bare \InvalidArgumentException from generate(); that call no
 * longer exists in 2.0, so this does not extend it. Catch
 * PasswordToolkitException instead.
 */
final class InvalidOptionException extends PasswordToolkitException {}
