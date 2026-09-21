<?php
declare(strict_types=1);

namespace Fixture\Clean\BoundedStatement;

// EXPECT-NONE: a primary-key equality bounds the statement.
$row = $wpdb->get_row('SELECT * FROM howdah_series WHERE id = 1');

// EXPECT-NONE: the prefixed name is bounded by its primary key, and the
// interpolated prefix form is read as a statement rather than skipped.
$value = $wpdb->get_row('SELECT * FROM wptests_howdah_values WHERE id = 1');

// EXPECT-NONE: a package table with a LIMIT.
$ledger = $wpdb->get_row("SELECT id FROM {$wpdb->prefix}mahout_migrations ORDER BY id DESC LIMIT 1");
