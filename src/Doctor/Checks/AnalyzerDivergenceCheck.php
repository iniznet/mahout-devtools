<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

/**
 * The divergence rule: a repository references the analyzer configuration and
 * the architecture rules shipped by the pinned mahout-devtools; it never
 * carries a copy. See the contribution contract, section 6.
 */
final readonly class AnalyzerDivergenceCheck implements Check
{
    private const string PACKAGE = 'iniznet/mahout-devtools';

    public function __construct(private string $root)
    {
    }

    public function name(): string
    {
        return 'Analyzer divergence';
    }

    public function examine(): CheckResult
    {
        $failures = array_merge(
            $this->manifestFailures(),
            $this->lockFailures(),
            $this->analyzerFailures(),
            $this->localRuleFailures(),
        );

        if ([] === $failures) {
            return CheckResult::pass($this->name(), 'analyzer files reference the pinned mahout-devtools configuration');
        }

        return CheckResult::fail($this->name(), implode('; ', $failures));
    }

    /**
     * @return list<string>
     */
    private function manifestFailures(): array
    {
        $manifest = $this->read($this->root.'/composer.json');
        if (null === $manifest) {
            return ['composer.json unreadable'];
        }

        $decoded = json_decode($manifest, true);
        if (!\is_array($decoded)) {
            return ['composer.json is not a JSON object'];
        }

        $declared = false;
        foreach (['require', 'require-dev'] as $section) {
            $packages = $decoded[$section] ?? null;
            if (\is_array($packages) && array_key_exists(self::PACKAGE, $packages)) {
                $declared = true;
            }
        }

        $failures = [];
        if (!$declared) {
            $failures[] = 'composer.json does not require '.self::PACKAGE;
        }
        if (!is_dir($this->root.'/docs/decisions')) {
            $failures[] = 'docs/decisions/ is missing';
        }

        return $failures;
    }

    /**
     * @return list<string>
     */
    private function lockFailures(): array
    {
        $lock = $this->read($this->root.'/composer.lock');
        if (null === $lock) {
            return ['composer.lock unreadable; the pinned configuration cannot be proven'];
        }

        $decoded = json_decode($lock, true);
        if (!\is_array($decoded)) {
            return ['composer.lock is not a JSON object'];
        }

        foreach (['packages', 'packages-dev'] as $section) {
            $packages = $decoded[$section] ?? null;
            if (!\is_array($packages)) {
                continue;
            }
            foreach ($packages as $package) {
                if (\is_array($package) && self::PACKAGE === ($package['name'] ?? null)) {
                    return [];
                }
            }
        }

        return ['composer.lock does not pin '.self::PACKAGE];
    }

    /**
     * @return list<string>
     */
    private function analyzerFailures(): array
    {
        $failures = [];

        $phpstan = $this->read($this->root.'/phpstan.neon');
        if (null === $phpstan) {
            $failures[] = 'phpstan.neon missing';
        } else {
            if (!str_contains($phpstan, self::PACKAGE.'/phpstan.neon')) {
                $failures[] = 'phpstan.neon does not include the shared configuration';
            }
            if (1 === preg_match('/^\s*rules:/m', $phpstan)) {
                $failures[] = 'phpstan.neon declares local rules';
            }
            if (1 === preg_match('/^\s*services:/m', $phpstan)) {
                $failures[] = 'phpstan.neon declares local services';
            }
        }

        $psalm = $this->read($this->root.'/psalm.xml');
        if (null === $psalm) {
            $failures[] = 'psalm.xml missing';
        } else {
            if (!str_contains($psalm, self::PACKAGE.'/stubs/wordpress-stubs.php')) {
                $failures[] = 'psalm.xml does not reference the shared stubs';
            }
            if (str_contains($psalm, '<plugins>')) {
                $failures[] = 'psalm.xml loads a local plugin';
            }
        }

        $rector = $this->read($this->root.'/rector.php');
        if (null === $rector) {
            $failures[] = 'rector.php missing';
        } elseif (!str_contains($rector, self::PACKAGE.'/rector.php')) {
            $failures[] = 'rector.php does not require the shared configuration';
        }

        $fixer = $this->read($this->root.'/.php-cs-fixer.dist.php');
        if (null === $fixer) {
            $failures[] = '.php-cs-fixer.dist.php missing';
        } elseif (!str_contains($fixer, self::PACKAGE.'/.php-cs-fixer.dist.php')) {
            $failures[] = '.php-cs-fixer.dist.php does not require the shared configuration';
        }

        return $failures;
    }

    /**
     * @return list<string>
     */
    private function localRuleFailures(): array
    {
        $failures = [];

        $rules = glob($this->root.'/rules/*Rule.php') ?: [];
        if ([] !== $rules) {
            $failures[] = sprintf('%d local architecture rule file(s) copied into rules/', \count($rules));
        }

        if (is_file($this->root.'/phpstan-arch.neon')) {
            $failures[] = 'a local phpstan-arch.neon is present';
        }

        return $failures;
    }

    private function read(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return false === $contents ? null : $contents;
    }
}
