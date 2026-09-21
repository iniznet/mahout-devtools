<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Console;

/**
 * The paths a command needs, resolved once from the environment.
 *
 * The fallbacks are the Phase 0 harness locations on this workstation; every
 * one is overridable, so CI and a consumer set the environment instead.
 */
final readonly class Paths
{
    public function __construct(
        public string $root,
        public string $wordpressRoot,
        public string $testsDirectory,
        public string $configFile,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $root = dirname(__DIR__, 2);

        return new self(
            $root,
            self::environment('HOWDAH_WP_ROOT') ?? 'F:/laragon/www/modernwp',
            self::environment('WP_TESTS_DIR') ?? 'F:/kerjaan 2/WordPress/libraries/wordpress-develop/tests/phpunit',
            self::environment('WP_TESTS_CONFIG_FILE_PATH') ?? self::defaultConfigFile($root),
        );
    }

    private static function environment(string $name): ?string
    {
        $value = getenv($name);
        if (false === $value || '' === $value) {
            return null;
        }

        return $value;
    }

    private static function defaultConfigFile(string $root): string
    {
        $local = $root.'/tests/wp-tests-config.php';
        if (is_file($local)) {
            return $local;
        }

        return $root.'/tests/wp-tests-config.php.dist';
    }
}
