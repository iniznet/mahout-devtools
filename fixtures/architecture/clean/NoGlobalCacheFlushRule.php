<?php
declare(strict_types=1);

namespace Fixture\Clean\GlobalFlush;

// EXPECT-NONE: a scoped delete, not a global flush.
wp_cache_delete('series', 'howdah');
