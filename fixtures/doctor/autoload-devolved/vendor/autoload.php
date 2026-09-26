<?php
declare(strict_types=1);

require dirname(__DIR__, 4).'/vendor/composer/ClassLoader.php';

$loader = new \Composer\Autoload\ClassLoader();
$loader->setClassMapAuthoritative(false);

return $loader;
