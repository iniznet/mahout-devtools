<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * A field is registered with a storage target and is read through the field
 * layer, never through a raw meta call that bypasses sanitisation and caching.
 *
 * @implements ArchitectureRule<Node>
 */
final class FieldLayerOnlyMetaAccessRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.fieldLayerOnlyMetaAccess';

    /** @var list<string> */
    private const FUNCTIONS = [
        'get_post_meta',
        'get_user_meta',
        'update_post_meta',
        'update_user_meta',
        'delete_post_meta',
        'delete_user_meta',
        'add_post_meta',
        'add_user_meta',
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

        $namespace = $scope->getNamespace();
        if (is_string($namespace) && str_starts_with($namespace, 'Iniznet\\Mahout\\Fields')) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('%s() bypasses the field layer; read the value through a field reader.', $function),
        )];
    }
}
