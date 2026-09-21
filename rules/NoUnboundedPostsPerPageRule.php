<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * posts_per_page => -1 is an unbounded query; page the result instead.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoUnboundedPostsPerPageRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noUnboundedPostsPerPage';

    /** @var list<string> */
    private const KEYS = ['posts_per_page', 'numberposts'];

    public function getNodeType(): string
    {
        return Array_::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Array_) {
            return [];
        }

        $errors = [];
        foreach ($node->items as $item) {
            if (!$item->key instanceof String_ || !in_array($item->key->value, self::KEYS, true)) {
                continue;
            }

            if ($this->isMinusOne($item->value)) {
                $errors[] = Violation::at($node, self::IDENTIFIER, sprintf('%s => -1 is unbounded; page the result.', $item->key->value));
            }
        }

        return $errors;
    }

    private function isMinusOne(Node $value): bool
    {
        if ($value instanceof UnaryMinus) {
            return $value->expr instanceof Int_ && 1 === $value->expr->value;
        }

        if ($value instanceof Int_) {
            return -1 === $value->value;
        }

        return $value instanceof String_ && '-1' === $value->value;
    }
}
