<?php

/**
 * REP-11 has two halves and each needs a failing case: a committed manifest that
 * names a path repository, and a committed lock that pins a package to one. The
 * second is the half that decides whether a fresh clone installs, and it is the
 * one a manifest-only check cannot see.
 *
 * @internal
 */
declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Doctor\Checks\PathRepositoryCheck;
use Iniznet\Mahout\Devtools\Doctor\Status;
use Iniznet\Mahout\Devtools\Tests\TestCase;

final class PathRepositoryCheckTest extends TestCase
{
    public function testACleanConsumerPasses(): void
    {
        $result = $this->examine('clean');

        self::assertSame(Status::Pass, $result->status, $result->detail);
    }

    public function testARepositoryWithoutALockPasses(): void
    {
        $result = $this->examine('no-lock');

        self::assertSame(Status::Pass, $result->status, $result->detail);
    }

    public function testACommittedPathRepositoryFails(): void
    {
        $result = $this->examine('committed-path');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('path repository', $result->detail);
    }

    public function testALockPinnedToAPathDistFails(): void
    {
        $result = $this->examine('locked-path');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('path dist', $result->detail);
        self::assertStringContainsString('iniznet/mahout-kernel', $result->detail, 'the failing package is named.');
    }

    private function examine(string $fixture): \Iniznet\Mahout\Devtools\Doctor\CheckResult
    {
        return (new PathRepositoryCheck(\dirname(__DIR__, 2).'/fixtures/pathrepo/'.$fixture))->examine();
    }
}
