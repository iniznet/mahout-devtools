<?php
declare(strict_types=1);

namespace Fixture\Clean\Dispatch;

// EXPECT-NONE: the plan declares its cacheability.
$plan = new \Fixture\Clean\Dispatch\SurfacePlan(cacheability: 'shared');
