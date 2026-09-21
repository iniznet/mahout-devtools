<?php
declare(strict_types=1);

namespace Fixture\Violations\BoundedStatementPackageTable;

// A package table: the migration ledger is "{prefix}mahout_migrations".
$applied = $wpdb->get_col('SELECT migration FROM wptests_mahout_migrations'); // EXPECT: mahout.arch.boundedHowdahStatement
