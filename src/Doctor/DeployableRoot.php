<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor;

/**
 * Whether a root is a deployable WordPress host rather than a library.
 *
 * Two checks needed this question and each had written its own answer. One of them
 * recognised only `wordpress-theme`, so a plugin host — a starter that ships a preload
 * file of its own, an authoritative classmap and a purge seam like the theme — was told
 * its preload set was not applicable and was never examined. The second copy of the rule
 * would have been written the same way again by the next check that asked.
 *
 * A theme and a plugin are both roots that ship to a site; a library ships to a vendor
 * directory and belongs to whoever installs it. That is the whole distinction, and it is
 * read from the manifest the root declares rather than from a directory name.
 *
 * @internal
 */
final class DeployableRoot
{
    /**
     * The Composer types that install into a site and therefore own a runtime shape.
     *
     * @var list<string>
     */
    private const array TYPES = ['wordpress-theme', 'wordpress-plugin'];

    /**
     * @param array<array-key, mixed> $manifest a decoded composer.json
     */
    public static function matches(array $manifest): bool
    {
        $type = $manifest['type'] ?? null;

        return \is_string($type) && \in_array($type, self::TYPES, true);
    }

    /**
     * The same question asked of a directory, for a check that has no manifest in hand.
     */
    public static function isRoot(string $directory): bool
    {
        $manifest = $directory.'/composer.json';

        if (!is_file($manifest)) {
            return false;
        }

        $decoded = json_decode((string) file_get_contents($manifest), true);

        return \is_array($decoded) && self::matches($decoded);
    }
}
