<?php
declare(strict_types=1);

namespace Iniznet\Mahout\Kernel;

// EXPECT-NONE: error_log is confined to Diagnostics.
final class Diagnostics
{
    public function record(): void
    {
        error_log('booted');
    }
}
