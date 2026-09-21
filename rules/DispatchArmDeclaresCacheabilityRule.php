<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * The dispatch arm declares the Surface's cacheability as a required argument.
 * A plan without one cannot type-check at runtime, so the gate catches it.
 *
 * @implements ArchitectureRule<Node>
 */
final class DispatchArmDeclaresCacheabilityRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.dispatchArmDeclaresCacheability';

    public function getNodeType(): string
    {
        return Node::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof StaticCall && $node->class instanceof Name) {
            if (!$node->name instanceof Node\Identifier || 'wrapped' !== $node->name->toString()) {
                return [];
            }
            $className = $scope->resolveName($node->class);
            $arguments = $node->args;
        } elseif ($node instanceof New_ && $node->class instanceof Name) {
            $className = $scope->resolveName($node->class);
            $arguments = $node->args;
        } else {
            return [];
        }

        if ('SurfacePlan' !== $className && !str_ends_with($className, '\\SurfacePlan')) {
            return [];
        }

        foreach ($arguments as $argument) {
            if ($argument instanceof Arg && $argument->name instanceof Node\Identifier && 'cacheability' === $argument->name->toString()) {
                return [];
            }
        }

        return [Violation::at($node, self::IDENTIFIER, 'A SurfacePlan must declare its cacheability explicitly.')];
    }
}
