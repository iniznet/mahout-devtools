<?php
declare(strict_types=1);

namespace Fixture\Violations\SecurityHeader;

header('X-Frame-Options: DENY'); // EXPECT: mahout.arch.noHandSetSecurityHeader
