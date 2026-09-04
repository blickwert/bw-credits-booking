<?php
/**
 * Generates languages/bw-credits-booking.pot from the text catalogue.
 *
 * Needed because the defaults are passed to __() as a variable at
 * runtime — that works fine for translation, but is invisible to
 * xgettext. The catalogue is the only source, so we generate the
 * .pot from it directly.
 *
 * Usage:  php tools/make-pot.php
 */

if (PHP_SAPI !== 'cli') exit(1);

define('ABSPATH', true);

// Minimal stubs — we only need catalogue()
if (!function_exists('add_action'))            { function add_action(...$a) {} }
if (!function_exists('__'))                    { function __($t, $d = null) { return $t; } }
if (!function_exists('has_action'))            { function has_action(...$a) { return false; } }
if (!function_exists('has_filter'))            { function has_filter(...$a) { return false; } }
if (!function_exists('get_option'))            { function get_option($k, $d = false) { return $d; } }
if (!function_exists('plugin_basename'))       { function plugin_basename($f) { return $f; } }
if (!function_exists('load_plugin_textdomain')){ function load_plugin_textdomain(...$a) {} }
if (!defined('BW_CREDITS_BOOKING_FILE'))       { define('BW_CREDITS_BOOKING_FILE', __FILE__); }

require __DIR__ . '/po-format.php';
require __DIR__ . '/../includes/text.php';

$entries = BW_Text::catalogue();

$out  = "# BW Credits + Bookings\n";
$out .= "# Generated from includes/text.php — do not edit by hand.\n";
$out .= "msgid \"\"\nmsgstr \"\"\n";
$out .= "\"Project-Id-Version: BW Credits + Bookings\\n\"\n";
$out .= "\"MIME-Version: 1.0\\n\"\n";
$out .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$out .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$out .= "\"X-Domain: bw-credits-booking\\n\"\n\n";

$seen = [];
foreach ($entries as $key => [$default, $description, $group]) {
    if (isset($seen[$default])) {
        // Same text under two keys — only include it once in the .pot,
        // otherwise msgfmt rejects the file for a duplicate msgid
        continue;
    }
    $seen[$default] = true;

    $escaped = bw_po_escape($default);
    $out .= "#. {$description}\n";
    $out .= "#: catalogue:{$key}\n";
    $out .= "msgid \"{$escaped}\"\n";
    $out .= "msgstr \"\"\n\n";
}

file_put_contents(__DIR__ . '/../languages/bw-credits-booking.pot', $out);
printf("%d entries written (%d keys, %d duplicate texts merged)\n",
    count($seen), count($entries), count($entries) - count($seen));
