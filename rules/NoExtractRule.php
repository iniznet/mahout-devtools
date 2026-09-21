<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * extract() introduces variables that no reader and no analyser can trace.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoExtractRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noExtract';

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

        if ('extract' !== $node->name->toLowerString()) {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, 'extract() is banned; read the value you need by name.')];
    }
}
