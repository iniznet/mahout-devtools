<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * A trait with state is a concealed dependency. The expected count of traits
 * across the family is zero.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoTraitPropertyRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noTraitProperty';

    public function getNodeType(): string
    {
        return Property::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Property) {
            return [];
        }

        $trait = $scope->getTraitReflection();
        if (null === $trait) {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, sprintf('Trait %s must not declare a property.', $trait->getName()))];
    }
}
