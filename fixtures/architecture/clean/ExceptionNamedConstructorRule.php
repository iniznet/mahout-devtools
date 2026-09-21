<?php
declare(strict_types=1);

namespace Fixture\Clean\ExceptionCtor;

// EXPECT-NONE: the exception is built inside its own named constructor.
final class PackageError extends \RuntimeException
{
    public static function because(): self
    {
        return new self('x');
    }
}
