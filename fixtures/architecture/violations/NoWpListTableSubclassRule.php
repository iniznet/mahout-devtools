<?php
declare(strict_types=1);

namespace Fixture\Violations\ListTable;

final class SeriesTable extends \WP_List_Table // EXPECT: mahout.arch.noWpListTableSubclass
{
}
