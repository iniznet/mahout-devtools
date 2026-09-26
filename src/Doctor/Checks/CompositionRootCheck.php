<?php

/**
 * Exactly one host in this installation may own the runtime.
 *
 * Two hosts carrying their own `vendor/iniznet/mahout-*` do not fail when they meet
 * in one WordPress process, which is what makes the situation worth a gate. Composer
 * registers each host's autoloader by prepending and `wp-settings.php` includes the
 * theme after every active plugin, so the host that loads last answers every shared
 * class name for both while the other's pinned copies never run. The state is shared
 * with no owner recorded anywhere: the schema version is one fixed option, the field
 * tables are named from the table prefix, the hook namespace is the packages'. Two
 * hosts write it, each believing it owns it, and the first symptom is a field reading
 * back null.
 *
 * Every per-repository gate passes for both hosts while the site is wrong, because the
 * collision is a property of the installation rather than of either root's code. The
 * runtime backstop is mahout-kernel's process claim (its ADR-0007), which refuses the
 * second root at boot; this check is the offline one, and it is the one that can say
 * which tree has to give.
 *
 * A directory that carries the packages is not the same fact as a host that boots. Two
 * starter checkouts sit side by side on a development workstation with only one of them
 * activated, and a gate that called that ordinary state a collision is a gate that gets
 * deleted — so the scan and the loaded set are read separately and compared:
 *
 * - one tree: it is the root of record, and the options table need not be consulted.
 * - several trees, one of them loaded: pass, naming the inert remainder as source-only.
 * - several trees, more than one loaded: fail, naming every bootable host.
 * - several trees, none loaded: warn, because nothing would collide until someone
 *   activates one, and a warning that states the pending decision is not a pass.
 * - several trees and the site's own answers are unreadable: warn. A check cannot tell
 *   is never reported as a check that passed.
 *
 * `mu-plugins` needs no option to load it, so a package tree found there counts as
 * booted: it is an install by definition, which is the conservative side and the correct
 * one for a gate whose failure mode is silent corruption.
 */

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Console\DatabaseCredentials;
use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\ActiveHosts;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

final readonly class CompositionRootCheck implements Check
{
    private const string VENDOR = 'iniznet';

    private const string PACKAGE_PREFIX = 'mahout-';

    private const string THEMES = 'wp-content/themes/';

    private const string PLUGINS = 'wp-content/plugins/';

    private const string MU_PLUGINS = 'wp-content/mu-plugins/';

    /** A host's tree, resolved to the package namespace its autoloader would register. */
    private const array HOST_PATTERNS = [
        'wp-content/plugins/*/vendor/iniznet',
        'wp-content/plugins/*/*/vendor/iniznet',
        'wp-content/mu-plugins/*/vendor/iniznet',
        'wp-content/themes/*/vendor/iniznet',
    ];

    /**
     * @param ActiveHosts|null $measured the loaded hosts, when the caller already has them; a
     *                                   test seam, because a unit test must not need a running
     *                                   database to prove which tree would boot
     */
    public function __construct(
        private string $wordpressRoot,
        private ?ActiveHosts $measured = null,
    ) {
    }

    public function name(): string
    {
        return 'Composition root';
    }

    public function examine(): CheckResult
    {
        $trees = $this->hostTrees();

        if ([] === $trees) {
            return CheckResult::skip(
                $this->name(),
                is_dir($this->wordpressRoot.'/wp-content')
                    ? 'no host under wp-content installs the packages'
                    : 'no wp-content under this root',
            );
        }

        if (1 === count($trees)) {
            return CheckResult::pass($this->name(), $trees[0].' is the root of record; nothing else in this site installs the packages');
        }

        if (null !== $this->measured) {
            $active = $this->measured;
        } else {
            $reason = $this->whyThereIsNoAnswer();

            if (null !== $reason) {
                return CheckResult::warn(
                    $this->name(),
                    sprintf('%d hosts install the packages (%s); %s', count($trees), implode(', ', $trees), $reason),
                );
            }

            $active = $this->readActiveHosts();

            if (null === $active) {
                return CheckResult::warn(
                    $this->name(),
                    sprintf('%d hosts install the packages (%s); the active theme and plugins could not be read from this site, so which host would boot cannot be told', count($trees), implode(', ', $trees)),
                );
            }
        }

        $bootable = [];
        $inert = [];

        foreach ($trees as $tree) {
            if ($this->boots($tree, $active)) {
                $bootable[] = $tree;

                continue;
            }

            $inert[] = $tree;
        }

        if (count($bootable) > 1) {
            return CheckResult::fail(
                $this->name(),
                sprintf(
                    '%d hosts would both boot the packages (%s); one process has one root of record — install them in one host and let the others consume that runtime over hooks or REST',
                    count($bootable),
                    implode(', ', $bootable),
                ),
            );
        }

        if (1 === count($bootable)) {
            return CheckResult::pass(
                $this->name(),
                sprintf(
                    '%s is the root of record; %d source-only tree(s) are not loaded: %s',
                    $bootable[0],
                    count($inert),
                    implode(', ', $inert),
                ),
            );
        }

        return CheckResult::warn(
            $this->name(),
            sprintf(
                '%d hosts install the packages (%s) and none of them is the active theme or an activated plugin, so nothing collides until one is loaded — decide the root of record before activating any of them',
                count($trees),
                implode(', ', $trees),
            ),
        );
    }

    /**
     * Every host tree that carries the packages, relative to the WordPress root.
     *
     * @return list<string>
     */
    private function hostTrees(): array
    {
        if (!is_dir($this->wordpressRoot.'/wp-content')) {
            return [];
        }

        $hosts = [];

        foreach (self::HOST_PATTERNS as $pattern) {
            foreach (glob($this->wordpressRoot.'/'.$pattern) ?: [] as $vendor) {
                if ($this->installsPackages($vendor)) {
                    $hosts[$this->hostOf($vendor)] = true;
                }
            }
        }

        $found = array_keys($hosts);
        sort($found);

        return $found;
    }

    private function installsPackages(string $vendor): bool
    {
        return \array_any(glob($vendor.'/'.self::PACKAGE_PREFIX.'*') ?: [], static fn (string $package): bool => is_dir($package));
    }

    private function hostOf(string $vendor): string
    {
        $relative = str_replace($this->wordpressRoot.'/', '', $vendor);

        return str_replace('/vendor/'.self::VENDOR, '', $relative);
    }

    /**
     * Whether this tree is one WordPress would load. A theme is loaded when it is the
     * active stylesheet; a plugin when its directory is in `active_plugins`; a package
     * tree under `mu-plugins` always.
     */
    private function boots(string $tree, ActiveHosts $active): bool
    {
        if (str_starts_with($tree, self::MU_PLUGINS)) {
            return true;
        }

        if (str_starts_with($tree, self::THEMES)) {
            return $active->stylesheet === substr($tree, strlen(self::THEMES));
        }

        if (str_starts_with($tree, self::PLUGINS)) {
            return \in_array(substr($tree, strlen(self::PLUGINS)), $active->plugins, true);
        }

        return false;
    }

    /**
     * Why the site's own answer cannot be had, or null when it can be asked for.
     *
     * Each of these is a different thing an operator can fix, so each is said in its
     * own words rather than folded into one unknown.
     */
    private function whyThereIsNoAnswer(): ?string
    {
        if (!is_file($this->wordpressRoot.'/wp-config.php')) {
            return 'the site database is not reachable from here, so which host is active cannot be told';
        }

        $credentials = DatabaseCredentials::fromConfigFile($this->wordpressRoot.'/wp-config.php');

        if (null === $credentials) {
            return 'wp-config.php declares no database name, so which host is active cannot be told';
        }

        if (null === $credentials->tablePrefix) {
            return 'wp-config.php declares no table prefix, so the options table cannot be named';
        }

        return null;
    }

    /**
     * The site's own answer, or null when the options table gives none.
     *
     * A failed connect raises a diagnostic or an exception depending on the mysqli
     * report mode in force, exactly as in the buffer-pool check, so both paths end in
     * the same reported answer.
     */
    private function readActiveHosts(): ?ActiveHosts
    {
        $credentials = DatabaseCredentials::fromConfigFile($this->wordpressRoot.'/wp-config.php');

        if (null === $credentials || null === $credentials->tablePrefix) {
            return null;
        }

        try {
            $connection = @new \mysqli($credentials->host, $credentials->user, $credentials->password, $credentials->name, $credentials->port);
        } catch (\mysqli_sql_exception) {
            return null;
        }

        if (0 !== $connection->connect_errno) {
            $connection->close();

            return null;
        }

        $active = ActiveHosts::fromConnection($connection, $credentials->tablePrefix);
        $connection->close();

        return $active;
    }
}
