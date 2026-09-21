<?php
declare(strict_types=1);

namespace Fixture\Violations\Transaction;

$statement = 'START TRANSACTION'; // EXPECT: mahout.arch.transactionOnlyInGateway
