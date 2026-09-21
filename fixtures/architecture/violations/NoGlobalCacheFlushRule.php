<?php
declare(strict_types=1);

namespace Fixture\Violations\GlobalFlush;

wp_cache_flush(); // EXPECT: mahout.arch.noGlobalCacheFlush
