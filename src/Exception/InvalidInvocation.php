<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Exception;

/**
 * The command line was invoked without the options a generator requires.
 */
final class InvalidInvocation extends \InvalidArgumentException implements DevtoolsException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function missingOption(string $name): self
    {
        return new self(sprintf('Required option --%s=... is missing', $name));
    }

    public static function unknownSource(string $path): self
    {
        return new self(sprintf('Source path does not exist: %s', $path));
    }
}
