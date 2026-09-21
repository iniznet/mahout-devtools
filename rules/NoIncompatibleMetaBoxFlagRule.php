<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * A metabox is either in the block editor or it is a legacy panel; the
 * compatibility flags are banned in both directions.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoIncompatibleMetaBoxFlagRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noIncompatibleMetaBoxFlag';

    /** @var list<string> */
    private const FLAGS = ['__back_compat_meta_box', '__block_editor_compatible_meta_box'];

    public function getNodeType(): string
    {
        return ArrayItem::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ArrayItem || !$node->key instanceof String_ || !in_array($node->key->value, self::FLAGS, true)) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf("The metabox compatibility flag '%s' is banned.", $node->key->value),
        )];
    }
}
