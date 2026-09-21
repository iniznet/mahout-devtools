<?php
declare(strict_types=1);

namespace Fixture\Clean\ListTable;

// EXPECT-NONE: the core list screen is the list screen.
final class SeriesTable
{
    public function title(): string
    {
        return 'Series';
    }
}
