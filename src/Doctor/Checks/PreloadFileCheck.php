<?php

/**
 * A preload file is what makes the first request after a pool restart cost the
 * same as the thousandth. Without it, `opcache.preload` has nothing to compile
 * and the cold request pays the whole compile bill, which is the one moment the
 * declared capacity is most likely to be tested.
 *
 * The script is executed in a child process, as PHP itself will execute it, so a
 * preload that fatals is caught here rather than at pool start. Nothing is
 * loaded into this process: a diagnostic that boots the thing it measures has
 * stopped measuring.
 */

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Console\ProcessRunner;
use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

final readonly class PreloadFileCheck implements Check
{
    public const string FILENAME = 'preload.php';

    /**
     * @param string|null $configured the `opcache.preload` directive as this SAPI reads it
     */
    public function __construct(
        private string $root,
        private ProcessRunner $runner,
        private ?string $configured = null,
    ) {
    }

    public function name(): string
    {
        return 'Preload file';
    }

    public function examine(): CheckResult
    {
        if (!$this->isDeployable()) {
            return CheckResult::skip($this->name(), 'not a deployable theme root; a library ships no preload set');
        }

        $file = $this->root.'/'.self::FILENAME;

        if (!is_file($file)) {
            return CheckResult::fail($this->name(), 'no '.self::FILENAME.'; opcache.preload has nothing to compile, so the first request after a pool restart pays the full compile cost');
        }

        // Syntax first, on every platform: a file that does not parse is broken
        // everywhere, and on Windows nothing below can reach that judgement.
        $parsed = $this->runner->capture([PHP_BINARY, '-l', $file], $this->root);

        if (0 !== $parsed['code']) {
            return CheckResult::fail($this->name(), 'the preload script does not parse: '.$parsed['output']);
        }

        if (!$this->isWired()) {
            // A set that nothing points at buys nothing: the file exists, and the pool
            // still compiles everything on its first request. Reported rather than
            // failed because a Windows pool cannot set the directive at all, and the
            // remedy differs by platform.
            return CheckResult::warn($this->name(), self::FILENAME.' is present but opcache.preload does not name it ('.('' === (string) $this->configured ? 'unset' : (string) $this->configured).'); the warm-up will not happen until the pool points at it');
        }

        if ('Windows' === \PHP_OS_FAMILY) {
            // There is no preload to exercise: PHP refuses opcache.preload at startup
            // on this platform. A diagnostic reporting what the machine can actually
            // answer is not the environment-dependent behaviour switch the contract
            // forbids — that forbids the *theme* changing shape per layer. Pretending
            // the pool start had been verified would be the silent half.
            return CheckResult::warn($this->name(), self::FILENAME.' parses; preloading is unavailable on Windows, so pool start cannot be exercised from this machine');
        }

        $probe = $this->runner->capture(
            [PHP_BINARY, '-d', 'opcache.enable_cli=1', '-d', 'opcache.preload='.$file, '-r', 'echo 1;'],
            $this->root,
        );

        if (0 !== $probe['code']) {
            return CheckResult::fail($this->name(), 'the preload script fails at pool start (exit '.$probe['code'].'): '.$probe['output']);
        }

        return CheckResult::pass($this->name(), self::FILENAME.' is the configured preload set and preloads in a child process');
    }

    /**
     * The directive either names this file or names nothing; an unset directive is
     * not wired, and a path ending in the same filename is treated as this file, since
     * the deployment root `doctor` sees need not be the path the pool was given.
     */
    private function isWired(): bool
    {
        return '' !== trim((string) $this->configured) && str_ends_with(trim((string) $this->configured), '/'.self::FILENAME);
    }

    private function isDeployable(): bool
    {
        $manifest = $this->root.'/composer.json';

        if (!is_file($manifest)) {
            return false;
        }

        $decoded = json_decode((string) file_get_contents($manifest), true);

        return \is_array($decoded) && 'wordpress-theme' === ($decoded['type'] ?? null);
    }
}
