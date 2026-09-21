<?php
declare(strict_types=1);

namespace Fixture\Clean\TraitMember;

// EXPECT-NONE: the trait declares every member it calls.
trait Renders
{
    public function render(): string
    {
        return $this->helper();
    }

    private function helper(): string
    {
        return 'ok';
    }
}

final class Holder
{
    use Renders;
}
