<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Generator\Hooks;

/**
 * One public hook constant, with the documentation its reference entry needs.
 *
 * The kind is an enum rather than the docblock word, because the kind decides which
 * of the two generated documents the entry lands in and what that file is called.
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
        public HookType $type,
        public string $since,
        public array $arguments,
        public string $description,
    ) {
    }
}
