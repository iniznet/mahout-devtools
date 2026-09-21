<?php
declare(strict_types=1);

namespace Fixture\Violations\QuickEdit;

add_action('bulk_edit_posts', 'howdah_bulk_edit'); // EXPECT: mahout.arch.noQuickEditHandler, mahout.arch.noRawHookName
