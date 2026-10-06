<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('bw_cs_translate_term')) {
    /**
     * The term in the given WPML language (default: the current one). Sessions are not translated,
     * they keep the term of the language they were saved in — so every output maps it here.
     * Returns the term itself without WPML or without a translation.
     */
    function bw_cs_translate_term($term, ?string $lang = null) {
        if (!is_object($term) || !has_filter('wpml_object_id')) return $term;

        $id = apply_filters('wpml_object_id', (int) $term->term_id, $term->taxonomy, true, $lang);
        if (!$id || (int) $id === (int) $term->term_id) return $term;

        $translated = get_term((int) $id, $term->taxonomy);
        return ($translated && !is_wp_error($translated)) ? $translated : $term;
    }
}
