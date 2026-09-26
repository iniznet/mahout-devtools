<?php

/**
 * The comparison is the assertion, so it is tested with the measurement injected:
 * a unit test that needed a running database would be an integration test with a
 * dependency it does not declare. The three paths that precede the comparison —
 * no installation, no declared name, unreachable server — are tested for real,
 * because each is a distinct thing a doctor has to say.
 *
 * @internal
 */
declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Doctor\Checks\InnoDBBufferPoolCheck;
use Iniznet\Mahout\Devtools\Doctor\InnoDBStatistics;
use Iniznet\Mahout\Devtools\Doctor\Status;
use Iniznet\Mahout\Devtools\Tests\TestCase;

final class InnoDBBufferPoolCheckTest extends TestCase
{
    private const int MEGABYTE = 1048576;

    public function testNoInstallationIsNotApplicable(): void
    {
        $result = $this->examine('preload-ok');

        self::assertSame(Status::Skip, $result->status);
        self::assertStringContainsString('no wp-config.php', $result->detail);
    }

    public function testAConfigWithoutADatabaseNameFails(): void
    {
        $result = $this->examine('buffer-unconfigured');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('declares no database name', $result->detail);
    }

    public function testAnUnreachableServerFailsLoudly(): void
    {
        $result = $this->examine('buffer-unreachable');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('unreachable', $result->detail);
    }

    public function testAWorkingSetThatDoesNotFitFails(): void
    {
        $result = $this->examineWith(InnoDBStatistics::of(64 * self::MEGABYTE, 512 * self::MEGABYTE));

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('does not fit', $result->detail);
    }

    public function testAPoolThatHoldsTheWorkingSetPasses(): void
    {
        $result = $this->examineWith(InnoDBStatistics::of(512 * self::MEGABYTE, 64 * self::MEGABYTE));

        self::assertSame(Status::Pass, $result->status, $result->detail);
        self::assertStringContainsString('8.0x headroom', $result->detail);
    }

    public function testAnEmptyDatabaseReportsWithoutADivisionByZero(): void
    {
        $result = $this->examineWith(InnoDBStatistics::of(128 * self::MEGABYTE, 0));

        self::assertSame(Status::Pass, $result->status, $result->detail);
        self::assertStringNotContainsString('headroom', $result->detail);
    }

    private function examine(string $fixture): \Iniznet\Mahout\Devtools\Doctor\CheckResult
    {
        return (new InnoDBBufferPoolCheck(\dirname(__DIR__, 2).'/fixtures/doctor/'.$fixture))->examine();
    }

    private function examineWith(InnoDBStatistics $statistics): \Iniznet\Mahout\Devtools\Doctor\CheckResult
    {
        return (new InnoDBBufferPoolCheck(
            \dirname(__DIR__, 2).'/fixtures/doctor/buffer-unconfigured',
            $statistics,
        ))->examine();
    }
}
