<?php
declare(strict_types=1);

namespace Fixture\Clean\Capability;

// EXPECT-NONE: the capability lives on a Capabilities class.
final class Capabilities
{
    public function allows(): bool
    {
        return current_user_can('edit_theme_options');
    }
}
