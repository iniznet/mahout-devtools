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

    public function testAClassmapThatOmitsClassesInTheTreesItClaimsFails(): void
    {
        $result = $this->examine('autoload-stale');

        self::assertSame(Status::Fail, $result->status, $result->detail);
        self::assertStringContainsString('does not cover 2 declared classes', $result->detail);
        self::assertStringContainsString('Iniznet\\Mahout\\Fixture\\AddedAfterTheDump', $result->detail, 'a second class in a file the map references');
        self::assertStringContainsString('Iniznet\\Mahout\\Fixture\\Unreferenced', $result->detail, 'a file with no entry at all is the hazard: a new class in a symlinked package');
        self::assertStringContainsString('composer dump-autoload -o', $result->detail, 'the failure names the command that fixes it');
    }

    private function examine(string $fixture): \Iniznet\Mahout\Devtools\Doctor\CheckResult
    {
        return (new AutoloaderCheck(\dirname(__DIR__, 2).'/fixtures/doctor/'.$fixture, new ProcessRunner()))->examine();
    }
}
