<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Integration;

use Iniznet\Mahout\Devtools\Tests\TestCase;

/**
 * Proves the database strategy the corpus requires: a custom table is created
 * once per class, outside the per-test transaction, and a row written inside a
 * test is gone by the next test.
 *
 * @internal
 */
final class CustomTableIsolationTest extends TestCase
{
    private static string $table = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        global $wpdb;
        self::$table = $wpdb->prefix.'mahout_devtools_isolation';

        // DDL implicitly commits, so it must run outside the per-test transaction.
        $wpdb->query(
            'CREATE TABLE IF NOT EXISTS '.self::$table.' (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				note VARCHAR(100) NOT NULL,
				PRIMARY KEY (id)
			) ENGINE=InnoDB'
        );
    }

    public static function tearDownAfterClass(): void
    {
        global $wpdb;
        $wpdb->query('DROP TABLE IF EXISTS '.self::$table);

        parent::tearDownAfterClass();
    }

    public function testARowIsWrittenInsideThePerTestTransaction(): void
    {
        global $wpdb;

        $wpdb->insert(self::$table, ['note' => 'first']);

        self::assertSame(1, (int) $wpdb->get_var('SELECT COUNT(*) FROM '.self::$table));
    }

    public function testTheNextTestSeesNoRow(): void
    {
        global $wpdb;

        self::assertSame(0, (int) $wpdb->get_var('SELECT COUNT(*) FROM '.self::$table));
    }
}
