<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Exception;

/**
 * A public hook constant is not documented with the action-versus-filter tag
 * the generated reference requires.
 */
final class InvalidHookDeclaration extends \LogicException implements DevtoolsException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function missingType(string $class, string $constant, string $file): self
    {
        return new self(sprintf(
            'Hook %s::%s in %s declares neither @action nor @filter',
            $class,
            $constant,
            $file,
        ));
    }
}
