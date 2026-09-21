<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * Internal/ is private to its package. Referencing another package's Internal/
 * class binds the consumer to an implementation detail that may change in a
 * patch release.
 *
 * @implements ArchitectureRule<Node>
 */
final class InternalNotCrossPackageRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.internalNotCrossPackage';

    public function getNodeType(): string
    {
        return Name::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Name) {
            return [];
        }

        $resolved = $scope->resolveName($node);
        if (!str_contains($resolved, '\\Internal\\')) {
            return [];
        }

        $target = $this->packageOf($resolved);
        $current = $this->packageOf(($scope->getNamespace() ?? '').'\\probe');
        if ($target === null || $current === null || $target === $current) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('The Internal class %s belongs to another package (%s); use its Contracts instead.', $resolved, $target),
        )];
    }

    private function packageOf(string $fqcn): ?string
    {
        if (1 === preg_match('/^Iniznet\\\\Mahout\\\\([^\\\\]+)\\\\/', $fqcn, $matches)) {
            return $matches[1];
        }

        if (str_starts_with($fqcn, 'Iniznet\\Howdah\\')) {
            return 'Howdah';
        }

        return null;
    }
}
