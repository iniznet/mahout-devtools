<?php

/**
 * The check is a directory-shape question answered against a loaded-set question, so
 * the loaded set is injected: a unit test that needed a running database would be an
 * integration test with a dependency it does not declare. The fixture trees are real
 * wp-content shapes rather than mocks, because the whole point is which directories
 * WordPress would actually include.
 *
 * @internal
 */
declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Doctor\ActiveHosts;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;
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

    public function testOneHostIsTheRootOfRecordWithoutAskingTheDatabase(): void
    {
        // No wp-config.php exists in this fixture, and the answer is still given:
        // a lone tree cannot collide with anything, so requiring the site's own
        // answer here would turn an ordinary installation into a warning.
        $result = $this->examine('composition-root-single');

        self::assertSame(Status::Pass, $result->status);
        self::assertStringContainsString('wp-content/themes/howdah', $result->detail);
        self::assertStringContainsString('nothing else', $result->detail);
    }

    public function testAHostFoundOnlyByTheNestedPluginPatternCountsAsOneRoot(): void
    {
        $result = $this->examine('composition-root-nested');

        self::assertSame(Status::Pass, $result->status, 'a plugin nested one level deep is still the only root.');
        self::assertStringContainsString('wp-content/plugins/howdah/mahout-host', $result->detail);
    }

    public function testTwoLoadedHostsFailAndBothAreNamed(): void
    {
        $result = $this->examine('composition-root-two', ActiveHosts::of('howdah', ['howdah-core']));

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('wp-content/plugins/howdah-core', $result->detail);
        self::assertStringContainsString('wp-content/themes/howdah', $result->detail);
        self::assertStringContainsString('root of record', $result->detail, 'the failure carries the rule that resolves it.');
    }

    public function testOneLoadedHostAmongSeveralTreesPassesAndNamesTheInertRest(): void
    {
        $result = $this->examine('composition-root-two', ActiveHosts::of('howdah', []));

        self::assertSame(Status::Pass, $result->status, 'a source checkout that is not activated cannot collide.');
        self::assertStringContainsString('wp-content/themes/howdah is the root of record', $result->detail);
        self::assertStringContainsString('source-only', $result->detail);
        self::assertStringContainsString('wp-content/plugins/howdah-core', $result->detail);
    }

    public function testSeveralTreesWithNothingLoadedWarnRatherThanPassOrFail(): void
    {
        $result = $this->examine('composition-root-two', ActiveHosts::of('twentyfive', []));

        self::assertSame(Status::Warn, $result->status, 'nothing collides today, and the pending decision is not a pass.');
        self::assertStringContainsString('before activating', $result->detail);
    }

    public function testAMuPluginTreeCountsAsLoadedWithoutBeingAsked(): void
    {
        // mu-plugins needs no option to load it, so a package tree there is an install
        // by definition: two loaded hosts, no database read.
        $result = $this->examine('composition-root-mu', ActiveHosts::of('howdah', []));

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('wp-content/mu-plugins/howdah-core', $result->detail);
    }

    public function testTwoTreesWithNoReachableDatabaseCannotBeJudgedAndSaysSo(): void
    {
        // The fixture has no wp-config.php. "cannot tell" is reported as a warning that
        // names both trees, never as a pass.
        $result = $this->examine('composition-root-two');

        self::assertSame(Status::Warn, $result->status);
        self::assertStringContainsString('cannot be told', $result->detail);
        self::assertStringContainsString('2 hosts', $result->detail);
    }

    public function testTheActivePluginIsMatchedByItsOwnPathAndTheInactiveThemeIsInert(): void
    {
        // The site's theme does not install the packages; the plugin does, and it is
        // the only thing that would boot. That is the recommended shape of a real
        // site, so it must read as one root rather than as two trees.
        $result = $this->examine('composition-root-two', ActiveHosts::of('twentyfive', ['howdah-core']));

        self::assertSame(Status::Pass, $result->status);
        self::assertStringContainsString('wp-content/plugins/howdah-core is the root of record', $result->detail);
        self::assertStringContainsString('wp-content/themes/howdah', $result->detail);
    }

    private function examine(string $fixture, ?ActiveHosts $measured = null): CheckResult
    {
        return (new CompositionRootCheck(\dirname(__DIR__, 2).'/fixtures/doctor/'.$fixture, $measured))->examine();
    }
}
