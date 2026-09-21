<?php

declare(strict_types=1);

namespace Iniznet\Mahout\Devtools\Fixtures\I18n;

/**
 * Exercises every translation function the plan recognises, so the POT
 * generator and its drift gate have one fixture that covers the whole surface.
 */
final class Translations
{
    public function render(int $count, string $title): string
    {
        $output = __('Plain string', 'howdah');
        _e('Echoed string', 'howdah');

        /* translators: %s: number of chapters. */
        $plural = sprintf(
            esc_html(_n('%s chapter', '%s chapters', $count, 'howdah')),
            esc_html(number_format_i18n($count)),
        );

        $context = _x('Post', 'verb', 'howdah');
        $pluralContext = _nx('%s story', '%s stories', $count, 'noun', 'howdah');
        _ex('Read', 'imperative', 'howdah');

        $pages = _n_noop('%s page', '%s pages', 'howdah');
        $files = _nx_noop('%s file', '%s files', 'collection', 'howdah');
        $translated = translate_nooped_plural($pages, $count, 'howdah');
        $translatedContext = translate_nooped_plural($files, $count, 'howdah');

        /* translators: %s: series title. */
        $read = esc_html__('Read %s', 'howdah');
        /* translators: %s: series title. */
        esc_html_e('Open %s', 'howdah');
        wp_set_script_module_translations('howdah-app', 'howdah', '/languages');

        $attribute = esc_attr__('Title attribute', 'howdah');
        esc_attr_e('Label attribute', 'howdah');

        return $output.$plural.$context.$pluralContext.$translated.$translatedContext.$read.$attribute.$title;
    }
}
