<?php
declare(strict_types=1);

namespace Fixture\Clean\RawHook;

const BEFORE_BOOT = 'mahout/kernel/before_boot';

// EXPECT-NONE: the name arrives from a declared constant.
do_action(BEFORE_BOOT);
