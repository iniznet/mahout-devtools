<?php
declare(strict_types=1);

namespace Fixture\Clean\CriticalReadPath;

// EXPECT-NONE: an informational record is not a critical read-path log.
final class SeriesRepository
{
    public function load(\Fixture\Stub\Diagnostics $diagnostics): void
    {
        $diagnostics->log(\Fixture\Stub\Level::Info, 'loaded');
    }
}
