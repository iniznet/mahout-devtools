<?php
declare(strict_types=1);

namespace Fixture\Clean\ComponentWp\Components;

final class SeriesData
{
    public function __construct(public string $title)
    {
    }
}

// EXPECT-NONE: the component names no WordPress type.
final class SeriesCard
{
    public function render(SeriesData $data): string
    {
        return $data->title;
    }
}
