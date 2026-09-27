<?php

/**
 * The opcode-cache assertion has three outcomes and each needs a case, because the
 * difference between them is the difference between a broken install, a development
 * box, and a production one.
 *
 * @internal
 */
declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Unit;

use Iniznet\Mahout\Devtools\Doctor\ActiveHosts;
use Iniznet\Mahout\Devtools\Doctor\Checks\OpcacheCheck;
use Iniznet\Mahout\Devtools\Doctor\Status;
use Iniznet\Mahout\Devtools\Tests\TestCase;

final class OpcacheCheckTest extends TestCase
{
    /** Room for any fixture's floor, so the case under test is the count and not the verdict. */
    private const array ROOMY_SETTINGS = [
        'opcache.enable' => '1',
        'opcache.validate_timestamps' => '0',
        'opcache.max_accelerated_files' => '20000',
        'opcache.memory_consumption' => '512',
    ];

    public function testAnAbsentExtensionWarnsRatherThanPassing(): void
    {
        $result = $this->examine(root: '', settings: [], loaded: false);

        self::assertSame(Status::Warn, $result->status);
        self::assertStringContainsString('not loaded in the SAPI', $result->detail);
    }

    public function testOpcacheDisabledFails(): void
    {
        $result = $this->examine(root: '', settings: ['opcache.enable' => '0'], loaded: true);

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('opcache.enable is off', $result->detail);
    }

    public function testTimestampValidationWarnsWithTheRemedy(): void
    {
        $result = $this->examine(root: $this->fixture('wp-floor'), settings: [
            'opcache.enable' => '1',
            'opcache.validate_timestamps' => '1',
            'opcache.max_accelerated_files' => '20000',
        ], loaded: true);

        self::assertSame(Status::Warn, $result->status);
        self::assertStringContainsString('production sets it to 0', $result->detail);
    }

    public function testAProductionShapePasses(): void
    {
        $result = $this->examine(root: $this->fixture('wp-floor'), settings: [
            'opcache.enable' => '1',
            'opcache.validate_timestamps' => '0',
            'opcache.max_accelerated_files' => '20000',
        ], loaded: true);

        self::assertSame(Status::Pass, $result->status, $result->detail);
        self::assertStringContainsString('measured file floor', $result->detail);
    }

    public function testATableSmallerThanTheInstallationFails(): void
    {
        $result = $this->examine(root: $this->fixture('wp-floor'), settings: [
            'opcache.enable' => '1',
            'opcache.validate_timestamps' => '0',
            'opcache.max_accelerated_files' => '2',
        ], loaded: true);

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('evicts under traffic', $result->detail);
    }

    public function testAnUnmeasurableRootReportsRatherThanInventingAFloor(): void
    {
        $result = $this->examine(root: $this->fixture('preload-ok'), settings: [
            'opcache.enable' => '1',
            'opcache.validate_timestamps' => '0',
            'opcache.max_accelerated_files' => '10000',
        ], loaded: true);

        self::assertSame(Status::Pass, $result->status, $result->detail);
        self::assertStringContainsString('not measurable here', $result->detail);
    }

    public function testDeclaredProductionFailsValidationAndMemoryTogether(): void
    {
        $result = $this->examine($this->fixture('wp-floor'), [
            'opcache.enable' => '1',
            'opcache.validate_timestamps' => '1',
            'opcache.max_accelerated_files' => '20000',
            'opcache.memory_consumption' => '128',
        ], loaded: true, environment: 'production');

        self::assertSame(Status::Fail, $result->status);
        self::assertStringContainsString('production installation', $result->detail);
        self::assertStringContainsString('memory_consumption is 128 MB', $result->detail);
    }

    public function testADeclaredDevelopmentBoxIsNotHeldToTheProductionFigure(): void
    {
        $result = $this->examine($this->fixture('wp-floor'), [
            'opcache.enable' => '1',
            'opcache.validate_timestamps' => '1',
            'opcache.max_accelerated_files' => '20000',
            'opcache.memory_consumption' => '128',
        ], loaded: true, environment: 'development');

        self::assertSame(Status::Warn, $result->status, $result->detail);
        self::assertStringContainsString('declared environment development', $result->detail);
    }

    public function testAnUndeclaredInstallationSaysSo(): void
    {
        $result = $this->examine($this->fixture('wp-floor'), [
            'opcache.enable' => '1',
            'opcache.validate_timestamps' => '0',
            'opcache.max_accelerated_files' => '20000',
            'opcache.memory_consumption' => '512',
        ], loaded: true, environment: null);

        self::assertSame(Status::Pass, $result->status, $result->detail);
        self::assertStringContainsString('production-only assertions', $result->detail);
    }

    public function testATinyRealpathCacheIsNoted(): void
    {
        $result = $this->examine($this->fixture('wp-floor'), [
            'opcache.enable' => '1',
            'opcache.validate_timestamps' => '0',
            'opcache.max_accelerated_files' => '20000',
            'opcache.memory_consumption' => '512',
            'realpath_cache_size' => '512',
        ], loaded: true, environment: 'production');

        self::assertSame(Status::Pass, $result->status, $result->detail);
        self::assertStringContainsString('realpath_cache_size 512 KB', $result->detail);
    }

    /**
     * Two starter trees on one disk is an ordinary development machine, not an
     * installation that can load both, and a floor that counted the inactive one
     * would fail the gate for the state of the developer's disk. This asserts the
     * scoping by number rather than by wording: the reported floor is the active
     * host's file count and nothing else's.
     */
    public function testTheCensusCountsOnlyTheActiveHost(): void
    {
        $root = $this->installation([
            'wp-includes/a.php' => 2,
            'wp-admin/b.php' => 1,
            'wp-content/mu-plugins/c.php' => 1,
            'wp-content/themes/active/d.php' => 3,
            'wp-content/themes/inactive/e.php' => 9,
            'wp-content/plugins/active/f.php' => 4,
            'wp-content/plugins/inactive/g.php' => 8,
        ]);

        $result = $this->examine($root, self::ROOMY_SETTINGS, true, hosts: ActiveHosts::of('active', ['active']));
        $reported = $this->floor($result->detail);

        self::assertSame(11, $reported, $result->detail);
        self::assertStringNotContainsString('pessimistic', $result->detail);
    }

    /**
     * The fallback is allowed to over-count, because a floor too high is a louder
     * failure than a floor too low; what it is not allowed to do is over-count
     * silently and read like the exact answer.
     */
    public function testAnUnreadableActiveSetCountsEveryHostAndSaysSo(): void
    {
        $root = $this->installation([
            'wp-includes/a.php' => 2,
            'wp-content/themes/active/d.php' => 3,
            'wp-content/themes/inactive/e.php' => 9,
        ]);

        $result = $this->examine($root, self::ROOMY_SETTINGS, true, hosts: null);

        self::assertSame(14, $this->floor($result->detail), $result->detail);
        self::assertStringContainsString('pessimistic', $result->detail);
    }

    /**
     * @param array<string, int> $files path => number of PHP files to write
     */
    private function installation(array $files): string
    {
        $root = sys_get_temp_dir().'/mahout-opcache-'.uniqid();

        foreach ($files as $relative => $copies) {
            $directory = $root.'/'.dirname((string) $relative);

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            for ($index = 0; $index < $copies; ++$index) {
                file_put_contents($root.'/'.dirname((string) $relative).'/file'.$index.'.php', "<?php\n");
            }
        }

        return $root;
    }

    private function floor(string $detail): int
    {
        if (1 !== preg_match('/>= (\d+) measured file floor/', $detail, $count)) {
            self::fail('the detail carries no measured floor: '.$detail);
        }

        return (int) $count[1];
    }

    /**
     * @param array<string, string|false> $settings
     */
    private function examine(string $root, array $settings, bool $loaded, ?string $environment = null, ?ActiveHosts $hosts = null): \Iniznet\Mahout\Devtools\Doctor\CheckResult
    {
        return (new OpcacheCheck($root, $settings, $loaded, $environment, $hosts))->examine();
    }

    private function fixture(string $name): string
    {
        return \dirname(__DIR__, 2).'/fixtures/doctor/'.$name;
    }
}
