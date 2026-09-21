<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Contracts;

use Iniznet\Mahout\Devtools\Doctor\DoctorReport;

/**
 * Runs the check set and returns an ordered report.
 */
interface Doctor
{
    public function examine(): DoctorReport;
}
