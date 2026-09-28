<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Generator\Hooks;

use Iniznet\Mahout\Devtools\Exception\FileMissing;
use Iniznet\Mahout\Devtools\Generator\SourceFiles;

/**
 * Reads every public hook constant declared on a Hooks class. Every hook name is a
 * public const on a Hooks class by contract, so constants elsewhere — an index name, a
 * cache group — are not hooks and are not listed.
 *
 * The scan is token-based, not reflection-based: a consumer's Hooks class need
 * not be loaded, and a constant whose docblock omits @action or @filter fails
 * loudly rather than being listed with a guessed type.
 *
 * @internal
 */
final readonly class HooksReference
{
    private const array IGNORED = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    public function __construct(private SourceFiles $files)
    {
    }

    /**
     * Every documented hook constant in the scanned sources, in file order.
     *
     * Rendering is HookDocument's job; this class only reads, so the two kinds can share
     * one scan instead of each re-tokenising the tree.
     *
     * @return list<HookDefinition>
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->files->phpFiles() as $file) {
            foreach ($this->scan($file) as $definition) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * @return list<HookDefinition>
     */
    private function scan(string $file): array
    {
        $code = file_get_contents($file);
        if (false === $code) {
            throw FileMissing::at($file);
        }

        /** @var list<array{0: int, 1: string, 2: int}|string> $tokens */
        $tokens = token_get_all($code);
        $definitions = [];
        $namespace = '';
        $class = null;
        $docblock = null;
        $count = \count($tokens);

        for ($index = 0; $index < $count; ++$index) {
            $token = $tokens[$index];
            if (\is_string($token)) {
                continue;
            }

            $identifier = $token[0];

            if (T_NAMESPACE === $identifier) {
                [$namespace, $index] = $this->readNamespace($tokens, $index);
                continue;
            }

            if (T_CLASS === $identifier || T_INTERFACE === $identifier || T_TRAIT === $identifier || T_ENUM === $identifier) {
                $class = $this->readClassName($tokens, $index, $namespace);
                continue;
            }

            if (T_DOC_COMMENT === $identifier) {
                $docblock = $token[1];
                continue;
            }

            if (T_CONST !== $identifier) {
                continue;
            }

            if ('public' !== $this->visibility($tokens, $index)) {
                $docblock = null;
                continue;
            }

            [$constant, $value, $index] = $this->readConstant($tokens, $index);
            $documentation = $this->documentation($docblock);
            $docblock = null;

            if (null === $constant || null === $value || null === $class) {
                continue;
            }

            if (!$this->isHookClass($class)) {
                continue;
            }

            $definitions[] = new HookDefinition(
                $class,
                $constant,
                $value,
                HookType::fromTag($documentation['type'], $class, $constant, $file),
                $documentation['since'],
                $documentation['arguments'],
                $documentation['description'],
            );
        }

        return $definitions;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array{0: string, 1: int}
     */
    private function readNamespace(array $tokens, int $from): array
    {
        $namespace = '';
        $count = \count($tokens);
        $index = $from;

        for ($index = $from + 1; $index < $count; ++$index) {
            $token = $tokens[$index];
            if (\is_array($token) && \in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $namespace .= $token[1];
                continue;
            }
            if (';' === $token || '{' === $token) {
                break;
            }
        }

        return [trim($namespace, '\\'), $index];
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function readClassName(array $tokens, int $from, string $namespace): ?string
    {
        $next = $this->nextMeaningful($tokens, $from + 1);
        if (null === $next || !\is_array($tokens[$next]) || T_STRING !== $tokens[$next][0]) {
            return null;
        }

        $class = $tokens[$next][1];

        return '' === $namespace ? $class : $namespace.'\\'.$class;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function visibility(array $tokens, int $const): string
    {
        for ($index = $const - 1; $index >= 0; --$index) {
            $token = $tokens[$index];
            if (\is_array($token) && \in_array($token[0], self::IGNORED, true)) {
                continue;
            }
            if (\is_array($token)) {
                return match ($token[0]) {
                    T_PRIVATE => 'private',
                    T_PROTECTED => 'protected',
                    default => 'public',
                };
            }
            break;
        }

        return 'public';
    }

    private function isHookClass(string $class): bool
    {
        $separator = strrpos($class, '\\');

        return 'Hooks' === (false === $separator ? $class : substr($class, $separator + 1));
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array{0: ?string, 1: ?string, 2: int}
     */
    private function readConstant(array $tokens, int $from): array
    {
        $name = null;
        $value = null;
        $count = \count($tokens);
        $index = $from;

        for ($index = $from + 1; $index < $count; ++$index) {
            $token = $tokens[$index];
            if (';' === $token) {
                break;
            }
            if ('=' === $token) {
                $value = $this->literal($tokens, $index + 1);
                break;
            }
            if (\is_array($token) && T_STRING === $token[0]) {
                $name = $token[1];
            }
        }

        return [$name, $value, $index];
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function literal(array $tokens, int $from): ?string
    {
        $index = $this->nextMeaningful($tokens, $from);
        if (null === $index) {
            return null;
        }

        $token = $tokens[$index];
        if (!\is_array($token) || T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
            return null;
        }

        return substr($token[1], 1, -1);
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function nextMeaningful(array $tokens, int $from): ?int
    {
        $count = \count($tokens);
        for ($index = $from; $index < $count; ++$index) {
            $token = $tokens[$index];
            if (\is_array($token) && \in_array($token[0], self::IGNORED, true)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * @return array{type: string, since: string, arguments: list<string>, description: string}
     */
    private function documentation(?string $docblock): array
    {
        $result = ['type' => '', 'since' => '', 'arguments' => [], 'description' => ''];

        if (null === $docblock) {
            return $result;
        }

        $lines = preg_split('/\R/', $docblock);
        if (false === $lines) {
            return $result;
        }

        $seenTag = false;
        $description = [];
        foreach ($lines as $line) {
            $clean = trim((string) preg_replace('#^\\s*/?\\*+\\s?#', '', $line));
            if ('' === $clean) {
                continue;
            }

            if (str_starts_with($clean, '@')) {
                $seenTag = true;
                if (preg_match('/^@(action|filter)\b/', $clean, $matches)) {
                    $result['type'] = $matches[1];
                } elseif (preg_match('/^@since\s+(\S+)/', $clean, $matches)) {
                    $result['since'] = $matches[1];
                } elseif (preg_match('/^@param\s+(\S+)\s+(\$\S+)/', $clean, $matches)) {
                    $result['arguments'][] = $matches[1].' '.$matches[2];
                }
                continue;
            }

            if (!$seenTag) {
                $description[] = $clean;
            }
        }

        $result['description'] = implode(' ', $description);

        return $result;
    }
}
