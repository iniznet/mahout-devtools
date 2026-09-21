<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * The theme sets no cookie. A cookie is presentation state the visitor did not
 * ask for and a cache-correctness hazard.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoCookieRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noCookie';

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

        $function = $node->name->toLowerString();
        if (!in_array($function, ['setcookie', 'setrawcookie'], true)) {
            return [];
        }

        return [Violation::at($node, self::IDENTIFIER, sprintf('%s() is banned; the theme sets no cookie.', $function))];
    }
}
