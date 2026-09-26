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
    /**
     * @param string|null $tablePrefix the declared `$table_prefix`, or null when the file
     *                                 omits it: a diagnostic that assumed WordPress's own
     *                                 default would read a table that may not be the site's,
     *                                 and report success about a database it did not look at
     */
    public function __construct(
        public string $name,
        public string $host,
        public int $port,
        public string $user,
        public string $password,
        public ?string $tablePrefix = null,
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

        // `$table_prefix` is a variable assignment rather than a define, so it needs
        // its own read. An absent prefix is reported as absent rather than defaulted:
        // a caller that assumed core's own default would read tables that may not be
        // the site's and report success about a database it never looked at.
        $prefix = 1 === preg_match('/^[\\s]*\\$table_prefix[\\s]*=[\\s]*[\\x27"]([^\\x27"]+)[\\x27"]/m', $source, $variable)
            ? $variable[1]
            : null;

        return new self(
            $name,
            '' === $host ? 'localhost' : $host,
            $port,
            '' === $define('DB_USER') ? 'root' : $define('DB_USER'),
            $define('DB_PASSWORD'),
            $prefix,
        );
    }
}
