<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * Records, per class, whether it declares Cacheability::Shared and where it
 * creates a nonce. The paired rule joins the two across one analysis run.
 *
 * @implements Collector<Node, mixed>
 */
final class SharedSurfaceCollector implements Collector
{
    public const SHARED = 'shared';
    public const NONCE = 'nonce';

    /** @var list<string> */
    private const NONCE_FUNCTIONS = ['wp_nonce_field', 'wp_create_nonce', 'wp_verify_nonce'];

    public function getNodeType(): string
    {
        return Node::class;
    }

    /** @return array{kind: string, class: string, line: int}|null */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if ($node instanceof ClassConstFetch && $node->name instanceof Identifier && 'Shared' === $node->name->toString()) {
            if ($node->class instanceof Name && str_ends_with($node->class->toString(), 'Cacheability')) {
                return ['kind' => self::SHARED, 'class' => $this->className($scope), 'line' => $node->getStartLine()];
            }
        }

        if ($node instanceof FuncCall && $node->name instanceof Name && in_array($node->name->toLowerString(), self::NONCE_FUNCTIONS, true)) {
            return ['kind' => self::NONCE, 'class' => $this->className($scope), 'line' => $node->getStartLine()];
        }

        return null;
    }

    private function className(Scope $scope): string
    {
        if ($scope->isInClass()) {
            return $scope->getClassReflection()->getName();
        }

        return $scope->getNamespace() ?? '';
    }
}
