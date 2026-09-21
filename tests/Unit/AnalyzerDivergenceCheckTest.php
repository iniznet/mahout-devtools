<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Doctor\Checks\AnalyzerDivergenceCheck;
use Iniznet\Mahout\Devtools\Doctor\Status;
use Iniznet\Mahout\Devtools\Tests\TestCase;

/**
 * A divergence check with no failing case is a check that does not exist. The
 * consumer fixture passes; a copied rule and a locally declared ruleset fail.
 *
 * @internal
 */
final class AnalyzerDivergenceCheckTest extends TestCase
{
    public function testAFixtureConsumerPasses(): void
    {
        $result = (new AnalyzerDivergenceCheck($this->fixture('consumer-ok')))->examine();

        self::assertSame(Status::Pass, $result->status, $result->detail);
    }

    public function testACopiedLocalRuleFails(): void
    {
        $result = (new AnalyzerDivergenceCheck($this->fixture('consumer-rules-copy')))->examine();

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('local architecture rule', $result->detail);
    }

    public function testALocallyDeclaredRulesetFails(): void
    {
        $result = (new AnalyzerDivergenceCheck($this->fixture('consumer-copy-config')))->examine();

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('does not include the shared configuration', $result->detail);
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__, 2).'/fixtures/divergence/'.$name;
    }
}
