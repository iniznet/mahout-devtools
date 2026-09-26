<?php
declare(strict_types=1);

namespace Iniznet\Kumki\Features;

use Iniznet\Howdah\Internal\Chrome;

// EXPECT: mahout.arch.internalNotCrossPackage
// A host is a package to this rule, so a second installation reaching into the
// first one's Internal/ is caught the same way a package reaching across is.
final class VenueBlock
{
    public function chrome(): Chrome
    {
        return new Chrome();
    }
}
