<?php

/**
 * A preload that fatals takes every site on the pool down with it, so the check
 * that runs it in a child process needs cases for missing, broken, healthy and
 * not-applicable — the last one because a library ships no preload set at all.
 *
 * @internal
 */
declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Console\ProcessRunner;
use Iniznet\Mahout\Devtools\Doctor\Checks\PreloadFileCheck;
use Iniznet\Mahout\Devtools\Doctor\Status;
use Iniznet\Mahout\Devtools\Tests\TestCase;

final class PreloadFileCheckTest extends TestCase
{
    public function testALibraryRootIsNotApplicable(): void
    {
        self::assertSame(Status::Skip, $this->examine('preload-library')->status);
    }

    public function testAMissingPreloadFileFails(): void
    {
        $result = $this->examine('preload-missing');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('no preload.php', $result->detail);
    }

    public function testAPreloadThatDoesNotParseFails(): void
    {
        $result = $this->examine('preload-broken');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('does not parse', $result->detail);
    }

    public function testAPreloadFileThatNothingPointsAtIsReported(): void
    {
        $result = $this->examine('preload-ok', null);

        self::assertSame(Status::Warn, $result->status);
        self::assertStringContainsString('does not name it', $result->detail);
        self::assertStringContainsString('unset', $result->detail);
    }

    public function testAPreloadDirectiveNamingADifferentFileIsReported(): void
    {
        $result = $this->examine('preload-ok', '/srv/other/preload-other.php');

        self::assertSame(Status::Warn, $result->status);
        self::assertStringContainsString('does not name it', $result->detail);
    }

    public function testAPreloadIsExercisedAsFarAsThePlatformAllows(): void
    {
        $result = $this->examine('preload-ok', $this->path('preload-ok').'/preload.php');

        // Windows cannot load opcache.preload at all, so the deepest assertion
        // available there is that the file parses; the pool-start probe runs on the
        // platform the deployable actually serves from.
        $expected = 'Windows' === \PHP_OS_FAMILY ? Status::Warn : Status::Pass;

        self::assertSame($expected, $result->status, $result->detail);
    }

    private function path(string $fixture): string
    {
        return \dirname(__DIR__, 2).'/fixtures/doctor/'.$fixture;
    }

    private function examine(string $fixture, ?string $configured = null): \Iniznet\Mahout\Devtools\Doctor\CheckResult
    {
        return (new PreloadFileCheck($this->path($fixture), new ProcessRunner(), $configured))->examine();
    }
}
