<?php

/**
 * mahout-devtools test bootstrap.
 *
 * Resolves core's first-party test library from WP_TESTS_DIR and the test
 * configuration from WP_TESTS_CONFIG_FILE_PATH. A missing variable fails loudly
 * with its name: a silent skip is a green build with no tests.
 *
 * This is the bootstrap every package requires from its own phpunit.xml.
 */

declare(strict_types=1);

$testsDir = getenv('WP_TESTS_DIR');
if (false === $testsDir || '' === $testsDir) {
    fwrite(STDERR, 'WP_TESTS_DIR is not set. Run composer test.'.PHP_EOL);
    exit(1);
}

if (!file_exists($testsDir.'/includes/bootstrap.php')) {
    fwrite(STDERR, sprintf('WP_TESTS_DIR does not contain includes/bootstrap.php: %s%s', $testsDir, PHP_EOL));
    exit(1);
}

$configFile = getenv('WP_TESTS_CONFIG_FILE_PATH');
if (false === $configFile || '' === $configFile) {
    fwrite(STDERR, 'WP_TESTS_CONFIG_FILE_PATH is not set. Run composer test.'.PHP_EOL);
    exit(1);
}

define('WP_TESTS_CONFIG_FILE_PATH', $configFile);
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__).'/vendor/yoast/phpunit-polyfills');

require_once dirname(__DIR__).'/vendor/autoload.php';
require_once $testsDir.'/includes/functions.php';

tests_add_filter(
    'muplugins_loaded',
    static function (): void {
        // The package under test has no boot hook; the autoloader is already loaded.
    }
);

require_once $testsDir.'/includes/bootstrap.php';

require_once __DIR__.'/TestCase.php';
require_once __DIR__.'/WordPressFunctions.php';
