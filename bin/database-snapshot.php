<?php

/**
 * Prints the number of tables in a named database.
 *
 * Invoked by bin/mahout-devtools test before and after the suite to prove the
 * development database was not touched. Connection values are read from the
 * WordPress test config so no credential is duplicated here.
 *
 * Usage: php bin/database-snapshot.php <config-file> <database>
 */

declare(strict_types=1);

$config   = $argv[1] ?? '';
$database = $argv[2] ?? 'modernwp';

if ('' === $config || !is_file($config)) {
	fwrite(STDERR, sprintf('database-snapshot: config file not found: %s%s', $config, PHP_EOL));
	exit(1);
}

$source = file_get_contents($config);
if (false === $source) {
	fwrite(STDERR, 'database-snapshot: config file unreadable' . PHP_EOL);
	exit(1);
}

$define = static function (string $name) use ($source): string {
	$pattern = sprintf("/define\s*\(\s*'%s'\s*,\s*'([^']*)'\s*\)/", preg_quote($name, '/'));
	if (1 === preg_match($pattern, $source, $matches)) {
		return $matches[1];
	}

	return '';
};

$host = $define('DB_HOST');
$user = $define('DB_USER');
$pass = $define('DB_PASSWORD');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
	$connection = new mysqli('' === $host ? 'localhost' : $host, '' === $user ? 'root' : $user, $pass);
	$statement  = $connection->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
	$statement->bind_param('s', $database);
	$statement->execute();
	$statement->bind_result($count);
	$statement->fetch();
	$statement->close();
	$connection->close();
} catch (mysqli_sql_exception $exception) {
	fwrite(STDERR, 'database-snapshot: ' . $exception->getMessage() . PHP_EOL);
	exit(1);
}

echo (string) (int) $count;
