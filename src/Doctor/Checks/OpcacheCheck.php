<?php

/**
 * The opcode cache is a declared production prerequisite: the contract states
 * plainly that none of the capacity numbers hold without it, so it is asserted
 * rather than assumed.
 *
 * The check reads the directives through the SAPI running `doctor` and says so
 * where that is not the SAPI serving traffic. Two of them are treated
 * differently, and the difference is deliberate:
 *
 * - `opcache.enable` off is wrong wherever it is read, because it means no
 *   compiled bytecode is kept at all; that fails.
 * - `validate_timestamps` on is correct on a development box and wrong in a
 *   production one. Which one this is comes from the installation's own
 *   declaration, `WP_ENVIRONMENT_TYPE`: declared production fails, anything else
 *   warns with the remedy in the same line, and an installation that declares
 *   nothing is told so — because a site that cannot be distinguished from
 *   production from outside is also a site whose cache policy nobody can verify.
 *   A gate that fails a developer for developing is a gate that gets deleted.
 *
 * `opcache.memory_consumption` joins the production-only set at 256 MB, the
 * figure the throughput model carries for a core-plus-theme-plus-packages tree.
 *
 * `opcache.max_accelerated_files` is a hash-table size, and a table too small
 * for the installation evicts under traffic — which presents as "the theme is
 * slow" while the theme is innocent. The floor is counted from this installation
 * rather than guessed at, and it is counted over the code this installation can
 * put on a request path: core, must-use plugins, the active theme and the active
 * plugins. A workstation with three starter checkouts side by side is not an
 * installation that can load all three, and a floor that said it were would fail
 * the gate for a fact about the developer's disk rather than about the site —
 * which is how a gate stops being read.
 *
 * Reading the active set costs one options query, and it is the same query the
 * composition-root check already makes; where the database is unreachable the
 * census falls back to every host directory it can see and says so in the line,
 * because a pessimistic number is a worse floor to be told than an absent one
 * but it must never be mistaken for the exact reading.
 */

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Console\DatabaseCredentials;
use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\ActiveHosts;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

final readonly class OpcacheCheck implements Check
{
    /**
     * Code a request can reach whatever the site has activated: core, and everything
     * in the must-use directory, which has no option recording it because there is
     * nothing to record.
     */
    private const array CORE_DIRECTORIES = ['wp-includes', 'wp-admin', 'wp-content/mu-plugins'];

    /**
     * The host trees counted whole when the active set cannot be read. A guess that
     * over-counts is the safe direction for a floor; the line says which reading ran.
     */
    private const array HOST_DIRECTORIES = ['wp-content/plugins', 'wp-content/themes'];

    /** The pool a production install is declared to need; below it the cache thrashes. */
    private const int MINIMUM_MEMORY_MB = 256;

    /**
     * @param array<string, string|false> $settings        the directives as this SAPI reads them
     * @param string|null                 $environmentType the type the installation declares, or null when it declares none
     */
    public function __construct(
        private string $wordpressRoot,
        private array $settings,
        private bool $extensionLoaded,
        private ?string $environmentType = null,
        private ?ActiveHosts $hosts = null,
    ) {
    }

    public function name(): string
    {
        return 'Opcode cache';
    }

    public function examine(): CheckResult
    {
        if (!$this->extensionLoaded) {
            return CheckResult::warn(
                $this->name(),
                'Zend OPcache is not loaded in the SAPI running doctor; the pool that serves traffic is the one that must be checked',
            );
        }

        if (!$this->enabled('opcache.enable')) {
            return CheckResult::fail(
                $this->name(),
                'opcache.enable is off: every request recompiles every file, and the declared capacity numbers do not hold',
            );
        }

        $problems = [];
        $notes = ['enable on'];
        $fresh = $this->enabled('opcache.validate_timestamps');
        $production = 'production' === $this->environmentType;

        if ($production && $fresh) {
            // Declared production is the only place this is a defect rather than a
            // preference: with validation on, every included file is stat()ed on the
            // revalidate interval, which is a cost proportional to traffic.
            $problems[] = 'opcache.validate_timestamps is on in a production installation; set it to 0 and reset the cache once after deploy';
        } elseif ($fresh) {
            $notes[] = 'validate_timestamps on (right for development; production sets it to 0 and resets the cache once after deploy)';
        } else {
            $notes[] = 'validate_timestamps off';
        }

        if ($production) {
            $declaredMemory = $this->settings['opcache.memory_consumption'] ?? null;

            if (null === $declaredMemory || '' === $declaredMemory) {
                // Unreadable is not the same finding as too small, and reporting one
                // as the other would send someone to the wrong file.
                $notes[] = 'opcache.memory_consumption is not readable from this SAPI, so the production floor is not being asserted';
            } elseif ((int) $declaredMemory < self::MINIMUM_MEMORY_MB) {
                $problems[] = sprintf('opcache.memory_consumption is %d MB; a production install needs at least %d MB for core, the theme and its packages', (int) $declaredMemory, self::MINIMUM_MEMORY_MB);
            }

            $notes[] = 'declared environment production';
        } elseif (null === $this->environmentType) {
            $notes[] = 'no WP_ENVIRONMENT_TYPE declared, so the production-only assertions below are not being made';
        } else {
            $notes[] = 'declared environment '.$this->environmentType;
        }

        $realpath = (int) preg_replace('/[^0-9]/', '', (string) ($this->settings['realpath_cache_size'] ?? '0'));

        if ($realpath > 0 && $realpath < 4096) {
            $notes[] = 'realpath_cache_size '.$realpath.' KB is under the 4096 KB the file count wants';
        }

        $declared = (int) ($this->settings['opcache.max_accelerated_files'] ?? 0);
        [$floor, $bounded] = $this->fileFloor();

        if (null === $floor) {
            $notes[] = 'max_accelerated_files '.$declared.' (no WordPress installation at this root, so the floor is not measurable here)';
        } elseif ($declared < $floor) {
            $problems[] = sprintf(
                'opcache.max_accelerated_files is %d but this installation can load %d PHP files%s: the table evicts under traffic',
                $declared,
                $floor,
                $bounded ? ' (the active host is unknown, so every theme and plugin on disk was counted)' : ' across core, must-use code and the active host',
            );
        } else {
            $notes[] = sprintf(
                'max_accelerated_files %d >= %d measured file floor%s',
                $declared,
                $floor,
                $bounded ? ' (pessimistic: the active host is unknown, so every theme and plugin on disk was counted)' : ' (active host only)',
            );
        }

        if ([] !== $problems) {
            return CheckResult::fail($this->name(), implode('; ', $problems));
        }

        $detail = implode('; ', $notes);

        return $fresh && !$production
            ? CheckResult::warn($this->name(), $detail)
            : CheckResult::pass($this->name(), $detail);
    }

    private function enabled(string $directive): bool
    {
        $value = $this->settings[$directive] ?? '';

        return !\in_array((string) $value, ['', '0', 'Off', 'off', 'false', 'FALSE', 'No', 'no'], true);
    }

    /**
     * The count of PHP files the installation can put on a request path, and whether
     * that number is the bounded reading or the fallback that counts every host on
     * disk. `null` when there is no installation here to count.
     *
     * @return array{int|null, bool}
     */
    private function fileFloor(): array
    {
        if ('' === $this->wordpressRoot || !is_dir($this->wordpressRoot)) {
            return [null, false];
        }

        [$directories, $bounded] = $this->loadedTrees();
        $count = 0;

        foreach ($directories as $relative) {
            $directory = $this->wordpressRoot.'/'.$relative;

            if (!is_dir($directory)) {
                continue;
            }

            try {
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

                foreach ($files as $file) {
                    if ($file instanceof \SplFileInfo && 'php' === strtolower($file->getExtension())) {
                        ++$count;
                    }
                }
            } catch (\UnexpectedValueException) {
                return [null, $bounded];
            }
        }

        return [$count > 0 ? $count : null, $bounded];
    }

    /**
     * The trees a request can load code from: core and must-use always, and the hosts
     * the site reports as activated. When the options table gives no answer, every
     * host directory is counted and the caller is told the reading was the pessimistic
     * one rather than left to assume it was the exact one.
     *
     * @return array{list<string>, bool}
     */
    private function loadedTrees(): array
    {
        $hosts = $this->hosts ?? $this->readActiveHosts();

        if (null === $hosts) {
            return [[...self::CORE_DIRECTORIES, ...self::HOST_DIRECTORIES], true];
        }

        $directories = self::CORE_DIRECTORIES;

        if (null !== $hosts->stylesheet) {
            $directories[] = 'wp-content/themes/'.$hosts->stylesheet;
        }

        foreach ($hosts->plugins as $plugin) {
            $directories[] = 'wp-content/plugins/'.$plugin;
        }

        return [$directories, false];
    }

    /**
     * The site's own answer, or null when the options table gives none.
     *
     * A failed connect raises a diagnostic or an exception depending on the mysqli
     * report mode in force, exactly as in the composition-root check, so both paths
     * end in the same reported answer.
     */
    private function readActiveHosts(): ?ActiveHosts
    {
        $config = $this->wordpressRoot.'/wp-config.php';

        if (!is_file($config)) {
            return null;
        }

        $credentials = DatabaseCredentials::fromConfigFile($config);

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
