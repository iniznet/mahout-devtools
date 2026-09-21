<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Rules;

use PHPStan\Rules\Rule;

/**
 * Marker for every architecture rule in this package.
 *
 * A rule exposes its stable identifier as a class constant so the fixture
 * harness can assert on it without reflection.
 *
 * @template TNodeType of \PhpParser\Node
 * @extends Rule<TNodeType>
 */
interface ArchitectureRule extends Rule
{
    public const IDENTIFIER = 'mahout.arch.unknown';
}
