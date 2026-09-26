<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Console;

/**
 * The connection values a WordPress installation declares.
 *
 * The defines are read as text, never evaluated: loading `wp-config.php` would
 * run the site's own bootstrap, and a diagnostic that boots the thing it is
 * measuring has stopped being a measurement.
 */
final readonly class DatabaseCredentials
{
    public function __construct(
        public string $name,
        public string $host,
        public int $port,
        public string $user,
        public string $password,
    ) {
    }

    /**
     * `null` when the file cannot be read or declares no database name; the
     * caller reports the absence rather than guessing at a connection.
     */
    public static function fromConfigFile(string $path): ?self
    {
        if (!is_file($path)) {
            return null;
        }

        $source = file_get_contents($path);

        if (false === $source) {
            return null;
        }

        $define = static function (string $name) use ($source): string {
            $pattern = sprintf("/define\s*\(\s*'%s'\s*,\s*'([^']*)'\s*\)/", preg_quote($name, '/'));

            return 1 === preg_match($pattern, $source, $matches) ? $matches[1] : '';
        };

        $name = $define('DB_NAME');

        if ('' === $name) {
            return null;
        }

        $host = $define('DB_HOST');
        $port = 3306;

        if (str_contains($host, ':')) {
            [$host, $declared] = explode(':', $host, 2);

            if (is_numeric($declared)) {
                $port = (int) $declared;
            }
        }

        return new self(
            $name,
            '' === $host ? 'localhost' : $host,
            $port,
            '' === $define('DB_USER') ? 'root' : $define('DB_USER'),
            $define('DB_PASSWORD'),
        );
    }
}
