<?php
declare(strict_types=1);

namespace Fixture\Clean\BaseException;

final class PackageError extends \RuntimeException
{
    public static function because(): self
    {
        return new self('x');
    }
}

// EXPECT-NONE: a package exception with a named constructor.
function boom(): void
{
    throw PackageError::because();
}
