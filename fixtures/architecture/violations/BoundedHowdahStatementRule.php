<?php
declare(strict_types=1);

namespace Fixture\Violations\BoundedStatement;

$rows = $wpdb->get_results('SELECT * FROM howdah_series'); // EXPECT: mahout.arch.boundedHowdahStatement
