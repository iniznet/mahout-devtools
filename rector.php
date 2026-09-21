<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

$root = getcwd() ?: __DIR__;

return RectorConfig::configure()
	->withPaths([$root . '/src'])
	->withPhpSets(php84: true);
