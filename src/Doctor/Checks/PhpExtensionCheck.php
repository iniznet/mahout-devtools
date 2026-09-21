<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

/**
 * The required extension set is json, hash and mysqli.
 *
 * json and hash are core's own requirement; mysqli is the driver wpdb actually
 * uses. pdo, mbstring and intl are deliberately absent: nothing calls them.
 */
final readonly class PhpExtensionCheck implements Check
{
    /**
     * @param list<string> $extensions
     */
    public function __construct(private array $extensions)
    {
    }

    public function name(): string
    {
        return 'PHP extensions';
    }

    public function examine(): CheckResult
    {
        $missing = [];
        foreach ($this->extensions as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }

        if ([] === $missing) {
            return CheckResult::pass($this->name(), implode(', ', $this->extensions).' loaded');
        }

        return CheckResult::fail($this->name(), 'missing: '.implode(', ', $missing));
    }
}
