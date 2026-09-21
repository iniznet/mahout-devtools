<?php
declare(strict_types=1);

namespace Fixture\Clean\Transaction;

// EXPECT-NONE: the gateway owns the transaction boundary.
final class DatabaseGateway
{
    public function begin(): string
    {
        return 'START TRANSACTION';
    }
}

// EXPECT-NONE: prose that names a rollback is prose. This is the message
// mahout-db's MigrationRollbackRefused carries.
final class RollbackRefusalMessage
{
    public static function text(): string
    {
        return 'The rollback is refused before any statement runs: a migration cannot be rolled back.';
    }
}

// EXPECT-NONE: a boundary between statements is not a transaction statement.
final class OrdinaryStatement
{
    public function read(): string
    {
        return 'SELECT id FROM wp_posts LIMIT 1';
    }
}
