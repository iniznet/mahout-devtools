<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Generator\Hooks;

/**
 * One public hook constant, with the documentation its reference entry needs.
 *
 * @internal
 */
final readonly class HookDefinition
{
    /**
     * @param list<string> $arguments
     */
    public function __construct(
        public string $class,
        public string $constant,
        public string $hook,
        public string $type,
        public string $since,
        public array $arguments,
        public string $description,
    ) {
    }
}
