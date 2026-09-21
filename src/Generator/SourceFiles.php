<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Generator;

use Iniznet\Mahout\Devtools\Exception\FileMissing;

/**
 * The PHP files named by a generator's source roots, sorted for determinism.
 *
 * @internal
 */
final readonly class SourceFiles
{
    /**
     * @param list<string> $roots
     */
    public function __construct(
        private array $roots,
        private string $cwd,
    ) {
    }

    /**
     * @return list<string>
     */
    public function phpFiles(): array
    {
        $files = [];
        foreach ($this->roots as $root) {
            if (is_file($root)) {
                $files[] = $this->normalise($root);
                continue;
            }

            if (is_dir($root)) {
                foreach ($this->inDirectory($root) as $file) {
                    $files[] = $file;
                }
                continue;
            }

            throw FileMissing::at($root);
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * The path as it should appear in a reference file: relative to the
     * working directory when it is inside it, forward-slashed either way.
     */
    public function relative(string $file): string
    {
        $normalised = $this->normalise($file);
        $prefix = rtrim($this->normalise($this->cwd), '/').'/';

        if (str_starts_with($normalised, $prefix)) {
            return substr($normalised, \strlen($prefix));
        }

        return $normalised;
    }

    /**
     * @return list<string>
     */
    private function inDirectory(string $directory): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            if ($item instanceof \SplFileInfo && $item->isFile() && 'php' === strtolower($item->getExtension())) {
                $found[] = $this->normalise($item->getPathname());
            }
        }

        return $found;
    }

    private function normalise(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
