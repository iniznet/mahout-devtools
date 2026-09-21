<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

/**
 * The producer side of the divergence rule: this repository is the source of
 * the architecture rules, so its phpstan.neon must register every rule file it
 * ships. A rule that is not registered is a rule that does not run.
 */
final readonly class ArchitectureRuleCatalogCheck implements Check
{
    public function __construct(private string $root)
    {
    }

    public function name(): string
    {
        return 'Architecture rule catalog';
    }

    public function examine(): CheckResult
    {
        $configuration = $this->root.'/phpstan.neon';
        if (!is_file($configuration)) {
            return CheckResult::fail($this->name(), 'phpstan.neon is missing');
        }

        $contents = file_get_contents($configuration);
        if (false === $contents) {
            return CheckResult::fail($this->name(), 'phpstan.neon is unreadable');
        }

        $rules = array_values(array_filter(
            glob($this->root.'/rules/*Rule.php') ?: [],
            static fn (string $file): bool => 'ArchitectureRule.php' !== basename($file),
        ));

        if ([] === $rules) {
            return CheckResult::fail($this->name(), 'no architecture rule files exist in rules/');
        }

        $missing = [];
        foreach ($rules as $rule) {
            $short = basename($rule, '.php');
            if (!str_contains($contents, $short)) {
                $missing[] = $short;
            }
        }

        if ([] === $missing) {
            return CheckResult::pass($this->name(), sprintf('%d rules registered', \count($rules)));
        }

        return CheckResult::fail($this->name(), 'rules not registered: '.implode(', ', $missing));
    }
}
