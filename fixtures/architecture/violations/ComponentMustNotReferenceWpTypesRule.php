<?php
declare(strict_types=1);

namespace Fixture\Violations\ComponentWp\Components;

final class SeriesCard
{
    public function render(\WP_Post $post): void // EXPECT: mahout.arch.componentMustNotReferenceWpTypes
    {
    }
}
