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
 * ARC-18: neither bulk edit nor inline save can carry the save lifecycle, and
 * neither writes a Table field.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoQuickEditHandlerRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noQuickEditHandler';

    /** @var list<string> */
    private const HOOKS = ['bulk_edit_posts', 'wp_ajax_inline-save', 'wp_ajax_inline_save', 'inline-save'];

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

        if (!in_array($node->name->toLowerString(), ['add_action', 'add_filter'], true)) {
            return [];
        }

        if (!isset($node->args[0]) || !$node->args[0] instanceof Arg || !$node->args[0]->value instanceof String_) {
            return [];
        }

        $hook = $node->args[0]->value->value;
        if (!in_array($hook, self::HOOKS, true)) {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, sprintf("The '%s' handler is banned; the field panel is the only write path.", $hook))];
    }
}
