<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\RuleError;

/**
 * An exception is built by a static named constructor so its message lives in
 * one place and every throw site names the condition, not the class.
 *
 * @implements ArchitectureRule<Node>
 */
final class ExceptionNamedConstructorRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.exceptionNamedConstructor';

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

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

        $written = $node->class->toString();
        if (in_array($written, ['self', 'static', 'parent'], true)) {
            return [];
        }

        $className = $scope->resolveName($node->class);
        if (!$this->reflectionProvider->hasClass($className)) {
            return [];
        }

        $reflection = $this->reflectionProvider->getClass($className);
        if (!$reflection->implementsInterface(\Throwable::class) && !$reflection->isSubclassOf(\Exception::class) && !$reflection->isSubclassOf(\Error::class)) {
            return [];
        }

        if ($scope->isInClass()) {
            $current = $scope->getClassReflection();
            if ($current->getName() === $className) {
                return [];
            }
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('Exception %s must be built through a static named constructor.', $className),
        )];
    }
}
