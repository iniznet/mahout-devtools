<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\InlineHTML;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * An SEO plugin owns indexability and canonicalisation. The theme emits no
 * canonical, description or robots tag and does not filter wp_robots.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoSeoMetaRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noSeoMeta';

    private const META_PATTERN = '/name=["\']?(canonical|description|robots)/i';

    public function getNodeType(): string
    {
        return Node::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $function = $node->name->toLowerString();
            if ('rel_canonical' === $function) {
                return [Violation::at($node, self::IDENTIFIER, 'rel_canonical() is banned; an SEO plugin owns the canonical URL.')];
            }

            if (in_array($function, ['add_action', 'add_filter'], true)
                && isset($node->args[0]) && $node->args[0] instanceof Arg && $node->args[0]->value instanceof String_
                && in_array($node->args[0]->value->value, ['wp_robots', 'robots_txt'], true)) {
                return [Violation::at($node, self::IDENTIFIER, 'The theme must not filter robots output; an SEO plugin owns it.')];
            }
        }

        if ($node instanceof String_ && 1 === preg_match(self::META_PATTERN, $node->value)) {
            return [Violation::at($node, self::IDENTIFIER, 'The theme emits no canonical, description or robots meta tag.')];
        }

        if ($node instanceof InlineHTML && 1 === preg_match(self::META_PATTERN, $node->value)) {
            return [Violation::at($node, self::IDENTIFIER, 'The theme emits no canonical, description or robots meta tag.')];
        }

        return [];
    }
}
