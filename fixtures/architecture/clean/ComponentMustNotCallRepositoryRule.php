<?php
declare(strict_types=1);

namespace Fixture\Clean\ComponentRepo {
    final class SeriesData
    {
        public function __construct(public string $title)
        {
        }
    }
}

namespace Fixture\Clean\ComponentRepo\Components {
    use Fixture\Clean\ComponentRepo\SeriesData;

    // EXPECT-NONE: the component receives typed props.
    final class SeriesCard
    {
        public function __construct(private readonly SeriesData $data)
        {
        }

        public function render(): string
        {
            return $this->data->title;
        }
    }
}
