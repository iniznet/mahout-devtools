<?php
declare(strict_types=1);

namespace Fixture\Clean\Capability;

// EXPECT-NONE: the capability is a typed constant on the Capabilities type, and
// the check site names the constant rather than a literal.
enum Capabilities: string
{
    case EditThemeOptions = 'edit_theme_options';
}

final class ThemeSettingsScreen
{
    public function allows(): bool
    {
        return current_user_can(Capabilities::EditThemeOptions->value);
    }
}
