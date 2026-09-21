<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\RuleError;

/**
 * REP-11: the local-development path repository override is uncommitted, so a
 * committed composer.json with a path repository fails the build.
 *
 * @implements ArchitectureRule<FileNode>
 */
final class NoPathRepositoryRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noPathRepository';

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $manifest = $this->nearestManifest(dirname($scope->getFile()));
        if (null === $manifest) {
            return [];
        }

        $raw = @file_get_contents($manifest);
        if (false === $raw) {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($decoded) || !isset($decoded['repositories']) || !is_array($decoded['repositories'])) {
            return [];
        }

        foreach ($decoded['repositories'] as $repository) {
            if (is_array($repository) && 'path' === ($repository['type'] ?? null)) {
                return [Violation::at($node, self::IDENTIFIER, 'A committed path repository is banned; use an uncommitted override.')];
            }
        }

        return [];
    }

    private function nearestManifest(string $directory): ?string
    {
        while (true) {
            $candidate = $directory.'/composer.json';
            if (is_file($candidate)) {
                return $candidate;
            }

            $parent = dirname($directory);
            if ($parent === $directory) {
                return null;
            }

            $directory = $parent;
        }
    }
}
