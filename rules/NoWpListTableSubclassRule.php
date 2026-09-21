<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * ARC-18: the core list screen is the list screen. A WP_List_Table subclass
 * cannot carry the save lifecycle.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoWpListTableSubclassRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noWpListTableSubclass';

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_ || !$node->extends instanceof Name) {
            return [];
        }

        if ('WP_List_Table' !== $scope->resolveName($node->extends)) {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, 'A WP_List_Table subclass is banned; the core list screen is the list screen.')];
    }
}
