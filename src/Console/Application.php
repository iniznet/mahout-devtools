<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Console;

use Iniznet\Mahout\Devtools\Doctor\Checks\AnalyzerDivergenceCheck;
use Iniznet\Mahout\Devtools\Doctor\Checks\ArchitectureRuleCatalogCheck;
use Iniznet\Mahout\Devtools\Doctor\Checks\AutoloaderCheck;
use Iniznet\Mahout\Devtools\Doctor\Checks\PathRepositoryCheck;
use Iniznet\Mahout\Devtools\Doctor\Checks\PhpExtensionCheck;
use Iniznet\Mahout\Devtools\Doctor\Checks\PhpVersionCheck;
use Iniznet\Mahout\Devtools\Doctor\Checks\RequiredFilesCheck;
use Iniznet\Mahout\Devtools\Doctor\Checks\WordPressVersionCheck;
use Iniznet\Mahout\Devtools\Doctor\Checks\WorkflowSafetyCheck;
use Iniznet\Mahout\Devtools\Doctor\Doctor;
use Iniznet\Mahout\Devtools\Doctor\DoctorReport;
use Iniznet\Mahout\Devtools\Exception\DevtoolsException;
use Iniznet\Mahout\Devtools\Exception\InvalidInvocation;
use Iniznet\Mahout\Devtools\Exception\StubGenerationFailed;
use Iniznet\Mahout\Devtools\Generator\GeneratedReference;
use Iniznet\Mahout\Devtools\Generator\Hooks\HooksReference;
use Iniznet\Mahout\Devtools\Generator\ReferenceGate;
use Iniznet\Mahout\Devtools\Generator\SourceFiles;
use Iniznet\Mahout\Devtools\Generator\Translations\TranslationsReference;

/**
 * The mahout-devtools command line.
 *
 * One entry point, one dispatch table. Every command is greppable from here;
 * nothing is registered at file scope.
 */
final readonly class Application
{
    /**
     * The artifact set every repository carries.
     *
     * @var list<string>
     */
    private const array ARTIFACTS = [
        'LICENSE',
        'README.md',
        'CHANGELOG.md',
        'CONTRIBUTING.md',
        'CODE_OF_CONDUCT.md',
        'SECURITY.md',
        'AGENTS.md',
        'composer.json',
        '.github/ISSUE_TEMPLATE/bug_report.yml',
        '.github/ISSUE_TEMPLATE/feature_request.yml',
        '.github/ISSUE_TEMPLATE/config.yml',
        '.github/pull_request_template.md',
        '.github/workflows/quality.yml',
    ];

    /**
     * The four shared analyzer configuration files.
     *
     * @var list<string>
     */
    private const array ANALYZER_FILES = [
        'phpstan.neon',
        'psalm.xml',
        'rector.php',
        '.php-cs-fixer.dist.php',
    ];

    public function __construct(
        private Paths $paths,
        private ProcessRunner $runner,
    ) {
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        $command = $arguments[0] ?? '';
        $options = $this->options($arguments);

        try {
            return match ($command) {
                'doctor' => $this->doctor(),
                'config:check' => $this->configCheck($options),
                'stubs:generate' => $this->stubsGenerate(),
                'stubs:check' => $this->stubsCheck(),
                'test' => $this->test(),
                'arch' => $this->arch(),
                'i18n:check' => $this->translationGate($options, false),
                'i18n:generate' => $this->translationGate($options, true),
                'hooks:check' => $this->hookGate($options, false),
                'hooks:generate' => $this->hookGate($options, true),
                'help', '--help', '-h' => $this->help(),
                default => $this->unknown($command),
            };
        } catch (DevtoolsException $exception) {
            fwrite(STDERR, 'FAIL: '.$exception->getMessage().PHP_EOL);

            return 1;
        }
    }

    private function doctor(): int
    {
        $checks = [
            new PhpVersionCheck(),
            new PhpExtensionCheck(['json', 'hash', 'mysqli']),
            new WordPressVersionCheck($this->paths->wordpressRoot),
            new AutoloaderCheck($this->paths->root),
            new RequiredFilesCheck($this->paths->root, 'Stub file', ['stubs/wordpress-stubs.php']),
            new RequiredFilesCheck($this->paths->root, 'Analyzer configuration', self::ANALYZER_FILES),
            new RequiredFilesCheck($this->paths->root, 'Artifact set', self::ARTIFACTS),
            new RequiredFilesCheck($this->paths->root, 'Test bootstrap', ['tests/bootstrap.php']),
            new PathRepositoryCheck($this->paths->root),
            new WorkflowSafetyCheck($this->paths->root),
        ];

        return $this->report(new Doctor($checks)->examine());
    }

    /**
     * @param array<string, string> $options
     */
    private function configCheck(array $options): int
    {
        $root = $options['root'] ?? $this->cwd();

        $checks = [
            new RequiredFilesCheck($root, 'Artifact set', self::ARTIFACTS),
            new RequiredFilesCheck($root, 'Analyzer configuration', self::ANALYZER_FILES),
            new PathRepositoryCheck($root),
            new WorkflowSafetyCheck($root),
        ];

        if ($this->isProducer($root)) {
            $checks[] = new RequiredFilesCheck($root, 'Shared stubs', ['stubs/wordpress-stubs.php']);
            $checks[] = new ArchitectureRuleCatalogCheck($root);
        } else {
            $checks[] = new AnalyzerDivergenceCheck($root);
        }

        return $this->report(new Doctor($checks)->examine());
    }

    private function stubsGenerate(): int
    {
        return $this->runner->run(
            [PHP_BINARY, $this->paths->root.'/bin/generate-stubs.php', $this->paths->root.'/stubs/wordpress-stubs.php'],
            $this->paths->root,
            $this->stubEnvironment(),
        );
    }

    private function stubsCheck(): int
    {
        $committed = $this->paths->root.'/stubs/wordpress-stubs.php';
        if (!is_file($committed)) {
            fwrite(STDERR, 'FAIL: stubs/wordpress-stubs.php is missing; run composer stubs:generate'.PHP_EOL);

            return 1;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'mahout-stubs-');
        if (false === $temporary) {
            throw StubGenerationFailed::because('could not create a temporary file');
        }

        $exitCode = $this->runner->run(
            [PHP_BINARY, $this->paths->root.'/bin/generate-stubs.php', $temporary],
            $this->paths->root,
            $this->stubEnvironment(),
        );

        if (0 !== $exitCode) {
            @unlink($temporary);

            return $exitCode;
        }

        $committedHash = hash_file('sha256', $committed);
        $generatedHash = hash_file('sha256', $temporary);
        @unlink($temporary);

        if (false === $committedHash || false === $generatedHash) {
            fwrite(STDERR, 'FAIL: could not hash the stub files'.PHP_EOL);

            return 1;
        }

        if ($committedHash === $generatedHash) {
            echo 'PASS: stubs are current (regeneration is byte-identical)'.PHP_EOL;

            return 0;
        }

        fwrite(STDERR, 'FAIL: stubs are stale; run composer stubs:generate'.PHP_EOL);

        return 1;
    }

    private function test(): int
    {
        $phpunit = $this->paths->root.'/vendor/phpunit/phpunit/phpunit';
        if (!is_file($phpunit)) {
            fwrite(STDERR, 'FAIL: phpunit is not installed; run composer install'.PHP_EOL);

            return 1;
        }

        $environment = [
            'WP_TESTS_DIR' => $this->paths->testsDirectory,
            'WP_TESTS_CONFIG_FILE_PATH' => $this->paths->configFile,
        ];

        $siteDatabase = getenv('HOWDAH_SITE_DB');
        if (false === $siteDatabase || '' === $siteDatabase) {
            $siteDatabase = 'modernwp';
        }

        $snapshot = fn (): string => trim($this->runner->capture(
            [PHP_BINARY, $this->paths->root.'/bin/database-snapshot.php', $this->paths->configFile, $siteDatabase],
            $this->paths->root,
        )['output']);

        $before = $snapshot();
        $exitCode = $this->runner->run([PHP_BINARY, $phpunit, '-c', 'phpunit.xml.dist'], $this->paths->root, $environment);
        $after = $snapshot();

        $unchanged = '' !== $before && $before === $after;
        printf(
            'Site database %s unchanged: %s (tables before=%s after=%s)%s',
            $siteDatabase,
            $unchanged ? 'PASS' : 'FAIL',
            $before,
            $after,
            PHP_EOL,
        );

        return $unchanged ? $exitCode : 1;
    }

    private function arch(): int
    {
        $phpstan = $this->paths->root.'/vendor/phpstan/phpstan/phpstan';
        if (!is_file($phpstan)) {
            fwrite(STDERR, 'FAIL: PHPStan is not installed; run composer install'.PHP_EOL);

            return 1;
        }

        return $this->runner->run(
            [PHP_BINARY, $phpstan, 'analyse', '-c', 'phpstan-arch.neon', '--no-progress', '--memory-limit=1G'],
            $this->paths->root,
        );
    }

    /**
     * @param array<string, string> $options
     */
    private function translationGate(array $options, bool $write): int
    {
        $reference = new TranslationsReference(
            $this->requiredOption($options, 'domain'),
            new SourceFiles($this->sourceRoots($options), $this->cwd()),
        );

        return $this->referenceGate($reference, $this->requiredOption($options, 'output'), $write);
    }

    /**
     * @param array<string, string> $options
     */
    private function hookGate(array $options, bool $write): int
    {
        $reference = new HooksReference(new SourceFiles($this->sourceRoots($options), $this->cwd()));

        return $this->referenceGate($reference, $this->requiredOption($options, 'output'), $write);
    }

    private function referenceGate(GeneratedReference $reference, string $output, bool $write): int
    {
        $gate = new ReferenceGate($reference, $output);

        if ($write) {
            if (!$gate->write()) {
                fwrite(STDERR, 'FAIL: could not write '.$output.PHP_EOL);

                return 1;
            }

            echo 'WROTE: '.$output.' ('.$reference->kind().')'.PHP_EOL;

            return 0;
        }

        if (!$gate->isCurrent()) {
            fwrite(STDERR, 'FAIL: the '.$reference->kind().' is stale; run the matching :generate command'.PHP_EOL);
            fwrite(STDERR, '      '.$gate->firstDifference().PHP_EOL);

            return 1;
        }

        echo 'PASS: the '.$reference->kind().' is current'.PHP_EOL;

        return 0;
    }

    private function help(): int
    {
        echo 'mahout-devtools '.implode(' ', [
            'doctor',
            'config:check',
            'stubs:generate',
            'stubs:check',
            'test',
            'arch',
            'i18n:check',
            'i18n:generate',
            'hooks:check',
            'hooks:generate',
        ]).PHP_EOL;

        return 0;
    }

    private function unknown(string $command): int
    {
        fwrite(STDERR, sprintf('Unknown command: %s%s', '' === $command ? '(none)' : $command, PHP_EOL));
        fwrite(STDERR, 'Usage: mahout-devtools {doctor|config:check|stubs:generate|stubs:check|test|arch|i18n:check|i18n:generate|hooks:check|hooks:generate}'.PHP_EOL);

        return 2;
    }

    private function report(DoctorReport $report): int
    {
        echo $report->toTable();

        return $report->exitCode();
    }

    /**
     * @return array<string,string>
     */
    private function stubEnvironment(): array
    {
        return [
            'MAHOUT_WP_ROOT' => $this->paths->wordpressRoot,
            'WP_TESTS_DIR' => $this->paths->testsDirectory,
            'WP_TESTS_CONFIG_FILE_PATH' => $this->paths->configFile,
        ];
    }

    /**
     * @param list<string> $arguments
     *
     * @return array<string, string>
     */
    private function options(array $arguments): array
    {
        $options = [];
        foreach (\array_slice($arguments, 1) as $argument) {
            if (!str_starts_with($argument, '--')) {
                continue;
            }

            $pair = substr($argument, 2);
            $separator = strpos($pair, '=');
            if (false === $separator) {
                $options[$pair] = '';
                continue;
            }

            $options[substr($pair, 0, $separator)] = substr($pair, $separator + 1);
        }

        return $options;
    }

    /**
     * @param array<string, string> $options
     */
    private function requiredOption(array $options, string $name): string
    {
        $value = $options[$name] ?? '';
        if ('' === $value) {
            throw InvalidInvocation::missingOption($name);
        }

        return $value;
    }

    /**
     * @param array<string, string> $options
     *
     * @return list<string>
     */
    private function sourceRoots(array $options): array
    {
        $raw = $this->requiredOption($options, 'source');
        $roots = [];
        foreach (explode(',', $raw) as $root) {
            $root = trim($root);
            if ('' === $root) {
                continue;
            }
            if (!is_file($root) && !is_dir($root)) {
                throw InvalidInvocation::unknownSource($root);
            }
            $roots[] = $root;
        }

        if ([] === $roots) {
            throw InvalidInvocation::unknownSource($raw);
        }

        return $roots;
    }

    private function isProducer(string $root): bool
    {
        $manifest = $root.'/composer.json';
        if (!is_file($manifest)) {
            return false;
        }

        $decoded = json_decode((string) file_get_contents($manifest), true);

        return \is_array($decoded) && 'iniznet/mahout-devtools' === ($decoded['name'] ?? null);
    }

    private function cwd(): string
    {
        $cwd = getcwd();

        return false === $cwd ? $this->paths->root : $cwd;
    }
}
