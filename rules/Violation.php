<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PhpParser\Node;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * One architecture error with a stable identifier and the node's line.
 *
 * @internal
 */
final class Violation
{
    public static function shortName(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return false === $position ? $fqcn : substr($fqcn, $position + 1);
    }

    public static function at(Node $node, string $identifier, string $message): RuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier($identifier)
            ->line($node->getStartLine())
            ->build();
    }
}
