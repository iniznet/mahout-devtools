<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Tests\Architecture;

use Iniznet\Mahout\Devtools\Rules\ArchitectureRule;
use Iniznet\Mahout\Devtools\Tests\TestCase;

/**
 * An architecture rule is only real if it fires on a violation and stays quiet
 * on the nearest legal neighbour. Every registered rule owns a positive fixture
 * that must emit exactly its identifier and a negative fixture that must emit
 * none, asserted in one PHPStan run over the fixture tree.
 *
 * @internal
 */
final class ArchitectureRulesTest extends TestCase
{
    private const FIXTURES = 'fixtures/architecture';

    /**
     * Rule short name => positive fixture path, when it is not violations/<name>.php.
     *
     * @var array<string, string>
     */
    private const POSITIVE_OVERRIDES = [
        'NoPathRepositoryRule' => 'path-repository/NoPathRepositoryRule.php',
    ];

    public function testEveryRegisteredRuleDeclaresAnIdentifierAndOwnsBothFixtures(): void
    {
        $rules = $this->registeredRules();

        self::assertNotEmpty($rules, 'No architecture rules are registered in phpstan.neon');

        foreach ($rules as $rule) {
            $reflection = new \ReflectionClass($rule);
            self::assertTrue(
                $reflection->implementsInterface(ArchitectureRule::class),
                $rule.' is registered but does not implement ArchitectureRule',
            );

            $identifier = $reflection->getConstant('IDENTIFIER');
            self::assertIsString($identifier, $rule.' declares no IDENTIFIER');
            self::assertStringStartsWith('mahout.arch.', $identifier);

            $short = $reflection->getShortName();

            $positive = $this->positiveFixture($short);
            self::assertFileExists($positive, 'Missing positive fixture for '.$short);
            self::assertStringContainsString($identifier, (string) file_get_contents($positive));

            $negative = $this->fixturePath('clean/'.$short.'.php');
            self::assertFileExists($negative, 'Missing negative fixture for '.$short);
            self::assertStringContainsString('// EXPECT-NONE', (string) file_get_contents($negative));
        }
    }

    public function testFixturesEmitExactlyTheDeclaredIdentifiers(): void
    {
        $observed = $this->analyseFixtures();

        foreach ($this->expectedByFile() as $file => $expected) {
            $actual = $observed[$file] ?? [];
            sort($expected);
            sort($actual);
            self::assertSame($expected, $actual, 'Unexpected architecture identifiers in '.$file);
        }
    }

    /**
     * @return list<class-string<ArchitectureRule>>
     */
    private function registeredRules(): array
    {
        $neon = (string) file_get_contents($this->rootPath().'/phpstan.neon');
        preg_match_all('/Iniznet\\\\Mahout\\\\Devtools\\\\Rules\\\\([A-Za-z0-9_]+Rule)/', $neon, $matches);

        $rules = [];
        foreach (array_unique($matches[1]) as $short) {
            $rules[] = 'Iniznet\\Mahout\\Devtools\\Rules\\'.$short;
        }

        sort($rules);

        return $rules;
    }

    /**
     * @return array<string, list<string>>
     */
    private function expectedByFile(): array
    {
        $expected = [];

        foreach (glob($this->fixturePath('violations/*.php')) ?: [] as $file) {
            $expected[$this->normalise($file)] = $this->declaredIdentifiers($file);
        }

        foreach (self::POSITIVE_OVERRIDES as $relative) {
            $path = $this->fixturePath($relative);
            $expected[$this->normalise($path)] = $this->declaredIdentifiers($path);
        }

        foreach (glob($this->fixturePath('clean/*.php')) ?: [] as $file) {
            $expected[$this->normalise($file)] = [];
        }

        return $expected;
    }

    /**
     * @return list<string>
     */
    private function declaredIdentifiers(string $file): array
    {
        preg_match_all('/\/\/ EXPECT:\s*([^\n]+)/', (string) file_get_contents($file), $matches);

        $identifiers = [];
        foreach ($matches[1] as $line) {
            foreach (explode(',', $line) as $identifier) {
                $identifier = trim($identifier);
                if ('' !== $identifier) {
                    $identifiers[] = $identifier;
                }
            }
        }

        return array_values(array_unique($identifiers));
    }

    /**
     * @return array<string, list<string>>
     */
    private function analyseFixtures(): array
    {
        $root = $this->rootPath();
        $phpstan = $root.'/vendor/phpstan/phpstan/phpstan';
        self::assertFileExists($phpstan, 'PHPStan is not installed');

        $this->clearDirectory($root.'/.phpstan-fixtures-cache');

        $output = $this->runPhpstan(
            [
                PHP_BINARY,
                $phpstan,
                'analyse',
                '-c',
                'phpstan-arch-fixtures.neon',
                '--no-progress',
                '--error-format=json',
                '--memory-limit=1G',
            ],
            $root,
        );

        $decoded = json_decode($output, true);
        self::assertIsArray($decoded, 'PHPStan produced no JSON report: '.$output);

        $observed = [];
        /** @var array<string, array{messages?: list<array{identifier?: string|null}>}> $files */
        $files = is_array($decoded['files'] ?? null) ? $decoded['files'] : [];
        foreach ($files as $path => $report) {
            $identifiers = [];
            foreach ($report['messages'] ?? [] as $message) {
                $identifier = $message['identifier'] ?? null;
                if (is_string($identifier) && str_starts_with($identifier, 'mahout.arch.')) {
                    $identifiers[] = $identifier;
                }
            }

            $observed[$this->normalise((string) $path)] = array_values(array_unique($identifiers));
        }

        return $observed;
    }

    private function positiveFixture(string $short): string
    {
        return $this->fixturePath(self::POSITIVE_OVERRIDES[$short] ?? 'violations/'.$short.'.php');
    }

    private function fixturePath(string $relative): string
    {
        return $this->rootPath().'/'.self::FIXTURES.'/'.$relative;
    }

    private function rootPath(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * PHPStan writes the JSON report to stdout and its prose banner to stderr,
     * so only stdout is decoded.
     *
     * @param list<string> $command
     */
    private function runPhpstan(array $command, string $workingDirectory): string
    {
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $workingDirectory,
        );
        self::assertIsResource($process, 'Could not start PHPStan');

        $stdout = (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $stdout;
    }

    private function clearDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }

    private function normalise(string $path): string
    {
        $path = (string) preg_replace('/ \(in context of .*\)$/', '', $path);
        $real = realpath($path);

        return str_replace('\\', '/', false === $real ? $path : $real);
    }
}
