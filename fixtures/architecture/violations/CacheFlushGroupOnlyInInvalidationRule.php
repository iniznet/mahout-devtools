<?php
declare(strict_types=1);

namespace Fixture\Violations\CacheFlushGroup;

wp_cache_flush_group('howdah'); // EXPECT: mahout.arch.cacheFlushGroupOnlyInInvalidation
