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
