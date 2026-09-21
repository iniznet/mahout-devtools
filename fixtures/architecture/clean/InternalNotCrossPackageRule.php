<?php
declare(strict_types=1);

namespace Iniznet\Mahout\Content\Fixture;

use Iniznet\Mahout\Content\Internal\LocalThing;

// EXPECT-NONE: same package.
final class Consumer
{
    public function render(LocalThing $thing): void
    {
    }
}
