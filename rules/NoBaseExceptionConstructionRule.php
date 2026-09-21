<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * The SPL base exceptions carry no context and no named constructor, so they
 * are never constructed directly.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoBaseExceptionConstructionRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noBaseExceptionConstruction';

    /** @var list<string> */
    private const BASE = [
        'Exception', 'Error', 'RuntimeException', 'LogicException', 'InvalidArgumentException',
        'DomainException', 'RangeException', 'OutOfRangeException', 'LengthException',
        'OverflowException', 'UnderflowException', 'UnexpectedValueException',
        'BadFunctionCallException', 'BadMethodCallException', 'TypeError', 'ArgumentCountError',
        'ValueError', 'ArithmeticError', 'DivisionByZeroError', 'ErrorException',
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

        if (!in_array($scope->resolveName($node->class), self::BASE, true)) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('new \\%s() is banned; use a package exception with a named constructor.', $scope->resolveName($node->class)),
        )];
    }
}
