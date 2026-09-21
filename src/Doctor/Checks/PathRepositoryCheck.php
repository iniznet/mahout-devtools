<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

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

        $repositories = $decoded['repositories'] ?? null;
        if (!\is_array($repositories)) {
            return CheckResult::pass($this->name(), 'no repositories block');
        }

        foreach ($repositories as $repository) {
            if (!\is_array($repository)) {
                continue;
            }
            $type = $repository['type'] ?? null;
            if (\is_string($type) && 'path' === $type) {
                return CheckResult::fail($this->name(), 'a path repository is committed; use composer.dev.json');
            }
        }

        return CheckResult::pass($this->name(), 'no path repository');
    }
}
