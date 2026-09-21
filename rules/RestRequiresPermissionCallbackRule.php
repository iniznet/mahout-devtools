<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * A REST route without an explicit permission_callback is indistinguishable
 * from an unprotected private route, so absence is a build failure.
 *
 * @implements ArchitectureRule<Node>
 */
final class RestRequiresPermissionCallbackRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.restRequiresPermissionCallback';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return [];
        }

        if ('register_rest_route' !== $node->name->toLowerString()) {
            return [];
        }

        $arguments = null;
        foreach ($node->args as $argument) {
            if ($argument instanceof Arg && $argument->name instanceof Node\Identifier && 'args' === $argument->name->toString() && $argument->value instanceof Array_) {
                $arguments = $argument->value;
                break;
            }
        }

        if (null === $arguments && isset($node->args[2]) && $node->args[2] instanceof Arg && $node->args[2]->value instanceof Array_) {
            $arguments = $node->args[2]->value;
        }

        if (null === $arguments) {
            return [];
        }

        if ($this->declaresPermissionCallback($arguments)) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            "register_rest_route() must declare an explicit 'permission_callback' key.",
        )];
    }

    private function declaresPermissionCallback(Array_ $arguments): bool
    {
        foreach ($arguments->items as $item) {
            if ($item->key instanceof String_ && 'permission_callback' === $item->key->value) {
                return true;
            }
        }

        return false;
    }
}
