<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Exception;

/**
 * A file the tool requires is absent.
 */
final class FileMissing extends \RuntimeException implements DevtoolsException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function at(string $path): self
    {
        return new self(sprintf('Required file is missing: %s', $path));
    }

    public static function containing(string $description, string $path): self
    {
        return new self(sprintf('Required %s is missing: %s', $description, $path));
    }
}
