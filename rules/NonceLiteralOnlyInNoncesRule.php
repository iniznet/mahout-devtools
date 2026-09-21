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
 * A nonce action is a typed constant on a Nonces class, so a rename is one
 * change and a drift is a build failure.
 *
 * @implements ArchitectureRule<Node>
 */
final class NonceLiteralOnlyInNoncesRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.nonceLiteralOnlyInNonces';

    /** @var list<string> */
    private const FUNCTIONS = ['wp_nonce_field', 'wp_verify_nonce', 'check_admin_referer', 'check_ajax_referer', 'wp_create_nonce'];

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

        $function = $node->name->toLowerString();
        if (!in_array($function, self::FUNCTIONS, true)) {
            return [];
        }

        if (!isset($node->args[0]) || !$node->args[0] instanceof Arg || !$node->args[0]->value instanceof String_) {
            return [];
        }

        if ($scope->isInClass() && Violation::shortName($scope->getClassReflection()->getName()) === 'Nonces') {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('Nonce action literal at %s() is banned; declare it on a Nonces class.', $function),
        )];
    }
}
