<?php
declare(strict_types=1);

namespace Fixture\Violations\RawHook;

// A raw hook name at an emit site bypasses the Hooks constant inventory.
do_action('mahout/kernel/before_boot'); // EXPECT: mahout.arch.noRawHookName
