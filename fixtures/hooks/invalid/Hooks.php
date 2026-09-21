<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Fixtures\Hooks;

/**
 * A public hook constant with no @action and no @filter: the generator must
 * refuse to guess.
 */
final class Hooks
{
    /**
     * Fires somewhere, the docblock does not say how.
     *
     * @since 1.0
     */
    public const UNTYPED = 'mahout/kernel/untyped';
}
