<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * wp_cache_flush() is every plugin's cache, not ours.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoGlobalCacheFlushRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noGlobalCacheFlush';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name || 'wp_cache_flush' !== $node->name->toLowerString()) {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, 'wp_cache_flush() is banned; the group flush is the gated path.')];
    }
}
