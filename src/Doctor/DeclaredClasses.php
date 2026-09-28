<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Doctor;

/**
 * The types a source file declares, by name, without loading it.
 *
 * Loading is exactly what must not happen. `doctor` runs against an installed site whose
 * autoloader may be the thing under suspicion, and requiring a file to count its classes
 * would either succeed for the wrong reason or fatal on the very class the caller is
 * looking for. Tokens answer the question statically.
 *
 * @internal
 */
final class DeclaredClasses
{
    /**
     * Every type declared in one file, as fully qualified names.
     *
     * @return list<string>
     */
    public static function inFile(string $path): array
    {
        $source = @file_get_contents(str_replace('\\', '/', $path));

        if (false === $source) {
            return [];
        }

        $tokens = token_get_all($source);
        $namespace = '';
        $declared = [];

        foreach ($tokens as $index => $token) {
            if (!\is_array($token)) {
                continue;
            }

            if (T_NAMESPACE === $token[0]) {
                $namespace = self::namespaceOf($tokens, $index);

                continue;
            }

            if (!self::isDeclarationKeyword($token[0])) {
                continue;
            }

            $name = self::nameAfter($tokens, $index);

            if (null === $name) {
                continue;
            }

            $declared[] = '' === $namespace ? $name : $namespace.'\\'.$name;
        }

        return $declared;
    }

    /**
     * Every type declared under one directory, as fully qualified names.
     *
     * @return list<string>
     */
    public static function inDirectory(string $directory): array
    {
        $declared = [];

        foreach (self::filesIn($directory) as $file) {
            foreach (self::inFile($file) as $name) {
                $declared[$name] = true;
            }
        }

        $names = array_keys($declared);
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function filesIn(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || 'php' !== strtolower($file->getExtension())) {
                continue;
            }

            $files[] = str_replace('\\', '/', $file->getPathname());
        }

        sort($files);

        return $files;
    }

    private static function isDeclarationKeyword(int $id): bool
    {
        if (\in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
            return true;
        }

        $enum = \defined('T_ENUM') ? \constant('T_ENUM') : null;

        return \is_int($enum) && $id === $enum;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function namespaceOf(array $tokens, int $at): string
    {
        for ($i = $at + 1; isset($tokens[$i]); ++$i) {
            $token = $tokens[$i];

            if (!\is_array($token)) {
                break;
            }

            if (\in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $qualified = \defined('T_NAME_QUALIFIED') && \in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true);

            if (T_STRING === $token[0] || $qualified) {
                return trim($token[1], '\\');
            }

            break;
        }

        return '';
    }

    /**
     * The identifier a declaration keyword introduces, or null when it introduces none:
     * `new class {}` is an expression and `Foo::class` is a constant, and neither is a
     * type the file declares.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function nameAfter(array $tokens, int $at): ?string
    {
        $before = self::significantBefore($tokens, $at);

        if ('::' === $before || T_NEW === $before) {
            return null;
        }

        for ($i = $at + 1; isset($tokens[$i]); ++$i) {
            $token = $tokens[$i];

            if (!\is_array($token)) {
                return null;
            }

            if (\in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return T_STRING === $token[0] ? $token[1] : null;
        }

        return null;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function significantBefore(array $tokens, int $at): int|string
    {
        for ($i = $at - 1; $i >= 0; --$i) {
            $token = $tokens[$i];

            if (!\is_array($token)) {
                return (string) $token;
            }

            if (\in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return T_STRING === $token[0] ? $token[1] : $token[0];
        }

        return '';
    }
}
