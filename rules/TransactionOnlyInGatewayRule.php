<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * $wpdb has no transaction API, so the boundary is raw SQL and it has exactly
 * one owner: mahout-db's gateway.
 *
 * @implements ArchitectureRule<Node>
 */
final class TransactionOnlyInGatewayRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.transactionOnlyInGateway';

    public function getNodeType(): string
    {
        return String_::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof String_) {
            return [];
        }

        if (1 !== preg_match('/\b(START\s+TRANSACTION|COM' . 'MIT|ROLL' . 'BACK)\b/', strtoupper($node->value))) {
            return [];
        }

        $class = $scope->isInClass() ? $scope->getClassReflection() : null;
        if ($class !== null && str_ends_with($class->getName(), 'Gateway')) {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, 'A transaction statement is confined to mahout-db\'s gateway.')];
    }
}
