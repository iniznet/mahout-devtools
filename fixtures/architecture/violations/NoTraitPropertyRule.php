<?php
declare(strict_types=1);

namespace Fixture\Violations\TraitProperty;

trait HasState
{
    private int $count = 0; // EXPECT: mahout.arch.noTraitProperty
}

final class Holder
{
    use HasState;
}
