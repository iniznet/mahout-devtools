<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\RuleError;

/**
 * A property that is neither declared nor served by a magic accessor cannot be
 * seen by static analysis, so it is banned.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoDynamicPropertyRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noDynamicProperty';

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return PropertyFetch::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof PropertyFetch) {
            return [];
        }

        if (!$node->name instanceof Identifier) {
            return [Violation::at($node, self::IDENTIFIER, 'A dynamic property name is banned; declare the property.')];
        }

        $property = $node->name->toString();
        foreach ($scope->getType($node->var)->getObjectClassNames() as $className) {
            if (!$this->reflectionProvider->hasClass($className)) {
                continue;
            }

            $reflection = $this->reflectionProvider->getClass($className);
            if ($reflection->hasProperty($property) || $reflection->hasMethod('__get') || $reflection->hasMethod('__set')) {
                continue;
            }

            return [Violation::at(
                $node,
                self::IDENTIFIER,
                sprintf('Property $%s is not declared on %s; dynamic properties are banned.', $property, $className),
            )];
        }

        return [];
    }
}
