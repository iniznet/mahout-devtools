<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * Core already sends the frame and content-type options headers. A second site
 * is a second answer, and the wrong one wins non-deterministically.
 *
 * @implements ArchitectureRule<Node>
 */
final class NoHandSetSecurityHeaderRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.noHandSetSecurityHeader';

    /** @var list<string> */
    private const HEADERS = ['X-Frame-Options', 'X-Content-Type-Options', 'Referrer-Policy'];

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name || 'header' !== $node->name->toLowerString()) {
            return [];
        }

        if (!isset($node->args[0]) || !$node->args[0] instanceof Arg || !$node->args[0]->value instanceof String_) {
            return [];
        }

        $value = $node->args[0]->value->value;
        foreach (self::HEADERS as $header) {
            if (0 === stripos($value, $header.':')) {
                return [Violation::at($node, self::IDENTIFIER, sprintf('The %s header is set by core; setting it here is banned.', $header))];
            }
        }

        return [];
    }
}
