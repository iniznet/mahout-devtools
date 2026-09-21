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
 * The keyword is matched in a statement position — the beginning of the literal
 * or the character after a semicolon — so the rule constrains statements rather
 * than prose. An exception message may explain a rollback without becoming one.
 *
 * @implements ArchitectureRule<Node>
 */
final class TransactionOnlyInGatewayRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.transactionOnlyInGateway';

    /**
     * A transaction statement, at the position SQL puts one.
     *
     * This is the package's single matcher for the question "is this literal a
     * transaction statement"; a consumer's own test reads the constant rather
     * than repeating the pattern, so the gate and its proof cannot drift apart.
     */
    public const STATEMENT_PATTERN = '/(?:^|;)\s*(?:START\s+TRANSACTION|COMMIT|ROLLBACK)\b/i';

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

        if (1 !== preg_match(self::STATEMENT_PATTERN, $node->value)) {
            return [];
        }

        $class = $scope->isInClass() ? $scope->getClassReflection() : null;
        if ($class !== null && str_ends_with($class->getName(), 'Gateway')) {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, 'A transaction statement is confined to mahout-db\'s gateway.')];
    }
}
