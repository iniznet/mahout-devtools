<?php

/**
 * The check is a directory-shape question, so it is tested against installed
 * shapes rather than against a mock filesystem: each fixture is a wp-content tree
 * a real deployment could contain, and the four answers a doctor can give —
 * one root, two roots, a root found only by the nested pattern, nothing to say —
 * are each a distinct thing an operator needs to read.
 *
 * @internal
 */
declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Doctor\Checks\CompositionRootCheck;
use Iniznet\Mahout\Devtools\Doctor\Status;
use Iniznet\Mahout\Devtools\Tests\TestCase;

final class CompositionRootCheckTest extends TestCase
{
    public function testARootWithoutWpContentIsNotApplicable(): void
    {
        $result = $this->examine('preload-ok');

        self::assertSame(Status::Skip, $result->status);
        self::assertStringContainsString('no wp-content', $result->detail);
    }

    public function testAWpContentWithoutAHostInstallingThePackagesIsNotApplicable(): void
    {
        $result = $this->examine('composition-root-empty');

        self::assertSame(Status::Skip, $result->status);
        self::assertStringContainsString('no host', $result->detail);
    }

    public function testOneHostPassesAndIsNamed(): void
    {
        $result = $this->examine('composition-root-single');

        self::assertSame(Status::Pass, $result->status);
        self::assertStringContainsString('wp-content/themes/howdah', $result->detail);
    }

    public function testAHostFoundOnlyByTheNestedPluginPatternCountsAsOneRoot(): void
    {
        $result = $this->examine('composition-root-nested');

        self::assertSame(Status::Pass, $result->status, 'a plugin nested one level deep is still the only root.');
        self::assertStringContainsString('wp-content/plugins/howdah/mahout-host', $result->detail);
    }

    public function testTwoHostsFailAndBothAreNamed(): void
    {
        $result = $this->examine('composition-root-two');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('wp-content/plugins/howdah-core', $result->detail);
        self::assertStringContainsString('wp-content/themes/howdah', $result->detail);
        self::assertStringContainsString('root of record', $result->detail, 'the failure carries the rule that resolves it.');
    }

    private function examine(string $fixture): \Iniznet\Mahout\Devtools\Doctor\CheckResult
    {
        return (new CompositionRootCheck(\dirname(__DIR__, 2).'/fixtures/doctor/'.$fixture))->examine();
    }
}
