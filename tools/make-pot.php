<?php
/**
 * Erzeugt languages/bw-credits-booking.pot aus dem Text-Katalog.
 *
 * Nötig weil die Standards zur Laufzeit als Variable an __() gehen —
 * das funktioniert für die Übersetzung, ist für xgettext aber unsichtbar.
 * Der Katalog ist die einzige Quelle, also erzeugen wir die .pot daraus.
 *
 * Aufruf:  php tools/make-pot.php
 */

if (PHP_SAPI !== 'cli') exit(1);

define('ABSPATH', true);

// Minimale Stubs — wir brauchen nur catalogue()
if (!function_exists('add_action'))            { function add_action(...$a) {} }
if (!function_exists('__'))                    { function __($t, $d = null) { return $t; } }
if (!function_exists('has_action'))            { function has_action(...$a) { return false; } }
if (!function_exists('has_filter'))            { function has_filter(...$a) { return false; } }
if (!function_exists('get_option'))            { function get_option($k, $d = false) { return $d; } }
if (!function_exists('plugin_basename'))       { function plugin_basename($f) { return $f; } }
if (!function_exists('load_plugin_textdomain')){ function load_plugin_textdomain(...$a) {} }
if (!defined('BW_CREDITS_BOOKING_FILE'))       { define('BW_CREDITS_BOOKING_FILE', __FILE__); }

require __DIR__ . '/../includes/text.php';

$entries = BW_Text::catalogue();

$out  = "# BW Credits + Bookings\n";
$out .= "# Erzeugt aus includes/text.php — nicht von Hand bearbeiten.\n";
$out .= "msgid \"\"\nmsgstr \"\"\n";
$out .= "\"Project-Id-Version: BW Credits + Bookings\\n\"\n";
$out .= "\"MIME-Version: 1.0\\n\"\n";
$out .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$out .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$out .= "\"X-Domain: bw-credits-booking\\n\"\n\n";

$seen = [];
foreach ($entries as $key => [$default, $description, $group]) {
    if (isset($seen[$default])) {
        // Gleicher Text unter zwei Schlüsseln — nur einmal in die .pot,
        // sonst lehnt msgfmt die Datei wegen doppelter msgid ab
        continue;
    }
    $seen[$default] = true;

    $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $default);
    $out .= "#. {$description}\n";
    $out .= "#: catalogue:{$key}\n";
    $out .= "msgid \"{$escaped}\"\n";
    $out .= "msgstr \"\"\n\n";
}

file_put_contents(__DIR__ . '/../languages/bw-credits-booking.pot', $out);
printf("%d Einträge geschrieben (%d Schlüssel, %d doppelte Texte zusammengefasst)\n",
    count($seen), count($entries), count($entries) - count($seen));
