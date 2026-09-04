<?php
/**
 * Scans a fixed list of plugin PHP files for literal-string calls to
 * WordPress i18n functions using the `bw-credits-booking` text domain,
 * and returns them as [text => ["file.php:line", ...]] — the same shape
 * tools/make-pot.php's catalogue loop already produces internally, so
 * both sources merge into one dedup pass there.
 *
 * Uses token_get_all() rather than a regex: these files contain
 * multi-line and escape-heavy string literals, and real PHP tokens
 * handle that correctly where a regex would be fragile. A call is only
 * picked up when every required argument (the msgid, and the domain) is
 * a single string-literal token — the same rule that keeps this scanner
 * from picking up catalogue-driven calls like
 * `esc_html__($heading, 'bw-credits-booking')` in admin-pages.php, whose
 * first argument is a variable, not a literal. Those stay covered by
 * make-pot.php's existing catalogue/GROUPS iteration instead.
 *
 * Usage:  php tools/scan-source-strings.php [--verbose]
 */

const BW_SCAN_DOMAIN = 'bw-credits-booking';

/** function name => [msgid argument positions, domain argument position] */
const BW_SCAN_TARGET_FUNCTIONS = [
    '__'         => ['msgid' => [0], 'domain' => 1],
    '_e'         => ['msgid' => [0], 'domain' => 1],
    'esc_html__' => ['msgid' => [0], 'domain' => 1],
    'esc_html_e' => ['msgid' => [0], 'domain' => 1],
    'esc_attr__' => ['msgid' => [0], 'domain' => 1],
    'esc_attr_e' => ['msgid' => [0], 'domain' => 1],
];

/**
 * Splits the argument list of a call, starting right after its opening
 * "(" (which the caller has already consumed). Returns [args, endIndex]
 * where args is a list of per-argument token arrays and endIndex is the
 * index of the matching ")". Returns [[], -1] on unbalanced input.
 */
function bw_scan_parse_call_args(array $tokens, int $start): array {
    $args    = [];
    $current = [];
    $depth   = 1;
    $n       = count($tokens);

    for ($i = $start; $i < $n; $i++) {
        $tok = $tokens[$i];
        $chr = is_array($tok) ? null : $tok;

        if ($chr === '(' || $chr === '[') {
            $depth++;
            $current[] = $tok;
            continue;
        }
        if ($chr === ')' || $chr === ']') {
            $depth--;
            if ($depth === 0) {
                $args[] = $current;
                return [$args, $i];
            }
            $current[] = $tok;
            continue;
        }
        if ($chr === ',' && $depth === 1) {
            $args[] = $current;
            $current = [];
            continue;
        }
        $current[] = $tok;
    }

    return [[], -1]; // unbalanced parens — caller treats endIndex < 0 as failure
}

/**
 * Returns the decoded string value of an argument's tokens if — and
 * only if — the argument is exactly one T_CONSTANT_ENCAPSED_STRING
 * token (ignoring surrounding whitespace/comments). A lone
 * T_CONSTANT_ENCAPSED_STRING is, by construction of PHP's tokenizer,
 * free of variable interpolation (an interpolated double-quoted string
 * splits into multiple tokens instead) — so eval()'ing just that one
 * token's raw text back is safe and gives an exact match for what PHP
 * itself would produce, without hand-rolling quote/escape handling.
 */
function bw_scan_literal_value(array $arg_tokens): ?string {
    $meaningful = array_values(array_filter($arg_tokens, function ($t) {
        return !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }));

    if (count($meaningful) !== 1) return null;
    $only = $meaningful[0];
    if (!is_array($only) || $only[0] !== T_CONSTANT_ENCAPSED_STRING) return null;

    return eval('return ' . $only[1] . ';');
}

/** Scans one already-tokenized file, returns [text => ["relpath:line", ...]]. */
function bw_scan_file_tokens(array $tokens, string $relpath): array {
    $found = [];
    $n = count($tokens);

    for ($i = 0; $i < $n; $i++) {
        $tok = $tokens[$i];
        if (!is_array($tok) || $tok[0] !== T_STRING) continue;

        $name = $tok[1];
        $line = $tok[2];
        if (!isset(BW_SCAN_TARGET_FUNCTIONS[$name])) continue;

        $j = $i + 1;
        while (isset($tokens[$j]) && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $j++;
        }
        if (!isset($tokens[$j]) || $tokens[$j] !== '(') continue;

        [$args, $end_index] = bw_scan_parse_call_args($tokens, $j + 1);
        if ($end_index < 0) continue; // unbalanced — skip rather than guess

        $spec = BW_SCAN_TARGET_FUNCTIONS[$name];

        if (!isset($args[$spec['domain']])) continue;
        $domain_val = bw_scan_literal_value($args[$spec['domain']]);
        if ($domain_val !== BW_SCAN_DOMAIN) continue;

        $values = [];
        foreach ($spec['msgid'] as $pos) {
            if (!isset($args[$pos])) continue 2;
            $val = bw_scan_literal_value($args[$pos]);
            if ($val === null) continue 2; // non-literal msgid (e.g. a variable) — not scannable, skip whole call
            $values[] = $val;
        }

        foreach ($values as $val) {
            $found[$val][] = "{$relpath}:{$line}";
        }
    }

    return $found;
}

/**
 * Scans the given plugin-relative file paths and returns the combined,
 * per-text occurrence list.
 */
function bw_scan_source_strings(array $files): array {
    $combined = [];
    foreach ($files as $relpath) {
        $abspath = __DIR__ . '/../' . $relpath;
        if (!file_exists($abspath)) {
            throw new RuntimeException("Scan target not found: {$relpath}");
        }
        $tokens = token_get_all(file_get_contents($abspath));
        foreach (bw_scan_file_tokens($tokens, $relpath) as $text => $locations) {
            foreach ($locations as $loc) {
                $combined[$text][] = $loc;
            }
        }
    }
    return $combined;
}

/** The fixed set of files Phase 2 wraps strings in — kept explicit, not a glob. */
function bw_scan_default_files(): array {
    return [
        'includes/admin-pages.php',
        'includes/metaboxes.php',
        'includes/settings.php',
        'includes/admin.php',
        'bw-credits-booking.php',
    ];
}

// --- CLI entry point — only runs when this file is the directly
//     invoked script, not when it's require()'d as a library. ---
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $verbose = in_array('--verbose', $argv, true);
    $results = bw_scan_source_strings(bw_scan_default_files());

    if ($verbose) {
        foreach ($results as $text => $locations) {
            printf("%s\n    %s\n", $text, implode(', ', $locations));
        }
    }
    printf("%d distinct strings found across %d occurrences in %d files\n",
        count($results),
        array_sum(array_map('count', $results)),
        count(bw_scan_default_files())
    );
}
