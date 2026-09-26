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

use Iniznet\Mahout\Devtools\Doctor\Checks\OpcacheCheck;
use Iniznet\Mahout\Devtools\Doctor\Status;
use Iniznet\Mahout\Devtools\Tests\TestCase;

final class OpcacheCheckTest extends TestCase
{
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
     * @param array<string, string|false> $settings
     */
    private function examine(string $root, array $settings, bool $loaded, ?string $environment = null): \Iniznet\Mahout\Devtools\Doctor\CheckResult
    {
        return (new OpcacheCheck($root, $settings, $loaded, $environment))->examine();
    }

    private function fixture(string $name): string
    {
        return \dirname(__DIR__, 2).'/fixtures/doctor/'.$name;
    }
}
