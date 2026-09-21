<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

/**
 * A fork-reachable workflow must never carry a privileged trigger, an inherited
 * secret or a write token. See the contribution contract.
 */
final readonly class WorkflowSafetyCheck implements Check
{
    private const array FORBIDDEN = ['pull_request_target', 'workflow_run', 'secrets: inherit', 'secrets:inherit'];

    public function __construct(private string $root)
    {
    }

    public function name(): string
    {
        return 'Workflow safety';
    }

    public function examine(): CheckResult
    {
        $directory = $this->root.'/.github/workflows';
        if (!is_dir($directory)) {
            return CheckResult::skip($this->name(), 'no .github/workflows directory');
        }

        $files = scandir($directory);
        if (false === $files) {
            return CheckResult::fail($this->name(), 'could not read '.$directory);
        }

        $offenders = [];
        foreach ($files as $file) {
            if (!str_ends_with($file, '.yml') && !str_ends_with($file, '.yaml')) {
                continue;
            }

            $contents = file_get_contents($directory.'/'.$file);
            if (false === $contents) {
                continue;
            }

            $haystack = strtolower($contents);
            foreach (self::FORBIDDEN as $token) {
                if (str_contains($haystack, $token)) {
                    $offenders[] = $file.' ('.$token.')';
                }
            }
        }

        if ([] === $offenders) {
            return CheckResult::pass($this->name(), 'no privileged trigger in a fork-reachable workflow');
        }

        return CheckResult::fail($this->name(), 'privileged workflow content: '.implode(', ', $offenders));
    }
}
