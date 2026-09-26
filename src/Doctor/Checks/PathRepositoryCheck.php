<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

/**
 * REP-11: a local-development path repository must never be committed — in the
 * manifest or in the lock. The manifest half is the rule as written; the lock
 * half is what makes a fresh clone resolvable, because a `path` dist points at
 * a sibling directory that exists only on the machine that wrote it.
 */

/**
 * REP-11: a local-development path repository must never be committed.
 */
final readonly class PathRepositoryCheck implements Check
{
    public function __construct(private string $root)
    {
    }

    public function name(): string
    {
        return 'No path repository';
    }

    public function examine(): CheckResult
    {
        $manifest = $this->root.'/composer.json';
        if (!is_file($manifest)) {
            return CheckResult::fail($this->name(), 'composer.json missing');
        }

        $raw = file_get_contents($manifest);
        if (false === $raw) {
            return CheckResult::fail($this->name(), 'composer.json unreadable');
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return CheckResult::fail($this->name(), 'composer.json is not valid JSON: '.$exception->getMessage());
        }

        if (!\is_array($decoded)) {
            return CheckResult::fail($this->name(), 'composer.json is not a JSON object');
        }

        $failures = $this->manifestFailures($decoded);
        $failures = array_merge($failures, $this->lockFailures());

        if ([] !== $failures) {
            return CheckResult::fail($this->name(), implode('; ', $failures));
        }

        return CheckResult::pass($this->name(), 'no committed path repository, in the manifest or the lock');
    }

    /**
     * @param array<array-key, mixed> $decoded
     *
     * @return list<string>
     */
    private function manifestFailures(array $decoded): array
    {
        $repositories = $decoded['repositories'] ?? null;

        if (!\is_array($repositories)) {
            return [];
        }

        $failures = [];

        foreach ($repositories as $name => $repository) {
            if (!\is_array($repository)) {
                continue;
            }

            if ('path' === ($repository['type'] ?? null)) {
                $failures[] = 'a path repository ('.$name.') is committed; use composer.dev.json';
            }
        }

        return $failures;
    }

    /**
     * @return list<string>
     */
    private function lockFailures(): array
    {
        $path = $this->root.'/composer.lock';

        if (!is_file($path)) {
            // A repository without a lock pins nothing; the manifest half above
            // is the whole of what can be proven here.
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!\is_array($decoded)) {
            return ['composer.lock is not a JSON object'];
        }

        $failures = [];

        foreach (['packages', 'packages-dev'] as $section) {
            $packages = $decoded[$section] ?? null;

            if (!\is_array($packages)) {
                continue;
            }

            foreach ($packages as $package) {
                if (!\is_array($package)) {
                    continue;
                }

                $dist = $package['dist'] ?? null;

                if (!\is_array($dist) || 'path' !== ($dist['type'] ?? null)) {
                    continue;
                }

                $name = $package['name'] ?? null;
                $label = \is_string($name) && '' !== $name ? $name : 'an unnamed package';
                $failures[] = $label.' is locked to a path dist; the lock must resolve over a committed repository';
            }
        }

        return $failures;
    }
}
