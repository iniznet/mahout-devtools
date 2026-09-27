<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * Hook names are declared once, on a Hooks class. A raw string at an emit site
 * is invisible to the generated reference and drifts silently.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoRawHookNameRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noRawHookName';

    /** @var list<string> */
    private const FUNCTIONS = [
        'add_action',
        'add_filter',
        'do_action',
        'apply_filters',
        'do_action_ref_array',
        'apply_filters_ref_array',
    ];

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

        if (!isset($node->args[0]) || !$node->args[0] instanceof Arg) {
            return [];
        }

        $argument = $node->args[0]->value;

        if (!$argument instanceof String_ && !$argument instanceof Concat) {
            return [];
        }

        $head = self::rawHead($argument);

        if (null === $head) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            $argument instanceof Concat
                ? sprintf("Raw hook prefix '%s' composes a name at %s(); declare the prefix on a Hooks class so the reference can list it.", $head, $function)
                : sprintf("Raw hook name '%s' at %s() is banned; declare it on a Hooks class.", $head, $function),
        )];
    }

    /**
     * The raw string at the head of a hook name, through a concatenation.
     *
     * The left operand is where a composed prefix sits, and `'load-' . $hook` is the
     * same bypass as `'load-'` — it is the case this rule exists for and did not see,
     * found while auditing the family's hook documentation rather than by a failure.
     *
     * A prefix that arrives from a declared constant is not a bypass: the fragment is
     * still in the inventory the reference is generated from, so only a literal head
     * is reported.
     */
    private static function rawHead(Expr $expression): ?string
    {
        if ($expression instanceof String_) {
            return $expression->value;
        }

        if ($expression instanceof Concat) {
            return self::rawHead($expression->left);
        }

        return null;
    }
}
