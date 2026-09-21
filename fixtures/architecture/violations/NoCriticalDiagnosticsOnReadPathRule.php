<?php
declare(strict_types=1);

namespace Fixture\Violations\CriticalReadPath;

final class SeriesRepository
{
    public function load(\Fixture\Stub\Diagnostics $diagnostics): void
    {
        $diagnostics->log(\Fixture\Stub\Level::Critical, 'slow'); // EXPECT: mahout.arch.noCriticalDiagnosticsOnReadPath
    }
}
