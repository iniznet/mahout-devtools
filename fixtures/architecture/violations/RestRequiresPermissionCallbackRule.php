<?php
declare(strict_types=1);

namespace Fixture\Violations\RestRoute;

register_rest_route('howdah/v1', '/series', ['methods' => 'GET']); // EXPECT: mahout.arch.restRequiresPermissionCallback
