<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * Diagnostics is the only class permitted to call error_log(), so the
 * destination and the threshold have exactly one owner.
 *
 * @implements ArchitectureRule<Node>
 */
final class ErrorLogOnlyInDiagnosticsRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.errorLogOnlyInDiagnostics';

    private const DIAGNOSTICS = 'Iniznet\\Mahout\\Kernel\\Diagnostics';

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

        if ('error_log' !== $node->name->toLowerString()) {
            return [];
        }

        if ($scope->isInClass() && 0 === strcmp($scope->getClassReflection()->getName(), self::DIAGNOSTICS)) {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, 'error_log() is confined to Diagnostics; record through the logger instead.')];
    }
}
