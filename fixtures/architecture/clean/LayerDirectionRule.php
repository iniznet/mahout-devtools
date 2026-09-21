<?php
declare(strict_types=1);

namespace Fixture\Clean\LayerDirection\Data {
    final class SeriesData
    {
    }
}

namespace Fixture\Clean\LayerDirection\Repositories {
    use Fixture\Clean\LayerDirection\Data\SeriesData;

    // EXPECT-NONE: the repository reaches only into data.
    final class SeriesRepository
    {
        public function data(): SeriesData
        {
            return new SeriesData();
        }
    }
}
