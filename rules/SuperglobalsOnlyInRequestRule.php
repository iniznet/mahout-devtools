<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleError;

/**
 * Request input has one boundary. A superglobal read outside it bypasses
 * unslashing, validation and the request object a test can fake.
 *
 * @implements ArchitectureRule<Node>
 */
final class SuperglobalsOnlyInRequestRule implements ArchitectureRule
{
    public const IDENTIFIER = 'mahout.arch.superglobalsOnlyInRequest';

    /** @var list<string> */
    private const SUPERGLOBALS = ['_GET', '_POST', '_REQUEST', '_SERVER', '_FILES', '_COOKIE'];

    private const REQUEST = 'Iniznet\\Howdah\\Support\\Request';

    public function getNodeType(): string
    {
        return Variable::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Variable || !is_string($node->name) || !in_array($node->name, self::SUPERGLOBALS, true)) {
            return [];
        }

        if ($scope->isInClass() && 0 === strcmp($scope->getClassReflection()->getName(), self::REQUEST)) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('$%s is read only inside %s; consume the request adapter.', $node->name, self::REQUEST),
        )];
    }
}
