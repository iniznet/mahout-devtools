<?php
declare(strict_types=1);

namespace Fixture\Violations\LayerDirection\Components {
    final class SeriesCard
    {
    }
}

namespace Fixture\Violations\LayerDirection\Repositories {
    use Fixture\Violations\LayerDirection\Components\SeriesCard;

    final class SeriesRepository
    {
        public function card(): SeriesCard // EXPECT: mahout.arch.layerDirection
        {
            return new SeriesCard();
        }
    }
}
