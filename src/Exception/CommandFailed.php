<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Exception;

/**
 * A delegated command (PHPUnit, a generator) could not start or exited non-zero.
 */
final class CommandFailed extends \RuntimeException implements DevtoolsException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function exited(string $command, int $exitCode): self
    {
        return new self(sprintf('Command "%s" exited with code %d', $command, $exitCode));
    }

    public static function couldNotStart(string $command): self
    {
        return new self(sprintf('Command "%s" could not be started', $command));
    }
}
