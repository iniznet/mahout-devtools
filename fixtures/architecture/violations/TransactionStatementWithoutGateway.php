<?php
declare(strict_types=1);

namespace Fixture\Violations\TransactionCommit;

$wpdb->query('COMMIT'); // EXPECT: mahout.arch.transactionOnlyInGateway
