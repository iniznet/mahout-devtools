<?php
declare(strict_types=1);

namespace Fixture\Violations\Nonce;

check_admin_referer('howdah_save'); // EXPECT: mahout.arch.nonceLiteralOnlyInNonces
