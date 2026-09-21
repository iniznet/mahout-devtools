<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * A capability is a typed constant on a Capabilities class; a string literal at
 * the check site is a typo that silently denies or grants.
 *
 * @implements ArchitectureRule<Node>
 */
final class CapabilityLiteralOnlyInCapabilitiesRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.capabilityLiteralOnlyInCapabilities';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name || 'current_user_can' !== $node->name->toLowerString()) {
            return [];
        }

        if (!isset($node->args[0]) || !$node->args[0] instanceof Arg || !$node->args[0]->value instanceof String_) {
            return [];
        }

        if ($scope->isInClass() && Violation::shortName($scope->getClassReflection()->getName()) === 'Capabilities') {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            'Capability literal at current_user_can() is banned; declare it on a Capabilities class.',
        )];
    }
}
