<?php

/**
 * A divergence check with no failing case is a check that does not exist. Each
 * way a repository can drift from the family's gates has a fixture that fails,
 * and the substitution of the derived source is pinned by a fixture whose
 * analysed path is not `src`.
 *
 * @internal
 */
declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Doctor\CheckResult;
use Iniznet\Mahout\Devtools\Doctor\Checks\GatesManifestCheck;
use Iniznet\Mahout\Devtools\Doctor\Status;
use Iniznet\Mahout\Devtools\Tests\TestCase;

final class GatesManifestCheckTest extends TestCase
{
    private const string MANIFEST = __DIR__.'/../../resources/gates.json';

    public function testAConformingConsumerPasses(): void
    {
        $result = $this->examine('consumer-ok');

        self::assertSame(Status::Pass, $result->status, $result->detail);
    }

    public function testADroppedGateFails(): void
    {
        $result = $this->examine('gate-missing');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('the psalm gate is missing', $result->detail);
    }

    public function testARedefinedGateFails(): void
    {
        $result = $this->examine('gate-redefined');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('the stan gate is redefined', $result->detail);
    }

    public function testAnOutOfOrderCheckEventFails(): void
    {
        $result = $this->examine('event-order');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('out of order', $result->detail);
    }

    public function testAnUndeclaredStepInTheCheckEventFails(): void
    {
        $result = $this->examine('event-undeclared');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('the manifest does not declare', $result->detail);
    }

    public function testAConsumerWithNoAnalysedPathFails(): void
    {
        $result = $this->examine('source-undeclared');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('gate source cannot be derived', $result->detail);
    }

    public function testTheDerivedSourceSubstitutesEverywhere(): void
    {
        $result = $this->examine('consumer-app-source');

        self::assertSame(Status::Pass, $result->status, $result->detail);
    }

    private function examine(string $fixture): CheckResult
    {
        return (new GatesManifestCheck(
            \dirname(__DIR__, 2).'/fixtures/gates/'.$fixture,
            self::MANIFEST,
        ))->examine();
    }
}
