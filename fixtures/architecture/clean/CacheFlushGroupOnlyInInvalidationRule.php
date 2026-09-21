<?php
declare(strict_types=1);

namespace Fixture\Clean\CacheFlushGroup;

// EXPECT-NONE: the gated invalidation service owns the group flush.
final class CacheInvalidation
{
    public function flush(): void
    {
        if (wp_cache_supports('flush_group')) {
            wp_cache_flush_group('howdah');
        }
    }
}
