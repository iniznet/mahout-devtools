<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * A statement against a howdah table carries a LIMIT or a primary-key
 * equality. An unbounded statement is a table scan behind a page render.
 *
 * The table name is matched where a statement puts one, not where the letters
 * happen to fall in the literal. Two properties of a real name are load-bearing
 * and both were previously missed:
 *
 * 1. The prefix. A table is declared as `{$wpdb->prefix}howdah_<entity>` or
 *    `{$wpdb->prefix}mahout_<name>`, so the runtime name is
 *    `wp_howdah_values` or `wptests_mahout_migrations`. A `\b` before
 *    "howdah" never matches there, because the underscore the prefix ends with
 *    is a word character. The boundary is a lookbehind for something that is not
 *    part of the name instead.
 * 2. The statement is often an interpolated or a concatenated string, which is
 *    how `$wpdb->prefix` reaches it. A rule that reads only a plain string
 *    literal is silent on the canonical form.
 *
 * @implements ArchitectureRule<Node>
 */
final class BoundedHowdahStatementRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.boundedHowdahStatement';

    /** @var list<string> */
    private const METHODS = ['query', 'get_results', 'get_row', 'get_col', 'get_var', 'get_blog_results'];

    /**
     * A table this project owns, in a statement's table position.
     *
     * `howdah_<entity>` is a theme-owned custom table (`wp_howdah_readthrough`);
     * `mahout_<name>` is a package table (`wp_mahout_migrations`,
     * `wp_mahout_field_values`, `wp_mahout_field_items`).
     */
    private const TABLE_PATTERN = '/\b(?:FROM|JOIN|INTO|UPDATE|TABLE)\s+[\w`.' . self::UNKNOWN . ' ]*?(?<![A-Za-z0-9])(?:howdah|mahout)_[A-Za-z0-9_]*/i';

    private const BOUNDED_PATTERN = '/\bLIMIT\b/i';

    private const PRIMARY_KEY_PATTERN = '/\bWHERE\b[^;]*\bid\s*=/i';

    /** A piece of the statement the analyzer cannot read, such as a variable. */
    private const UNKNOWN = "\x00";

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

        if (!isset($node->args[0]) || !$node->args[0] instanceof Arg) {
            return [];
        }

        $statement = $this->statement($node->args[0]->value);
        if (null === $statement) {
            return [];
        }

        if (1 !== preg_match(self::TABLE_PATTERN, $statement)) {
            return [];
        }

        if (1 === preg_match(self::BOUNDED_PATTERN, $statement) || 1 === preg_match(self::PRIMARY_KEY_PATTERN, $statement)) {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, 'A statement against a howdah table needs a LIMIT or a primary-key equality.')];
    }

    /**
     * The text of the statement as far as the analyzer can read it. A part that
     * is not text — an interpolated expression, a concatenated variable — is
     * replaced by a marker, so it can never be mistaken for SQL the rule can
     * read, and a table name that only exists in a variable is invisible here.
     */
    private function statement(Expr $expression): ?string
    {
        if ($expression instanceof String_) {
            return $expression->value;
        }

        if ($expression instanceof InterpolatedString) {
            $text = '';
            foreach ($expression->parts as $part) {
                $text .= $part instanceof InterpolatedStringPart ? $part->value : self::UNKNOWN;
            }

            return $text;
        }

        if ($expression instanceof Concat) {
            $left = $this->statement($expression->left);
            $right = $this->statement($expression->right);
            if (null === $left && null === $right) {
                return null;
            }

            return ($left ?? self::UNKNOWN) . ($right ?? self::UNKNOWN);
        }

        return null;
    }
}
