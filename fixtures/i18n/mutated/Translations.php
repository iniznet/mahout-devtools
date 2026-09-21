<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Fixtures\I18n;

/**
 * A deliberately mutated source: one new string was added, so the generated
 * POT must differ from the committed one and the gate must fail.
 */
final class Translations
{
    public function render(): string
    {
        return __('Diverge from the committed POT', 'howdah');
    }
}
