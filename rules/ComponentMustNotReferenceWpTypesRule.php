<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * A Component must be testable without WordPress, so it never names a WP_*
 * type. Data arrives as a DTO.
 *
 * @implements ArchitectureRule<Node>
 */
final class ComponentMustNotReferenceWpTypesRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.componentMustNotReferenceWpTypes';

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
        if (!str_contains($class->getName(), '\\Components\\') && !str_ends_with($class->getName(), 'Component')) {
            return [];
        }

        $resolved = $scope->resolveName($node);
        if (!str_starts_with($resolved, 'WP_')) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('Component %s must not reference the WordPress type %s.', $class->getName(), $resolved),
        )];
    }
}
