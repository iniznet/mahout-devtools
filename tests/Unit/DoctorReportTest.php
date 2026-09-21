<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Doctor\CheckResult;
use Iniznet\Mahout\Devtools\Doctor\DoctorReport;
use Iniznet\Mahout\Devtools\Doctor\Status;
use Iniznet\Mahout\Devtools\Tests\TestCase;

/**
 * The doctor's report DTOs are pure and need no database.
 *
 * @internal
 */
final class DoctorReportTest extends TestCase
{
    public function testExitCodeIsZeroWithoutAFailure(): void
    {
        $report = new DoctorReport([
            CheckResult::pass('a', 'ok'),
            CheckResult::warn('b', 'hmm'),
            CheckResult::skip('c', 'elsewhere'),
        ]);

        self::assertSame(0, $report->exitCode());
        self::assertSame(1, $report->warningCount());
    }

    public function testExitCodeIsOneWithAFailure(): void
    {
        $report = new DoctorReport([
            CheckResult::pass('a', 'ok'),
            CheckResult::fail('b', 'broken'),
        ]);

        self::assertSame(1, $report->exitCode());
        self::assertSame(1, $report->failureCount());
    }

    public function testTableNamesEveryStatus(): void
    {
        $report = new DoctorReport([
            CheckResult::fail('PHP extensions', 'missing: mysqli'),
        ]);

        $table = $report->toTable();

        self::assertStringContainsString('STATUS', $table);
        self::assertStringContainsString('FAIL', $table);
        self::assertStringContainsString('PHP extensions', $table);
        self::assertStringContainsString('missing: mysqli', $table);
        self::assertSame(Status::Fail, $report->results[0]->status);
    }
}
