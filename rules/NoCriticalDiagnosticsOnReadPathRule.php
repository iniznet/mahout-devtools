<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * A read path records at critical only for an exceptional condition. A log per
 * request is a cost that grows linearly with traffic.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoCriticalDiagnosticsOnReadPathRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noCriticalDiagnosticsOnReadPath';

    /** @var list<string> */
    private const LEVELS = ['critical', 'error', 'emergency', 'alert'];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier || 'log' !== $node->name->toString()) {
            return [];
        }

        $isDiagnostics = false;
        foreach ($scope->getType($node->var)->getObjectClassNames() as $className) {
            if (str_ends_with($className, 'Diagnostics')) {
                $isDiagnostics = true;
            }
        }

        if (!$isDiagnostics || !isset($node->args[0]) || !$node->args[0] instanceof Arg) {
            return [];
        }

        $level = $node->args[0]->value;
        if (!$level instanceof ClassConstFetch || !$level->name instanceof Identifier || !in_array(strtolower($level->name->toString()), self::LEVELS, true)) {
            return [];
        }

        if (!$scope->isInClass()) {
            return [];
        }

        $class = $scope->getClassReflection();
        if (!$this->isReadPath($class->getName())) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('A read path (%s) must not record at critical unless the condition is exceptional.', $class->getName()),
        )];
    }

    private function isReadPath(string $className): bool
    {
        if (1 === preg_match('/(Repository|Surface|Component|Mapper)$/', $className)) {
            return true;
        }

        return str_contains($className, '\\Features\\');
    }
}
