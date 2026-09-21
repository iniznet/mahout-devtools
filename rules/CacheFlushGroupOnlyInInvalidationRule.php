<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * wp_cache_flush_group() must be gated on wp_cache_supports( 'flush_group' ),
 * and that gate lives in one class.
 *
 * @implements ArchitectureRule<Node>
 */
final class CacheFlushGroupOnlyInInvalidationRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.cacheFlushGroupOnlyInInvalidation';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name || 'wp_cache_flush_group' !== $node->name->toLowerString()) {
            return [];
        }

        if ($scope->isInClass() && Violation::shortName($scope->getClassReflection()->getName()) === 'CacheInvalidation') {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, 'wp_cache_flush_group() is confined to the cache-invalidation service.')];
    }
}
