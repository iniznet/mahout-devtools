<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Exception;

/**
 * A script module is registered with a translation domain other than the one
 * being extracted, so the module's strings would never be served.
 */
final class InvalidScriptModuleDomain extends \LogicException implements DevtoolsException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function found(string $domain, string $expected, string $location): self
    {
        return new self(sprintf(
            'wp_set_script_module_translations() at %s uses domain "%s"; the extracted domain is "%s"',
            $location,
            $domain,
            $expected,
        ));
    }
}
