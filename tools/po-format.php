<?php
/**
 * Shared PO string escaping/unescaping helpers, used by make-pot.php and
 * make-de-po.php so both stay in sync instead of drifting apart.
 */

if (!function_exists('bw_po_escape')) {
    /**
     * Escapes a raw PHP string for use inside a PO "..." literal.
     * Order matters: backslashes first, then quotes, then newlines —
     * applied as one str_replace() call so later replacements don't
     * re-touch backslashes inserted by earlier ones.
     */
    function bw_po_escape(string $s): string {
        return str_replace(
            ['\\', '"', "\n", "\t"],
            ['\\\\', '\\"', '\\n', '\\t'],
            $s
        );
    }
}

if (!function_exists('bw_po_unescape')) {
    /** Reverses bw_po_escape() — order is the exact mirror. */
    function bw_po_unescape(string $s): string {
        return str_replace(
            ['\\n', '\\t', '\\"', '\\\\'],
            ["\n", "\t", '"', '\\'],
            $s
        );
    }
}
