<?php
declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Fixture;

// EXPECT-NONE: reflection is permitted inside the devtools package.
$reflection = new \ReflectionClass(\stdClass::class);
