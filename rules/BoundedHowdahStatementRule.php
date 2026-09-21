<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * A statement against a howdah table carries a LIMIT or a primary-key
 * equality. An unbounded statement is a table scan behind a page render.
 *
 * @implements ArchitectureRule<Node>
 */
final class BoundedHowdahStatementRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.boundedHowdahStatement';

    /** @var list<string> */
    private const METHODS = ['query', 'get_results', 'get_row', 'get_col', 'get_var', 'get_blog_results'];

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

        if (!in_array($node->name->toString(), self::METHODS, true)) {
            return [];
        }

        if (!$node->var instanceof Variable || 'wpdb' !== $node->var->name) {
            return [];
        }

        if (!isset($node->args[0]) || !$node->args[0] instanceof Arg || !$node->args[0]->value instanceof String_) {
            return [];
        }

        $sql = $node->args[0]->value->value;
        if (1 !== preg_match('/\bhowdah\w*\b/i', $sql)) {
            return [];
        }

        if (1 === preg_match('/\bLIMIT\b/i', $sql) || 1 === preg_match('/\bWHERE\b[^;]*\bid\s*=/i', $sql)) {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, 'A statement against a howdah table needs a LIMIT or a primary-key equality.')];
    }
}
