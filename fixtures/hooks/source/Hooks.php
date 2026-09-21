<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Fixtures\Hooks;

/**
 * The fixture Hooks class the generated reference is proven against.
 */
final class Hooks
{
    /**
     * Fires before the kernel runs provider and module registration.
     *
     * @since 1.0
     * @action
     * @param object $kernel The kernel.
     */
    public const BEFORE_BOOT = 'mahout/kernel/before_boot';

    /**
     * Filters the provider class list.
     *
     * @since 1.0
     * @filter
     * @param list<class-string> $providers The registered providers.
     * @param string $context The registration context.
     */
    public const PROVIDERS = 'mahout/kernel/providers';

    /**
     * Filters the module class list.
     *
     * @since 1.0
     * @filter
     * @param list<class-string> $modules The registered modules.
     */
    public const MODULES = 'mahout/kernel/modules';

    /**
     * A private constant is not part of the public contract.
     */
    private const INTERNAL = 'mahout/kernel/internal';
}
