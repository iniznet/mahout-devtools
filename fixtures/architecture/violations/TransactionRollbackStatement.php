<?php
declare(strict_types=1);

namespace Fixture\Violations\TransactionRollback;

// Leading whitespace and a trailing semicolon do not move the keyword out of
// statement position.
$wpdb->query('  ROLLBACK;'); // EXPECT: mahout.arch.transactionOnlyInGateway
