<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * The dependency arrow points one way: a Repository supplies data and never
 * imports presentation.
 *
 * @implements ArchitectureRule<Node>
 */
final class LayerDirectionRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.layerDirection';

    public function getNodeType(): string
    {
        return Name::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Name || !$scope->isInClass()) {
            return [];
        }

        $class = $scope->getClassReflection();
        if (!str_ends_with($class->getName(), 'Repository') && !str_contains($class->getName(), '\\Repositories\\')) {
            return [];
        }

        $resolved = $scope->resolveName($node);
        if (!str_contains($resolved, '\\Components\\') && !str_ends_with($resolved, 'Component')) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('Repository %s must not reach into presentation (%s).', $class->getName(), $resolved),
        )];
    }
}
