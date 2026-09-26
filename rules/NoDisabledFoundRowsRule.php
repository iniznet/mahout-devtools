<?php

/**
 * The count behind `no_found_rows` is the one permitted query that scales with
 * the data rather than with the page, so the hardened default may not be opened
 * from a call site. Pagination in this family answers "is there a next page" by
 * fetching one row past the page, which is bounded; core's `SQL_CALC_FOUND_ROWS`
 * is not.
 *
 * The rule bans the literal `false` in both shapes a call site can write — the
 * query-args array and the named argument of a constructor or method — because
 * either one re-enables the same query.
 */

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * @implements ArchitectureRule<Node>
 */
final class NoDisabledFoundRowsRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noDisabledFoundRows';

    private const string ARRAY_KEY = 'no_found_rows';

    private const string PROPERTY = 'noFoundRows';

    private const string MESSAGE = 'no_found_rows is disabled here, which re-runs the uncapped COUNT core issues as SQL_CALC_FOUND_ROWS; page with the one-past fetch or a capped count instead.';

    public function getNodeType(): string
    {
        return Node::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof Array_) {
            return $this->arrayFailures($node);
        }

        if ($node instanceof CallLike) {
            return $this->argumentFailures($node);
        }

        return [];
    }

    /**
     * @return list<RuleError>
     */
    private function arrayFailures(Array_ $node): array
    {
        foreach ($node->items as $item) {
            if (!$item->key instanceof String_) {
                continue;
            }

            if (self::ARRAY_KEY === $item->key->value && $this->isFalse($item->value)) {
                return [Violation::at($node, self::IDENTIFIER, self::MESSAGE)];
            }
        }

        return [];
    }

    /**
     * `CallLike` rather than `Call`: the spec is built with `new`, and a rule that
     * watched only function and method calls would miss the one shape the family
     * actually writes.
     *
     * @return list<RuleError>
     */
    private function argumentFailures(CallLike $node): array
    {
        foreach ($node->getArgs() as $argument) {
            if ($this->isNamedFalse($argument, self::PROPERTY)) {
                return [Violation::at($node, self::IDENTIFIER, self::MESSAGE)];
            }
        }

        return [];
    }

    private function isNamedFalse(Arg $argument, string $name): bool
    {
        return $argument->name instanceof Identifier
            && $name === $argument->name->name
            && $this->isFalse($argument->value);
    }

    private function isFalse(Node $value): bool
    {
        return $value instanceof ConstFetch && 0 === strcasecmp($value->name->toString(), 'false');
    }
}
