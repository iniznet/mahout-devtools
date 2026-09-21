<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Contracts\Doctor as DoctorContract;

/**
 * The doctor: every check it is constructed with, run once, in order.
 */
final readonly class Doctor implements DoctorContract
{
    /**
     * @param list<Check> $checks
     */
    public function __construct(private array $checks)
    {
    }

    public function examine(): DoctorReport
    {
        $results = [];
        foreach ($this->checks as $check) {
            $results[] = $check->examine();
        }

        return new DoctorReport($results);
    }
}
