<?php
declare(strict_types=1);

namespace Fixture\Clean\TraitProperty;

// EXPECT-NONE: the trait is stateless.
trait HasState
{
    private function count(): int
    {
        return 0;
    }
}

final class Holder
{
    use HasState;
}
