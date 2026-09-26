<?php
declare(strict_types=1);

if (function_exists('opcache_compile_file') && is_file(__DIR__.'/subject.php')) {
    opcache_compile_file(__DIR__.'/subject.php');
}
