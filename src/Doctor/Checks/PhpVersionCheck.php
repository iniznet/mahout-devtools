<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

/**
 * The PHP floor is 8.4 (PLT-01). A lower runtime cannot load this package.
 */
final readonly class PhpVersionCheck implements Check
{
    public function __construct(private string $minimum = '8.4')
    {
    }

    public function name(): string
    {
        return 'PHP version';
    }

    public function examine(): CheckResult
    {
        if (version_compare(PHP_VERSION, $this->minimum, '>=')) {
            return CheckResult::pass($this->name(), sprintf('%s >= %s', PHP_VERSION, $this->minimum));
        }

        return CheckResult::fail($this->name(), sprintf('%s < %s', PHP_VERSION, $this->minimum));
    }
}
