<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Generator;

/**
 * A reference file rendered from the working tree.
 *
 * Stubs, the POT template and the hook reference all answer the same question:
 * given the current source, what should the committed artefact contain? One
 * interface keeps generation and drift detection a single mechanism.
 *
 * @internal
 */
interface GeneratedReference
{
    /**
     * A short noun for the artefact, used in the gate message.
     */
    public function kind(): string;

    /**
     * Render the deterministic reference text.
     */
    public function generate(): string;
}
