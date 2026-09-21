<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * A trait that calls a method it does not declare hides a dependency on the
 * using class. Traits are stateless and self-contained or they do not exist.
 *
 * @implements ArchitectureRule<Node>
 */
final class TraitNoUndeclaredMemberRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.traitNoUndeclaredMember';

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return [];
        }

        if (!$node->var instanceof Variable || 'this' !== $node->var->name) {
            return [];
        }

        $trait = $scope->getTraitReflection();
        if (null === $trait) {
            return [];
        }

        $method = $node->name->toString();
        if ($trait->hasNativeMethod($method) || $trait->hasMethod($method)) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('Trait %s calls $this->%s() but does not declare it.', $trait->getName(), $method),
        )];
    }
}
