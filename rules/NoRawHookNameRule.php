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
        if (!$argument instanceof String_) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf("Raw hook name '%s' at %s() is banned; declare it on a Hooks class.", $argument->value, $function),
        )];
    }
}
