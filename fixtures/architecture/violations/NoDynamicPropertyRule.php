<?php
declare(strict_types=1);

namespace Fixture\Violations\DynamicProperty;

final class Thing
{
}

function fill(Thing $thing): void
{
    $thing->undeclared = 1; // EXPECT: mahout.arch.noDynamicProperty
}
