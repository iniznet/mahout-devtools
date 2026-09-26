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

    /**
     * The request boundary is named by its position in a host, not by one host's
     * fully qualified name: `<Host>\Support\Request`. A fixed FQCN would be a rule
     * that quietly stops applying at the second installation, which is worse than no
     * rule, because the reader cannot tell which side of the boundary they are on.
     */
    private const REQUEST_SUFFIX = '\\Support\\Request';

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

        if ($scope->isInClass() && str_ends_with($scope->getClassReflection()->getName(), self::REQUEST_SUFFIX)) {
            return [];
        }

        return [Violation::at(
            $node,
            self::IDENTIFIER,
            sprintf('$%s is read only inside the boundary a host names <Host>\\Support\\Request; consume the request adapter.', $node->name),
        )];
    }
}
