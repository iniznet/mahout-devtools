<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * meta_query has no usable index and adds one join per clause, so it never
 * crosses a public boundary.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoMetaQueryInPublicApiRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noMetaQueryInPublicApi';

    public function getNodeType(): string
    {
        return FunctionLike::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod && !$node instanceof Function_) {
            return [];
        }

        if ($node instanceof ClassMethod && !$node->isPublic()) {
            return [];
        }

        $errors = [];
        foreach ($node->getParams() as $parameter) {
            if ($parameter->var instanceof Node\Expr\Variable && 'meta_query' === $parameter->var->name) {
                $errors[] = Violation::at($node, self::IDENTIFIER, 'meta_query is banned in a public API; declare a Table field instead.');
            }
        }

        return $errors;
    }
}
