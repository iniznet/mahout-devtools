<?php
declare(strict_types=1);

namespace Fixture\Violations\Transaction;

$wpdb->query('START TRANSACTION'); // EXPECT: mahout.arch.transactionOnlyInGateway
