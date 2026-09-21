<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Exception;

/**
 * The WordPress stub generator could not boot core or write its output.
 */
final class StubGenerationFailed extends \RuntimeException implements DevtoolsException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function because(string $reason): self
    {
        return new self(sprintf('Stub generation failed: %s', $reason));
    }

    public static function processExited(int $exitCode): self
    {
        return new self(sprintf('Stub generation subprocess exited with code %d', $exitCode));
    }
}
