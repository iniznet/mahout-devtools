<?php
declare(strict_types=1);

namespace Fixture\Clean\RawHookComposedFromConst;

const PAGE_LOAD_PREFIX = 'load-';
const PAGE_HOOK = 'toplevel_page_howdah-status';

// EXPECT-NONE: composed, but from a declared name. The prefix is in the inventory the
// reference is generated from, so the hook is documented even though its suffix is only
// known at runtime — the shape howdah's Hooks::PAGE_LOAD_PREFIX now uses.
add_action(PAGE_LOAD_PREFIX . PAGE_HOOK, '__return_true');
