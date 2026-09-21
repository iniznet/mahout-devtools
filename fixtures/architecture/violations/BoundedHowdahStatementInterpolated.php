<?php
declare(strict_types=1);

namespace Fixture\Violations\BoundedStatementInterpolated;

// The $wpdb->prefix form as it is written in PHP. A rule that reads only scalar
// string literals never sees it.
$readthrough = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}howdah_readthrough"); // EXPECT: mahout.arch.boundedHowdahStatement
