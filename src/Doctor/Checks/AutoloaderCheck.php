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
 * A deployable host is a theme or a plugin — both ship a map, and the plugin
 * starter is a product in its own right, so treating only themes as deployables
 * left the plugin's map unproven.
 */

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Console\ProcessRunner;
use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;
use Iniznet\Mahout\Devtools\Doctor\DeclaredClasses;

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

        $type = $manifest['type'] ?? null;

        if (!\is_string($type) || !\in_array($type, ['wordpress-theme', 'wordpress-plugin'], true)) {
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

        $problems = [...$problems, ...$this->coverageProblems($autoload)];

        if ([] !== $problems) {
            return CheckResult::fail($this->name(), implode('; ', $problems));
        }

        return CheckResult::pass($this->name(), 'classmap-authoritative, declared, installed and complete');
    }

    /**
     * Whether the installed map covers the classes in the trees it claims to cover.
     *
     * The flag alone was never the point. An authoritative map means the autoloader will
     * not fall back to PSR-4, so a class the map does not list does not exist as far as
     * the site is concerned: `Class "…" not found` on a host whose every per-repository
     * gate had passed perfectly, because a class was added to a symlinked path repository
     * and nobody regenerated the map. That is how both starter suites died during the
     * identity work while this check reported PASS on the strength of the declaration.
     *
     * The directories come from the map's own entries, so the scan covers what the map
     * claims without guessing at layout, restricted to the trees this family installs
     * itself — our packages and the host's own source. A third-party tree autoloaded by
     * PSR-4, or Composer's generated runtime required by name from autoload_real.php, is
     * deliberately not classmapped, and reporting it would turn the gate into noise on
     * every install, which is the fastest way for a gate to be deleted. Scanning whole
     * directories rather than only files the map references is what keeps the hazard in
     * scope: a class added to a symlinked package has no entry anywhere, so a
     * file-level comparison passes the exact state that breaks the site.
     *
     * @return list<string>
     */
    private function coverageProblems(string $autoload): array
    {
        $map = \dirname($autoload).'/composer/autoload_classmap.php';

        if (!is_file($map)) {
            return ['config.classmap-authoritative is declared but vendor/composer/autoload_classmap.php is absent: run composer dump-autoload -o in this host'];
        }

        $entries = require $map;

        if (!\is_array($entries)) {
            return ['vendor/composer/autoload_classmap.php did not return a map'];
        }

        /** @var array<string, true> $roots */
        $roots = [];

        foreach ($entries as $path) {
            if (!\is_string($path)) {
                continue;
            }

            $normalised = str_replace('\\', '/', $path);

            if (str_contains($normalised, '/vendor/composer/')) {
                continue;
            }

            if (str_contains($normalised, '/vendor/') && !str_contains($normalised, '/iniznet/mahout-')) {
                continue;
            }

            $position = strrpos($normalised, '/src/');
            $root = false === $position ? \dirname($normalised) : substr($normalised, 0, $position + 4);

            if (is_dir($root)) {
                $roots[$root] = true;
            }
        }

        $missing = [];

        foreach (array_keys($roots) as $root) {
            foreach (DeclaredClasses::inDirectory((string) $root) as $declared) {
                if (!\array_key_exists($declared, $entries)) {
                    $missing[$declared] = true;
                }
            }
        }

        if ([] === $missing) {
            return [];
        }

        $names = array_keys($missing);
        sort($names);
        $listed = \array_slice($names, 0, 5);

        return [sprintf(
            'the authoritative classmap does not cover %d declared class%s in the trees it claims (%s%s): the map predates the source, so run composer dump-autoload -o in this host',
            count($names),
            1 === count($names) ? '' : 'es',
            implode(', ', $listed),
            count($names) > count($listed) ? ', …' : '',
        )];
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
