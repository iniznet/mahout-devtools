<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Contracts;

use Iniznet\Mahout\Devtools\Doctor\CheckResult;

/**
 * One named installation check.
 *
 * A consumer or a later slice adds a check by implementing this interface and
 * passing the instance to the doctor; the doctor itself never grows a branch.
 */
interface Check
{
    public function name(): string;

    public function examine(): CheckResult;
}
