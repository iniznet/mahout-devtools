<?php

/**
 * Deterministic WordPress function stubs for the unit and golden-file suites.
 *
 * They are guarded: when the integration suite has already loaded core, the
 * real functions exist and these are skipped. A stub does no formatting beyond
 * what core does, so a golden file asserts the component and not the stub.
 */

declare(strict_types=1);

if (!function_exists('esc_html')) {
    /**
     * @param string $text
     */
    function esc_html($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    /**
     * @param string $text
     */
    function esc_attr($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    /**
     * @param string $url
     */
    function esc_url($url): string
    {
        return (string) $url;
    }
}

if (!function_exists('esc_textarea')) {
    /**
     * @param string $text
     */
    function esc_textarea($text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_kses_post')) {
    /**
     * @param string $text
     */
    function wp_kses_post($text): string
    {
        return (string) $text;
    }
}
