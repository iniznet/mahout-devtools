<?php
declare(strict_types=1);

namespace Fixture\Violations\TraitMember;

trait Renders
{
    public function render(): void
    {
        $this->missing(); // EXPECT: mahout.arch.traitNoUndeclaredMember
    }
}

final class Holder
{
    use Renders;
}
