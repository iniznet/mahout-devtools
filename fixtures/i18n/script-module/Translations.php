<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Fixtures\I18n;

/**
 * A script module registered with a foreign domain: the i18n gate must refuse.
 */
final class ScriptModule
{
    public function register(): void
    {
        wp_set_script_module_translations('howdah-app', 'other-domain', '/languages');
    }
}
