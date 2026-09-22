<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

/*
 * The shared ruleset every repository references. The scanned paths are the
 * consumer's to declare: a repository may ship a .php-cs-fixer.paths.php
 * returning the directory names to scan, relative to its own root. The
 * default is the family's common layout.
 *
 * @var string[] $paths
 */
$root = getcwd() ?: __DIR__;
$paths = ['src', 'tests'];
if (is_file($override = $root.'/.php-cs-fixer.paths.php')) {
	$declared = require $override;

	if (!is_array($declared)) {
		throw new RuntimeException('.php-cs-fixer.paths.php must return a list of directory names');
	}

	$paths = $declared;
}

$finder = Finder::create()
	->in(array_map(static fn (string $path): string => $root.'/'.$path, $paths))
	->exclude('Fixtures');

return (new Config())
	->setRiskyAllowed(true)
	->setRules([
		'@PSR12' => true,
		'@Symfony' => true,
		'declare_strict_types' => true,
	])
	->setFinder($finder);
