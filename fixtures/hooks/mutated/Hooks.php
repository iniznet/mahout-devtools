<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Fixtures\Hooks;

/**
 * A deliberately mutated Hooks class: one hook was renamed, so the generated
 * reference must differ from the committed one and the gate must fail.
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
    public const PROVIDERS = 'mahout/kernel/renamed_providers';
}
