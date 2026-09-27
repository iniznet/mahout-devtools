<?php
declare(strict_types=1);

namespace Fixture\Clean\RawHook;

const BEFORE_BOOT = 'mahout/kernel/before_boot';

// EXPECT-NONE: the name arrives from a declared constant.
do_action(BEFORE_BOOT);

const PAGE_LOAD_PREFIX = 'load-';

// Composed, but from a declared name: the prefix is in the inventory, so the reference
// documents the hook even though its suffix is only known at runtime.
add_action(PAGE_LOAD_PREFIX . 'toplevel_page_howdah-status', '__return_true');
