<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * Internal/ is private to its package. Referencing another package's Internal/
 * class binds the consumer to an implementation detail that may change in a
 * patch release.
 *
 * The package is the vendor segment after `Iniznet\`, so a package of the family
 * (`Iniznet\Mahout\Db\…` is `Db`) and a host installation (`Iniznet\Howdah\…` is
 * `Howdah`, `Iniznet\Kumki\…` is `Kumki`) are the same kind of thing to this rule.
 *
 * @implements ArchitectureRule<Node>
 */
final class InternalNotCrossPackageRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.internalNotCrossPackage';

    public function getNodeType(): string
    {
        return Name::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Name) {
            return [];
        }

        $resolved = $scope->resolveName($node);
        if (!str_contains($resolved, '\\Internal\\')) {
            return [];
        }

        $target = $this->packageOf($resolved);
        $current = $this->packageOf(($scope->getNamespace() ?? '').'\\probe');
        if ($target === null || $current === null || $target === $current) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('The Internal class %s belongs to another package (%s); use its Contracts instead.', $resolved, $target),
        )];
    }

    private function packageOf(string $fqcn): ?string
    {
        // One pattern for both kinds of package: `Iniznet\Mahout\Db\…` is the package
        // `Db`, and a host installation — `Iniznet\Howdah\…`, `Iniznet\Kumki\…` — is the
        // package named after it. Naming only one host would leave every other
        // installation outside the rule's reach, and the reach is the point.
        if (1 === preg_match('/^Iniznet\\\\([^\\\\]+)\\\\([^\\\\]+)\\\\/', $fqcn, $matches)) {
            return 'Mahout' === $matches[1] ? $matches[2] : $matches[1];
        }

        return null;
    }
}
