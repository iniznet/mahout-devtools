<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * A Component is presentation only. Data arrives as typed props; it never
 * reaches for a repository, which would make it untestable without WordPress.
 *
 * @implements ArchitectureRule<Node>
 */
final class ComponentMustNotCallRepositoryRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.componentMustNotCallRepository';

    public function getNodeType(): string
    {
        return Node::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$scope->isInClass()) {
            return [];
        }

        $class = $scope->getClassReflection();
        if (!$this->isComponent($class->getName())) {
            return [];
        }

        $target = null;
        if (($node instanceof StaticCall || $node instanceof New_) && $node->class instanceof Name) {
            $target = $scope->resolveName($node->class);
        } elseif ($node instanceof MethodCall) {
            foreach ($scope->getType($node->var)->getObjectClassNames() as $name) {
                if ($this->isRepository($name)) {
                    $target = $name;
                    break;
                }
            }
        }

        if ($target === null || !$this->isRepository($target)) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('Component %s must not call repository %s; inject it instead.', $class->getName(), $target),
        )];
    }

    private function isComponent(string $name): bool
    {
        return str_contains($name, '\\Components\\') || str_ends_with($name, 'Component');
    }

    private function isRepository(string $name): bool
    {
        return str_contains($name, '\\Repositories\\') || str_ends_with($name, 'Repository');
    }
}
