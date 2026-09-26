<?php

/**
 * Which hosts this installation would actually load.
 *
 * A directory under `wp-content` that carries the mahout packages is not the same
 * fact as a host that boots. Two starter checkouts on one workstation sit side by
 * side and only one of them is active, and a check that counted directories would
 * report that ordinary development state as the collision it exists to prevent —
 * which is how a gate gets deleted. So the filesystem scan and the loaded set are
 * read separately and compared.
 *
 * The loaded set is two options: `stylesheet`, the directory of the active theme, and
 * `active_plugins`, the list of `dir/file.php` paths of the activated plugins. Both are
 * read from the site's own options table over the same connection the buffer-pool check
 * uses, and `wp-config.php` is parsed as text rather than loaded, for the same reason:
 * a diagnostic that boots the thing it measures has stopped measuring it.
 *
 * `mu-plugins` are deliberately reported as loaded by the caller rather than looked up
 * here: everything in that directory is always-on with no option recording it, so a
 * package tree found there is an install by definition.
 */

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor;

final readonly class ActiveHosts
{
    /**
     * @param string|null  $stylesheet the active theme's directory, or null when the site has none yet
     * @param list<string> $plugins    the directory of every activated plugin, relative to `wp-content/plugins`
     */
    private function __construct(
        public ?string $stylesheet,
        public array $plugins,
    ) {
    }

    /**
     * @param list<string> $plugins
     */
    public static function of(?string $stylesheet, array $plugins): self
    {
        sort($plugins);

        return new self($stylesheet, $plugins);
    }

    /**
     * Read from the running site, or null when the options table is not reachable or
     * the prefix is not a usable identifier.
     */
    public static function fromConnection(\mysqli $connection, string $prefix): ?self
    {
        if (1 !== preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            return null;
        }

        $statement = $connection->prepare(
            'SELECT option_name, option_value FROM `'.$prefix.'options` WHERE option_name IN (?, ?)',
        );

        if (false === $statement) {
            return null;
        }

        $names = 'stylesheet';
        $second = 'active_plugins';
        $statement->bind_param('ss', $names, $second);

        if (!$statement->execute()) {
            $statement->close();

            return null;
        }

        $statement->bind_result($name, $value);
        $found = [];

        while ($statement->fetch()) {
            // Both columns are text, so anything else means the table is not the shape
            // this read assumes. The answer is reported as unavailable rather than
            // coerced: a check that cast a surprise into a string would go on to
            // compare it as though it meant something.
            if (!is_string($name) || !is_string($value)) {
                $statement->close();

                return null;
            }

            $found[$name] = $value;
        }

        $statement->close();

        if ([] === $found) {
            return null;
        }

        return new self(
            $found['stylesheet'] ?? null,
            self::pluginDirectories($found['active_plugins'] ?? ''),
        );
    }

    /**
     * The directories behind `active_plugins`, which stores `dir/file.php` paths
     * relative to the plugins directory.
     *
     * The option is PHP-serialized, and unserialized with no classes allowed: the
     * value is a list of strings or nothing, and a diagnostic that instantiated
     * objects from a database it is only reading has become a request.
     *
     * @return list<string>
     */
    private static function pluginDirectories(string $serialized): array
    {
        if ('' === $serialized) {
            return [];
        }

        $decoded = @unserialize($serialized, ['allowed_classes' => false]);

        if (!is_array($decoded)) {
            return [];
        }

        $directories = [];

        foreach ($decoded as $entry) {
            if (!is_string($entry) || '' === $entry) {
                continue;
            }

            $separator = strrpos($entry, '/');

            if (false === $separator) {
                // A plugin at the top level of the directory: `hello.php` has no
                // directory of its own, so it names no host tree.
                continue;
            }

            // Core stores these with forward slashes; normalising the separator keeps
            // a path compared against a scanned directory on a Windows checkstand from
            // being the thing that decides whether a collision is visible.
            $directories[] = str_replace('\\', '/', substr($entry, 0, $separator));
        }

        return array_values(array_unique($directories));
    }
}
