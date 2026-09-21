<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor;

/**
 * The outcome of one doctor check.
 */
enum Status: string
{
    case Pass = 'PASS';
    case Warn = 'WARN';
    case Fail = 'FAIL';
    case Skip = 'SKIP';

    public function isFailure(): bool
    {
        return self::Fail === $this;
    }
}
