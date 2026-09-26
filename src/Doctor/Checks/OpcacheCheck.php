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
 * rather than guessed at.
 */

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

final readonly class OpcacheCheck implements Check
{
    /** The trees a front-end request can load code from. */
    private const array FLOOR_DIRECTORIES = ['wp-includes', 'wp-admin', 'wp-content/plugins', 'wp-content/themes'];

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
        $floor = $this->fileFloor();

        if (null === $floor) {
            $notes[] = 'max_accelerated_files '.$declared.' (no WordPress installation at this root, so the floor is not measurable here)';
        } elseif ($declared < $floor) {
            $problems[] = sprintf(
                'opcache.max_accelerated_files is %d but this installation can load %d PHP files: the table evicts under traffic',
                $declared,
                $floor,
            );
        } else {
            $notes[] = sprintf('max_accelerated_files %d >= %d measured file floor', $declared, $floor);
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
     * The count of PHP files the installation can put on disk for a request to
     * load. `null` when there is no installation here to count.
     */
    private function fileFloor(): ?int
    {
        if ('' === $this->wordpressRoot || !is_dir($this->wordpressRoot)) {
            return null;
        }

        $count = 0;

        foreach (self::FLOOR_DIRECTORIES as $relative) {
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
                return null;
            }
        }

        return $count > 0 ? $count : null;
    }
}
