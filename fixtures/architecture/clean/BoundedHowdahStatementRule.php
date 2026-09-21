<?php
declare(strict_types=1);

namespace Fixture\Clean\BoundedStatement;

// EXPECT-NONE: a primary-key equality bounds the statement.
$row = $wpdb->get_row('SELECT * FROM howdah_series WHERE id = 1');
