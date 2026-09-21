<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

/**
 * The WordPress floor is 7.1 (PLT-02). The version is read from the installed
 * core's wp-includes/version.php rather than from a constant, so the check runs
 * outside a booted WordPress.
 */
final readonly class WordPressVersionCheck implements Check
{
    public function __construct(
        private ?string $wordpressRoot,
        private string $minimum = '7.1',
    ) {
    }

    public function name(): string
    {
        return 'WordPress version';
    }

    public function examine(): CheckResult
    {
        if (null === $this->wordpressRoot) {
            return CheckResult::skip($this->name(), 'no WordPress root supplied');
        }

        $versionFile = rtrim($this->wordpressRoot, '/').'/wp-includes/version.php';
        if (!is_file($versionFile)) {
            return CheckResult::skip($this->name(), 'version.php not found at '.$versionFile);
        }

        $version = $this->readVersion($versionFile);
        if (null === $version) {
            return CheckResult::fail($this->name(), 'could not read wp_version from '.$versionFile);
        }

        if (version_compare($version, $this->minimum, '>=')) {
            return CheckResult::pass($this->name(), sprintf('%s >= %s', $version, $this->minimum));
        }

        return CheckResult::fail($this->name(), sprintf('%s < %s', $version, $this->minimum));
    }

    private function readVersion(string $versionFile): ?string
    {
        $contents = file_get_contents($versionFile);
        if (false === $contents) {
            return null;
        }

        $pattern = '/\\$wp_version\\s*=\\s*\'([^\']+)\'/';
        if (1 !== preg_match($pattern, $contents, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
