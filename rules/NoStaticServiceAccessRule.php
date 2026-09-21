<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * Static access to a collaborator hides the dependency. The test is not whether
 * it is static but whether it resolves a service. Value constructors are fine;
 * the composition root is the one exemption.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoStaticServiceAccessRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noStaticServiceAccess';

    /** @var list<string> */
    private const COMPOSITION_ROOT = [
        'Iniznet\\Howdah\\Bootstrap',
        'Iniznet\\Howdah\\Support\\Request',
        'Iniznet\\Howdah\\Render\\Surfaces',
    ];

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof StaticCall || !$node->class instanceof Name) {
            return [];
        }

        $className = $scope->resolveName($node->class);
        if (in_array($className, self::COMPOSITION_ROOT, true)) {
            return [];
        }

        if (!$this->isService($className)) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('Static access to service %s is banned; inject it through the constructor.', $className),
        )];
    }

    private function isService(string $className): bool
    {
        if (str_contains($className, '\\Repositories\\') || str_contains($className, '\\Registry\\') || str_contains($className, '\\Container\\')) {
            return true;
        }

        foreach (['Repository', 'Registry', 'Container', 'FieldQuery', 'Fields'] as $suffix) {
            if (str_ends_with($className, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
