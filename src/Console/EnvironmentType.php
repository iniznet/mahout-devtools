<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Console;

/**
 * The environment type an installation declares.
 *
 * Read as text from `wp-config.php`, the same way the database credentials are,
 * and with the same reason: a diagnostic that boots the site to ask it what it is
 * has stopped being a diagnostic. WordPress defaults to `production` when nothing
 * is declared, which is exactly why an undeclared installation is reported as
 * undeclared rather than assumed to be one thing or the other — the default is a
 * render-time fact, and the assertion here is about what the operator committed.
 */
final readonly class EnvironmentType
{
    private const array KNOWN = ['production', 'staging', 'development', 'local'];

    public static function fromConfigFile(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $source = file_get_contents($path);

        if (false === $source) {
            return null;
        }

        if (1 !== preg_match("/define\s*\(\s*'WP_ENVIRONMENT_TYPE'\s*,\s*'([^']*)'\s*\)/", $source, $matches)) {
            return null;
        }

        $declared = strtolower(trim($matches[1]));

        return \in_array($declared, self::KNOWN, true) ? $declared : null;
    }
}
