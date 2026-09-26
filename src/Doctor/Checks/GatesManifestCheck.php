<?php

/**
 * The gate scripts are the family's, not each repository's. Law 5: what the
 * contract promises as a gate is enforced here, so a package that drops a gate,
 * redefines one, or runs the set in a different order fails `composer
 * config:check` instead of waiting for an audit.
 *
 * The expected commands are read from this package's `resources/gates.json`.
 * Two placeholders are resolved from the repository under examination, so the
 * manifest stays one file for the whole family:
 *
 * - `{domain}` is the package name's last element, which is also the text
 *   domain and the artefact name;
 * - `{source}` is the first path the repository's own `phpstan.neon` analyses,
 *   so a gate can never scan a different tree than the analyzer scans.
 *
 * A repository may declare scripts beyond the set; they are its own business.
 * It may not omit, edit, or reorder a canonical gate, and the `check` event may
 * not reference a script the manifest does not know.
 */

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor\Checks;

use Iniznet\Mahout\Devtools\Contracts\Check;
use Iniznet\Mahout\Devtools\Doctor\CheckResult;

final readonly class GatesManifestCheck implements Check
{
    public function __construct(
        private string $root,
        private string $manifest,
    ) {
    }

    public function name(): string
    {
        return 'Gate scripts';
    }

    public function examine(): CheckResult
    {
        $expected = $this->object($this->readJson($this->manifest));

        if (null === $expected) {
            return CheckResult::fail($this->name(), 'the gate manifest is missing or is not a JSON object');
        }

        $actual = $this->object($this->readJson($this->root.'/composer.json'));

        if (null === $actual) {
            return CheckResult::fail($this->name(), 'composer.json is missing or is not a JSON object');
        }

        $scripts = $this->object($actual['scripts'] ?? null);

        if (null === $scripts) {
            return CheckResult::fail($this->name(), 'composer.json declares no scripts object');
        }

        [$domain, $source, $problems] = $this->placeholders($actual);

        $problems = array_merge(
            $problems,
            $this->gateFailures($expected, $scripts, $domain, $source),
            $this->eventFailures($expected, $scripts),
        );

        if ([] !== $problems) {
            return CheckResult::fail($this->name(), implode('; ', $problems));
        }

        return CheckResult::pass($this->name(), 'every gate matches the manifest');
    }

    /**
     * The two substitutions, derived from the repository itself. A repository
     * that cannot supply them is reported rather than guessed at.
     *
     * @param array<string, mixed> $actual
     *
     * @return array{string, string, list<string>}
     */
    private function placeholders(array $actual): array
    {
        $problems = [];

        $name = $actual['name'] ?? null;
        $domain = \is_string($name) && '' !== $name ? basename($name) : '';

        if ('' === $domain) {
            $problems[] = 'composer.json declares no package name, so the text domain cannot be derived';
        }

        $source = '';
        $neon = $this->root.'/phpstan.neon';

        if (is_file($neon) && 1 === preg_match('/^\s*paths:\s*\R\s*-\s*(\S+)/m', (string) file_get_contents($neon), $matches)) {
            $source = $matches[1];
        }

        if ('' === $source) {
            $problems[] = 'phpstan.neon declares no analysed path, so the gate source cannot be derived';
        }

        return [$domain, $source, $problems];
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $scripts
     *
     * @return list<string>
     */
    private function gateFailures(array $expected, array $scripts, string $domain, string $source): array
    {
        $canonical = $this->object($expected['scripts'] ?? null);

        if (null === $canonical) {
            return ['the gate manifest declares no scripts object'];
        }

        $optional = $this->strings($expected['optional'] ?? []) ?? [];
        $problems = [];

        foreach ($canonical as $gate => $template) {
            if (!\is_string($template)) {
                $problems[] = 'the manifest says nothing about the '.$gate.' gate';

                continue;
            }

            $want = strtr($template, ['{domain}' => $domain, '{source}' => $source]);
            $have = $scripts[$gate] ?? null;

            if (null === $have) {
                if (!\in_array($gate, $optional, true)) {
                    $problems[] = 'the '.$gate.' gate is missing';
                }

                continue;
            }

            // A gate is either one command or a list of them; anything else is
            // reported, never coerced.
            if (\is_array($have)) {
                $steps = $this->strings($have);

                if (null === $steps) {
                    $problems[] = 'the '.$gate.' gate is a list that is not a list of commands';

                    continue;
                }

                $have = implode(' && ', $steps);
            } elseif (!\is_string($have)) {
                $problems[] = 'the '.$gate.' gate is neither a command nor a list of commands';

                continue;
            }

            if ($have !== $want) {
                $problems[] = 'the '.$gate.' gate is redefined; the manifest says '.$want;
            }
        }

        return $problems;
    }

    /**
     * The `check` event must run the canonical set, in order, and may add only
     * manifest-declared optional gates.
     *
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $scripts
     *
     * @return list<string>
     */
    private function eventFailures(array $expected, array $scripts): array
    {
        $canonical = $this->strings($expected['check'] ?? null);

        if (null === $canonical) {
            return ['the gate manifest declares no check event'];
        }

        $canonical = array_map(static fn (string $gate): string => ltrim($gate, '@'), $canonical);
        $optional = $this->strings($expected['optional'] ?? []) ?? [];
        $event = $this->strings($scripts['check'] ?? null);

        if (null === $event) {
            return ['the check event must be a list of gate references'];
        }

        $seen = array_map(static fn (string $reference): string => ltrim($reference, '@'), $event);
        $problems = [];

        foreach ($event as $reference) {
            if (!str_starts_with($reference, '@')) {
                $problems[] = 'the check event carries '.$reference.', which is not a gate reference';
            }
        }

        $cursor = 0;

        foreach ($canonical as $gate) {
            $found = array_search($gate, $seen, true);

            if (false === $found) {
                $problems[] = 'the check event does not run the '.$gate.' gate';

                continue;
            }

            if ($found < $cursor) {
                $problems[] = 'the check event runs the '.$gate.' gate out of order';

                continue;
            }

            $cursor = $found + 1;
        }

        foreach ($seen as $gate) {
            if (!\in_array($gate, $canonical, true) && !\in_array($gate, $optional, true)) {
                $problems[] = 'the check event runs '.$gate.', which the manifest does not declare';
            }
        }

        return $problems;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function object(mixed $value): ?array
    {
        if (!\is_array($value)) {
            return null;
        }

        $object = [];

        foreach ($value as $key => $entry) {
            if (!\is_string($key)) {
                return null;
            }

            $object[$key] = $entry;
        }

        return $object;
    }

    /**
     * @return list<string>|null
     */
    private function strings(mixed $value): ?array
    {
        if (!\is_array($value)) {
            return null;
        }

        $strings = [];

        foreach ($value as $entry) {
            if (!\is_string($entry)) {
                return null;
            }

            $strings[] = $entry;
        }

        return $strings;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return \is_array($decoded) ? $decoded : null;
    }
}
