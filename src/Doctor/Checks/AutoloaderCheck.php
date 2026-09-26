<?php

/**
 * The production autoloader is generated with `--classmap-authoritative`, and
 * `doctor` proves two separate things about it, because they fail differently.
 *
 * The declaration is the durable half: `composer.json` must ask for the
 * optimised, authoritative map, so every install and every release build gets it
 * without anyone remembering a flag. The installed autoloader is the observable
 * half: the map is inspected in a child process, because asking the running
 * process would mean loading the site's own autoloader into the tool that is
 * checking it — and a classmap-authoritative map that disagrees with this
 * process's is exactly the kind of state a diagnostic should catch, not join.
 *
 * A library root is asserted to have an autoloader and nothing more: the shape
 * of a consumer's map is the consumer's business, and Composer itself says so.
 */

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Console\ProcessRunner;
use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

final readonly class AutoloaderCheck implements Check
{
    public function __construct(
        private string $root,
        private ProcessRunner $runner,
    ) {
    }

    public function name(): string
    {
        return 'Composer autoloader';
    }

    public function examine(): CheckResult
    {
        $autoload = $this->root.'/vendor/autoload.php';

        if (!is_file($autoload)) {
            return CheckResult::fail($this->name(), 'vendor/autoload.php missing; run composer install');
        }

        $manifest = $this->manifest();

        if (null === $manifest) {
            return CheckResult::fail($this->name(), 'composer.json is missing or unreadable beside the installed autoloader');
        }

        if ('wordpress-theme' !== ($manifest['type'] ?? null)) {
            return CheckResult::pass($this->name(), 'vendor/autoload.php present; a library map is the consumer\'s shape');
        }

        $problems = [];
        $config = \is_array($manifest['config'] ?? null) ? $manifest['config'] : [];

        if (true !== ($config['optimize-autoloader'] ?? null)) {
            $problems[] = 'composer.json does not declare config.optimize-autoloader';
        }

        if (true !== ($config['classmap-authoritative'] ?? null)) {
            $problems[] = 'composer.json does not declare config.classmap-authoritative';
        }

        $probe = $this->runner->capture(
            [PHP_BINARY, '-r', '$loader = require '.var_export($autoload, true).'; echo $loader instanceof \Composer\Autoload\ClassLoader && $loader->isClassMapAuthoritative() ? "authoritative" : "devolved";'],
            $this->root,
        );
        $code = $probe['code'];
        $output = $probe['output'];

        if (0 !== $code) {
            $problems[] = 'the installed autoloader could not be inspected (exit '.$code.'): '.$output;
        } elseif ('authoritative' !== $output) {
            $problems[] = 'the installed autoloader is '.$output.': the deployable must be built with --classmap-authoritative, or config.classmap-authoritative set so every install is';
        }

        if ([] !== $problems) {
            return CheckResult::fail($this->name(), implode('; ', $problems));
        }

        return CheckResult::pass($this->name(), 'classmap-authoritative, declared and installed');
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function manifest(): ?array
    {
        $path = $this->root.'/composer.json';

        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return \is_array($decoded) ? $decoded : null;
    }
}
