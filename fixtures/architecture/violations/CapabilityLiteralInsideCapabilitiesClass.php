<?php
declare(strict_types=1);

namespace Fixture\Violations\CapabilityInsideAClassNamedCapabilities;

// A class merely *called* Capabilities is not a declaration of a capability: the
// corpus's Capabilities type holds the name as a typed constant, and the check
// site names the constant. A literal at a check site is a violation wherever the
// check is written.
final class Capabilities
{
    public function allows(): bool
    {
        return current_user_can('edit_theme_options'); // EXPECT: mahout.arch.capabilityLiteralOnlyInCapabilities
    }
}
