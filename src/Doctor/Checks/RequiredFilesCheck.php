<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

/**
 * Every named path, relative to a root, must be a file. One check per group so
 * the doctor's table names what is absent rather than that something is.
 */
final readonly class RequiredFilesCheck implements Check
{
    /**
     * @param list<string> $relativePaths
     */
    public function __construct(
        private string $root,
        private string $label,
        private array $relativePaths,
    ) {
    }

    public function name(): string
    {
        return $this->label;
    }

    public function examine(): CheckResult
    {
        $missing = [];
        foreach ($this->relativePaths as $relativePath) {
            if (!is_file($this->root.'/'.$relativePath)) {
                $missing[] = $relativePath;
            }
        }

        if ([] === $missing) {
            return CheckResult::pass($this->name(), sprintf('%d files present', \count($this->relativePaths)));
        }

        return CheckResult::fail($this->name(), 'missing: '.implode(', ', $missing));
    }
}
