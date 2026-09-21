<?php
declare(strict_types=1);

namespace Fixture\Clean\RestRoute;

// EXPECT-NONE: an explicit permission callback is present.
register_rest_route('howdah/v1', '/series', ['methods' => 'GET', 'permission_callback' => '__return_true']);
