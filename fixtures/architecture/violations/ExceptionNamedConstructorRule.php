<?php
declare(strict_types=1);

namespace Fixture\Violations\ExceptionCtor;

final class PackageError extends \RuntimeException
{
    public static function because(): self
    {
        return new self('x');
    }
}

function make(): PackageError
{
    return new PackageError('x'); // EXPECT: mahout.arch.exceptionNamedConstructor
}
