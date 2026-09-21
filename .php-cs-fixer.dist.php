<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$root = getcwd() ?: __DIR__;

$finder = Finder::create()
	->in([$root . '/src', $root . '/tests'])
	->exclude('Fixtures');

return (new Config())
	->setRiskyAllowed(true)
	->setRules([
		'@PSR12' => true,
		'@Symfony' => true,
		'declare_strict_types' => true,
	])
	->setFinder($finder);
