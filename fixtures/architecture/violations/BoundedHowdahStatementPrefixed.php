<?php
declare(strict_types=1);

namespace Fixture\Violations\BoundedStatementPrefixed;

// The prefixed form is what a table is called at runtime: the declaration is
// "{$wpdb->prefix}howdah_<entity>", and the test suite's prefix is "wptests_".
// "\bhowdah" cannot match after an underscore, so a bare-name fixture proves
// nothing about a real table name.
$rows = $wpdb->get_results('SELECT * FROM wptests_howdah_values'); // EXPECT: mahout.arch.boundedHowdahStatement
