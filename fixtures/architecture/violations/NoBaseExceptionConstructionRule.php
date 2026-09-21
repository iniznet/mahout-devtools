<?php
declare(strict_types=1);

namespace Fixture\Violations\BaseException;

function boom(): void
{
    throw new \RuntimeException('x'); // EXPECT: mahout.arch.noBaseExceptionConstruction, mahout.arch.exceptionNamedConstructor
}
