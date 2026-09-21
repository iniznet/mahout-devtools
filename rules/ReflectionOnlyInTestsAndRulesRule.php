<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * Reflection hides the fragment key and the dependency. It is permitted only
 * in tests and in this package's own rules.
 *
 * @implements ArchitectureRule<Node>
 */
final class ReflectionOnlyInTestsAndRulesRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.reflectionOnlyInTestsAndRules';

    /** @var list<string> */
    private const CLASSES = [
        'ReflectionClass', 'ReflectionObject', 'ReflectionMethod', 'ReflectionProperty',
        'ReflectionFunction', 'ReflectionParameter', 'ReflectionNamedType',
        'ReflectionUnionType', 'ReflectionIntersectionType', 'ReflectionClassConstant',
    ];

    public function getNodeType(): string
    {
        return New_::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_ || !$node->class instanceof Name) {
            return [];
        }

        $className = $scope->resolveName($node->class);
        if (!in_array($className, self::CLASSES, true)) {
            return [];
        }

        $file = str_replace('\\', '/', $scope->getFile());
        if (str_contains($file, '/tests/') || str_contains($file, '/rules/')) {
            return [];
        }

        $namespace = $scope->getNamespace();
        if (is_string($namespace) && str_starts_with($namespace, 'Iniznet\\Mahout\\Devtools\\')) {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, sprintf('%s is banned outside tests and the devtools rules.', $className))];
    }
}
