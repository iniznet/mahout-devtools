<?php
declare(strict_types=1);

namespace Iniznet\Kumki\Features;

use Iniznet\Kumki\Internal\RowCache;

// EXPECT-NONE: same host, same package, so Internal/ is reachable.
final class VenueRepository
{
    public function cache(): RowCache
    {
        return new RowCache();
    }
}
