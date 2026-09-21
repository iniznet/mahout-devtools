<?php
declare(strict_types=1);

namespace Fixture\Violations\Cookie;

setcookie('howdah', 'value'); // EXPECT: mahout.arch.noCookie
