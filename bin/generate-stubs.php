<?php

/**
 * Generates the WordPress stub file from an installed core.
 *
 * Invoked by bin/mahout-devtools stubs:generate and stubs:check. This file is a
 * build script, not part of the analysed source tree: it reads the environment,
 * boots core's own test library, diffs the declared symbols against a pre-boot
 * snapshot, and renders the result through StubGenerator.
 *
 * Usage: php bin/generate-stubs.php <output-path>
 */

declare(strict_types=1);

use Iniznet\Mahout\Devtools\Stubs\StubGenerator;

$output = $argv[1] ?? '';
if ('' === $output) {
	fwrite(STDERR, 'usage: php bin/generate-stubs.php <output-path>' . PHP_EOL);
	exit(2);
}

/**
 * Read a required environment variable or stop with its name.
 */
function mahout_devtools_env(string $name): string
{
	$value = getenv($name);
	if (false === $value || '' === $value) {
		fwrite(STDERR, sprintf('%s is not set.%s', $name, PHP_EOL));
		exit(1);
	}

	return $value;
}

$wordpressRoot = str_replace('\\', '/', mahout_devtools_env('MAHOUT_WP_ROOT'));
$wordpressRoot = rtrim($wordpressRoot, '/');
$testsDir      = mahout_devtools_env('WP_TESTS_DIR');
$configFile    = mahout_devtools_env('WP_TESTS_CONFIG_FILE_PATH');
$vendorDir     = dirname(__DIR__) . '/vendor';

if (!is_file($testsDir . '/includes/bootstrap.php')) {
	fwrite(STDERR, sprintf('WP_TESTS_DIR does not contain includes/bootstrap.php: %s%s', $testsDir, PHP_EOL));
	exit(1);
}

define('WP_TESTS_CONFIG_FILE_PATH', $configFile);
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $vendorDir . '/yoast/phpunit-polyfills');

require_once $vendorDir . '/autoload.php';

$beforeFunctions = get_defined_functions()['user'];
$beforeClasses   = array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits());
$beforeConstants = get_defined_constants(true)['user'] ?? [];

require_once $testsDir . '/includes/functions.php';
tests_add_filter('muplugins_loaded', static function (): void {});
require_once $testsDir . '/includes/bootstrap.php';

$adminApi = $wordpressRoot . '/wp-admin/includes/admin.php';
if (is_file($adminApi)) {
	require_once $adminApi;
}

$afterFunctions = get_defined_functions()['user'];
$afterClasses   = array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits());
$afterConstants = get_defined_constants(true)['user'] ?? [];

$contentRoot = $wordpressRoot . '/wp-content';

$functions = [];
foreach (array_diff($afterFunctions, $beforeFunctions) as $function) {
	$reflection = new ReflectionFunction($function);
	$file       = $reflection->getFileName();
	if (is_string($file)) {
		$file = str_replace('\\', '/', $file);
		if (str_starts_with($file, $wordpressRoot) && !str_starts_with($file, $contentRoot)) {
			$functions[] = $function;
		}
	}
}

$classes = [];
foreach (array_diff($afterClasses, $beforeClasses) as $class) {
	$reflection = new ReflectionClass($class);
	$file       = $reflection->getFileName();
	if (is_string($file)) {
		$file = str_replace('\\', '/', $file);
		if (str_starts_with($file, $wordpressRoot) && !str_starts_with($file, $contentRoot)) {
			$classes[] = $class;
		}
	}
}

/**
 * Configuration, database and test-library constants are not WordPress API.
 */
$constantDenylist = [
	'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET', 'DB_COLLATE',
	'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
	'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
	'WP_PHP_BINARY', 'WPLANG', 'TEST_COOKIE', 'WP_START_TIMESTAMP',
	'DIR_TESTDATA', 'DIR_TESTROOT', 'REST_TESTS_IMPOSSIBLY_HIGH_NUMBER',
];

$constants = [];
foreach (array_diff_key($afterConstants, $beforeConstants) as $name => $value) {
	if (in_array($name, $constantDenylist, true)) {
		continue;
	}
	if (str_starts_with($name, 'WP_TESTS') || str_starts_with($name, 'WP_PHPUNIT') || str_starts_with($name, 'PHPUNIT')) {
		continue;
	}
	$constants[$name] = $value;
}

ksort($constants, SORT_STRING);

/*
 * The stub file is loaded by PHP as a bootstrap, so a class declaration must
 * follow the declarations it extends, implements or uses. Depth-first over the
 * alphabetically sorted set is deterministic.
 */
$known = array_fill_keys($classes, true);
$ordered = [];
$visiting = [];

$visit = function (string $class) use (&$visit, &$ordered, &$visiting, $known): void {
	if (isset($ordered[$class]) || isset($visiting[$class])) {
		return;
	}

	$visiting[$class] = true;
	$reflection       = new ReflectionClass($class);

	$dependencies = [];
	$parent       = $reflection->getParentClass();
	if (false !== $parent) {
		$dependencies[] = $parent->getName();
	}
	foreach ($reflection->getInterfaceNames() as $interface) {
		$dependencies[] = $interface;
	}
	foreach ($reflection->getTraitNames() as $trait) {
		$dependencies[] = $trait;
	}
	sort($dependencies, SORT_STRING);

	foreach ($dependencies as $dependency) {
		if (isset($known[$dependency]) && $dependency !== $class) {
			$visit($dependency);
		}
	}

	unset($visiting[$class]);
	$ordered[$class] = true;
};

$alphabetical = $classes;
sort($alphabetical, SORT_STRING);
foreach ($alphabetical as $class) {
	$visit($class);
}
$classes = array_keys($ordered);

$stub = (new StubGenerator($functions, $classes, $constants))->render();

if (false === file_put_contents($output, $stub)) {
	fwrite(STDERR, sprintf('Could not write %s%s', $output, PHP_EOL));
	exit(1);
}

printf(
	'wrote %s (%d functions, %d classes, %d constants)%s',
	$output,
	count($functions),
	count($classes),
	count($constants),
	PHP_EOL
);
