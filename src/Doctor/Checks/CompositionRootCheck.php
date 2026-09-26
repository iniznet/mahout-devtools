<?php

/**
 * The packages are installed by exactly one host.
 *
 * Two hosts carrying their own `vendor/iniznet/mahout-*` do not fail when they meet
 * in one WordPress process, which is what makes the situation worth a gate.
 * Composer registers each host's autoloader by prepending, so the host that loads
 * last — the theme, which WordPress includes after every active plugin — answers
 * every shared class name for both, and the other host's pinned copies never run at
 * all. The state, though, is shared with no owner recorded anywhere: the schema
 * version is one fixed option, the field value tables are named from the table
 * prefix, the hook namespace is the packages'. Two hosts write it, each believing it
 * owns it, and the first symptom is a field that reads back null.
 *
 * Every per-repository gate passes for both hosts while the site is wrong, because
 * the collision is a property of the *installation*, not of either root's code. So
 * it is asserted here, offline, before anything boots. The runtime backstop is
 * mahout-kernel's process claim (its ADR-0007), which refuses the second root at
 * boot; this check is the one that names both hosts while they can still be moved.
 *
 * The scan is bounded to the trees WordPress loads a host from, to the depth it
 * supports — plugins may nest one level deeper than themes — and it never walks the
 * filesystem freely: four patterns, each ending at the vendor namespace.
 */

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

final readonly class CompositionRootCheck implements Check
{
    private const string VENDOR = 'iniznet';

    private const string PACKAGE_PREFIX = 'mahout-';

    /** A host's tree, resolved to the package namespace its autoloader would register. */
    private const array HOST_PATTERNS = [
        'wp-content/plugins/*/vendor/iniznet',
        'wp-content/plugins/*/*/vendor/iniznet',
        'wp-content/mu-plugins/*/vendor/iniznet',
        'wp-content/themes/*/vendor/iniznet',
    ];

    public function __construct(private string $wordpressRoot)
    {
    }

    public function name(): string
    {
        return 'Composition root';
    }

    public function examine(): CheckResult
    {
        if (!is_dir($this->wordpressRoot.'/wp-content')) {
            return CheckResult::skip($this->name(), 'no wp-content under this root');
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

        if ([] === $found) {
            return CheckResult::skip($this->name(), 'no host under wp-content installs the packages');
        }

        if (1 === count($found)) {
            return CheckResult::pass($this->name(), $found[0].' is the root of record');
        }

        return CheckResult::fail(
            $this->name(),
            sprintf(
                '%d hosts install the packages (%s); one process has one root of record — install them in one host and let the others consume that runtime over hooks or REST',
                count($found),
                implode(', ', $found),
            ),
        );
    }

    private function installsPackages(string $vendor): bool
    {
        return array_any(glob($vendor.'/'.self::PACKAGE_PREFIX.'*') ?: [], fn ($package) => is_dir($package));
    }

    private function hostOf(string $vendor): string
    {
        $relative = str_replace($this->wordpressRoot.'/', '', $vendor);

        return str_replace('/vendor/'.self::VENDOR, '', $relative);
    }
}
