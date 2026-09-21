<?php
declare(strict_types=1);

namespace Fixture\Clean\DynamicProperty;

final class Thing
{
    public int $declared = 0;
}

// EXPECT-NONE: the property is declared.
function fill(Thing $thing): void
{
    $thing->declared = 1;
}
