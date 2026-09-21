<?php
declare(strict_types=1);

namespace Fixture\Clean\Nonce;

// EXPECT-NONE: the action lives on a Nonces class.
final class Nonces
{
    public function check(): void
    {
        check_admin_referer('howdah_save');
    }
}
