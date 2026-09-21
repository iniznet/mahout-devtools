<?php
declare(strict_types=1);

namespace Fixture\Violations\ComponentRepo {
    final class SeriesRepository
    {
        public function find(): void
        {
        }
    }
}

namespace Fixture\Violations\ComponentRepo\Components {
    use Fixture\Violations\ComponentRepo\SeriesRepository;

    final class SeriesCard
    {
        public function render(SeriesRepository $repository): void
        {
            $repository->find(); // EXPECT: mahout.arch.componentMustNotCallRepository
        }
    }
}
