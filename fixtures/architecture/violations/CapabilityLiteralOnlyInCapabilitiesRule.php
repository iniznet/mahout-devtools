<?php
declare(strict_types=1);

namespace Fixture\Violations\Capability;

current_user_can('edit_theme_options'); // EXPECT: mahout.arch.capabilityLiteralOnlyInCapabilities
