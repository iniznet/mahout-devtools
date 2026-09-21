<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

/**
 * A composer autoloader must be present; without it nothing in the package
 * graph loads.
 */
final readonly class AutoloaderCheck implements Check
{
    public function __construct(private string $root)
    {
    }

    public function name(): string
    {
        return 'Composer autoloader';
    }

    public function examine(): CheckResult
    {
        $autoloader = $this->root.'/vendor/autoload.php';

        if (is_file($autoloader)) {
            return CheckResult::pass($this->name(), 'vendor/autoload.php present');
        }

        return CheckResult::fail($this->name(), 'vendor/autoload.php missing; run composer install');
    }
}
