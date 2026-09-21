<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Generator;

/**
 * Compares a generated reference with the committed one and writes it back.
 *
 * @internal
 */
final readonly class ReferenceGate
{
    public function __construct(
        private GeneratedReference $reference,
        private string $path,
    ) {
    }

    public function isCurrent(): bool
    {
        if (!is_file($this->path)) {
            return false;
        }

        $committed = file_get_contents($this->path);
        if (false === $committed) {
            return false;
        }

        return $committed === $this->reference->generate();
    }

    public function write(): bool
    {
        $directory = \dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            return false;
        }

        return false !== file_put_contents($this->path, $this->reference->generate());
    }

    /**
     * The first line where the committed and generated text disagree, so a
     * stale reference names its own drift.
     */
    public function firstDifference(): string
    {
        if (!is_file($this->path)) {
            return 'reference file is missing: '.$this->path;
        }

        $committed = explode(PHP_EOL, (string) file_get_contents($this->path));
        $generated = explode(PHP_EOL, $this->reference->generate());
        $lines = max(\count($committed), \count($generated));

        for ($index = 0; $index < $lines; ++$index) {
            $left = $committed[$index] ?? '<absent>';
            $right = $generated[$index] ?? '<absent>';
            if ($left !== $right) {
                return sprintf('line %d: committed "%s", generated "%s"', $index + 1, $left, $right);
            }
        }

        return 'the files differ but no line does';
    }
}
