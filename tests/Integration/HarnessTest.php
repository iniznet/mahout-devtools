<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Integration;

use Iniznet\Mahout\Devtools\Tests\TestCase;

/**
 * Proves the Phase 1 harness: a real WordPress loads against the dedicated test
 * database, and core's factories round-trip through it.
 *
 * @internal
 */
final class HarnessTest extends TestCase
{
    public function testTheDedicatedTestDatabaseIsInUse(): void
    {
        global $wpdb;

        self::assertSame('modernwp_tests', DB_NAME);
        self::assertSame('modernwp_tests', $wpdb->dbname);
        self::assertNotSame('modernwp', DB_NAME);
    }

    public function testAPostRoundTripsThroughTheDatabase(): void
    {
        $id = self::factory()->post->create(['post_title' => 'Phase 1a harness']);

        self::assertIsInt($id);
        self::assertSame('Phase 1a harness', get_post($id)->post_title);
    }
}
