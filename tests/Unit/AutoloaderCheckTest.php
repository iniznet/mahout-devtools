<?php

/**
 * The deployable's autoloader is asserted on two separate grounds — what the
 * manifest declares and what the installed map actually is — because a build can
 * drift from either. A library root is asserted to have an autoloader only.
 *
 * @internal
 */
declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Console\ProcessRunner;
use Iniznet\Mahout\Devtools\Doctor\Checks\AutoloaderCheck;
use Iniznet\Mahout\Devtools\Doctor\Status;
use Iniznet\Mahout\Devtools\Tests\TestCase;

final class AutoloaderCheckTest extends TestCase
{
    public function testAnAbsentAutoloaderFails(): void
    {
        $result = $this->examine('preload-missing');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('vendor/autoload.php missing', $result->detail);
    }

    public function testALibraryRootIsPresentOnly(): void
    {
        $result = $this->examine('autoload-library');

        self::assertSame(Status::Pass, $result->status, $result->detail);
        self::assertStringContainsString("consumer's shape", $result->detail);
    }

    public function testADevolvedMapOnADeployableFails(): void
    {
        $result = $this->examine('autoload-devolved');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('devolved', $result->detail);
    }

    public function testAnAuthoritativeMapThatIsNotDeclaredFails(): void
    {
        $result = $this->examine('autoload-undeclared');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('does not declare config.classmap-authoritative', $result->detail);
    }

    public function testADeployableDeclaredAndBuiltPasses(): void
    {
        $result = $this->examine('autoload-ok');

        self::assertSame(Status::Pass, $result->status, $result->detail);
    }

    private function examine(string $fixture): \Iniznet\Mahout\Devtools\Doctor\CheckResult
    {
        return (new AutoloaderCheck(\dirname(__DIR__, 2).'/fixtures/doctor/'.$fixture, new ProcessRunner()))->examine();
    }
}
