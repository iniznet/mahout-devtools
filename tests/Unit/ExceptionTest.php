<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Exception\CommandFailed;
use Iniznet\Mahout\Devtools\Exception\DevtoolsException;
use Iniznet\Mahout\Devtools\Exception\FileMissing;
use Iniznet\Mahout\Devtools\Exception\StubGenerationFailed;
use Iniznet\Mahout\Devtools\Tests\TestCase;

/**
 * Every exception is reached only through a named constructor and carries the
 * condition, not the throw site.
 *
 * @internal
 */
final class ExceptionTest extends TestCase
{
    public function testFileMissingNamesThePath(): void
    {
        $exception = FileMissing::at('/tmp/example.php');

        self::assertInstanceOf(DevtoolsException::class, $exception);
        self::assertStringContainsString('/tmp/example.php', $exception->getMessage());
    }

    public function testFileMissingNamesTheDescription(): void
    {
        $exception = FileMissing::containing('stub file', '/tmp/stubs.php');

        self::assertStringContainsString('stub file', $exception->getMessage());
        self::assertStringContainsString('/tmp/stubs.php', $exception->getMessage());
    }

    public function testStubGenerationFailedReportsTheReason(): void
    {
        $exception = StubGenerationFailed::because('core did not boot');

        self::assertInstanceOf(DevtoolsException::class, $exception);
        self::assertStringContainsString('core did not boot', $exception->getMessage());
    }

    public function testStubGenerationFailedReportsTheExitCode(): void
    {
        self::assertStringContainsString('7', StubGenerationFailed::processExited(7)->getMessage());
    }

    public function testCommandFailedReportsTheExitCode(): void
    {
        $exception = CommandFailed::exited('phpunit', 2);

        self::assertInstanceOf(DevtoolsException::class, $exception);
        self::assertStringContainsString('phpunit', $exception->getMessage());
        self::assertStringContainsString('2', $exception->getMessage());
    }

    public function testCommandFailedReportsAStartFailure(): void
    {
        self::assertStringContainsString('nope', CommandFailed::couldNotStart('nope')->getMessage());
    }
}
