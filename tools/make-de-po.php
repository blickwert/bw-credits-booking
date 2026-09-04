<?php
/**
 * Generates languages/bw-credits-booking-de_DE.po — the German
 * translation of the (now English) text catalogue, captured from the
 * original German wording at the moment the catalogue was flipped to
 * English (v0.17.0).
 *
 * Validates the hand-maintained ENGLISH_TO_GERMAN map below against the
 * live catalogue: every current English default must have a German
 * translation, and vice versa — so the .po can never silently drift out
 * of sync with includes/text.php.
 *
 * Usage:  php tools/make-de-po.php
 *         (run after tools/make-pot.php, before tools/make-mo.php)
 */

if (PHP_SAPI !== 'cli') exit(1);

define('ABSPATH', true);

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

/**
 * English catalogue default => original German text, captured at the
 * v0.17.0 English flip. One entry per unique English default text (the
 * catalogue has one duplicate pair — booking.note.full / error.full —
 * which share both the English and the German text, so only one map
 * entry is needed for that text).
 */
const ENGLISH_TO_GERMAN = [
    'Book course (1 credit)' => 'Kurs buchen (1 Credit)',
    'Cancel booking' => 'Buchung stornieren',
    'Cancel' => 'Stornieren',
    'Please log in to book.' => 'Bitte einloggen um zu buchen.',
    'This session is over.' => 'Dieser Termin ist vorbei.',
    'This session is fully booked.' => 'Dieser Termin ist ausgebucht.',
    'You are booked for this session.' => 'Du bist für diesen Termin angemeldet.',
    'You have no credits.' => 'Du hast keine Credits.',
    'Top up now' => 'Jetzt aufladen',

    '{free} spots available' => '{free} freie Plätze',
    'more than {n} spots available' => 'mehr als {n} Plätze frei',
    'Fully booked' => 'Ausgebucht',

    'Available credits:' => 'Verfügbare Credits:',
    'Your credit balance is used up.' => 'Dein Guthaben ist aufgebraucht.',
    'credit available' => 'Credit verfügbar',
    'credits available' => 'Credits verfügbar',

    'You currently have no credits.' => 'Du hast aktuell kein Guthaben.',
    'Purchase' => 'Kauf',
    'Membership' => 'Mitgliedschaft',
    'Manual credit' => 'Gutschrift',
    'expired' => 'abgelaufen',
    'valid indefinitely' => 'unbegrenzt gültig',
    'valid until {date}' => 'gültig bis {date}',

    'No bookings yet.' => 'Noch keine Buchungen vorhanden.',
    'Please log in.' => 'Bitte einloggen.',
    'Booked' => 'Gebucht',
    'Cancelled' => 'Storniert',
    'Pending' => 'Ausstehend',
    'No-show' => 'Nicht erschienen',

    'No sessions are currently scheduled.' => 'Aktuell sind keine Termine geplant.',
    'All' => 'Alle',
    'Filter' => 'Filtern',
    'Reset' => 'Zurücksetzen',
    'Course type' => 'Kursart',
    'Level' => 'Level',
    'Language' => 'Sprache',

    'Access details' => 'Zugangsdaten',
    'Join online session' => 'Zum Online-Kurs',

    'My courses' => 'Meine Kurse',
    'Upcoming courses' => 'Kommende Kurse',
    'View course sessions' => 'Kurstermine ansehen',
    'My orders' => 'Meine Bestellungen',
    'Top up credits' => 'Guthaben aufladen',

    "You don't have any credits left." => 'Du hast kein Guthaben mehr.',
    'This session is in the past.' => 'Dieser Termin liegt in der Vergangenheit.',
    'You have already booked this session.' => 'Du hast diesen Termin bereits gebucht.',
    'Booking not found.' => 'Buchung nicht gefunden.',
    'This booking has already been cancelled.' => 'Diese Buchung ist bereits storniert.',
    'The cancellation deadline has passed.' => 'Die Stornofrist ist abgelaufen.',
    'This session is not available.' => 'Dieser Termin ist nicht verfügbar.',
    'No capacity is set for this session.' => 'Für diesen Termin ist keine Kapazität hinterlegt.',
    'This session is missing a start time.' => 'Für diesen Termin fehlt die Startzeit.',
    "That didn't work. Please try again." => 'Das hat nicht geklappt. Bitte versuche es noch einmal.',
    'The action could not be completed.' => 'Die Aktion konnte nicht ausgeführt werden.',

    'Your credit balance has been topped up' => 'Dein Guthaben wurde aufgeladen',
    "{credits_added} credits have been added to your account. Current balance: {credits_remaining}.\n\nManage your credits and bookings here: {account_link}" =>
        "{credits_added} Credits wurden deinem Konto gutgeschrieben. Aktuelles Guthaben: {credits_remaining}.\n\nHier verwaltest du dein Guthaben und deine Buchungen: {account_link}",
];

// --- Validate against the live catalogue before writing anything ---

$entries = BW_Text::catalogue();
$catalogue_texts = [];
foreach ($entries as $key => [$default, $description, $group]) {
    $catalogue_texts[$default] = true;
}

$missing = [];
foreach (array_keys($catalogue_texts) as $text) {
    if (!array_key_exists($text, ENGLISH_TO_GERMAN)) $missing[] = $text;
}

$extra = [];
foreach (array_keys(ENGLISH_TO_GERMAN) as $text) {
    if (!isset($catalogue_texts[$text])) $extra[] = $text;
}

if ($missing || $extra) {
    foreach ($missing as $text) printf("MISSING: %s\n", $text);
    foreach ($extra as $text) printf("EXTRA: %s\n", $text);
    exit(1);
}

// --- Pull #. / #: comments from the freshly generated .pot, keyed by msgid ---

$pot_path = __DIR__ . '/../languages/bw-credits-booking.pot';
if (!file_exists($pot_path)) {
    fwrite(STDERR, "languages/bw-credits-booking.pot not found — run tools/make-pot.php first.\n");
    exit(1);
}

$pot_comments = []; // msgid (raw PO string) => ['comment' => "#. ...\n#: ...\n", ...]
$pot_lines = file($pot_path, FILE_IGNORE_NEW_LINES);
$pending_comment = '';
foreach ($pot_lines as $line) {
    if (str_starts_with($line, '#.') || str_starts_with($line, '#:')) {
        $pending_comment .= $line . "\n";
    } elseif (str_starts_with($line, 'msgid "') && $pending_comment !== '') {
        $msgid = substr($line, 7, -1); // strip 'msgid "' and trailing '"'
        $pot_comments[$msgid] = $pending_comment;
        $pending_comment = '';
    } elseif ($line === '') {
        $pending_comment = '';
    }
}

// --- Write the .po ---

$out  = "# BW Credits + Bookings — German translation\n";
$out .= "# Generated by tools/make-de-po.php — do not edit by hand.\n";
$out .= "msgid \"\"\nmsgstr \"\"\n";
$out .= "\"Project-Id-Version: BW Credits + Bookings\\n\"\n";
$out .= "\"MIME-Version: 1.0\\n\"\n";
$out .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$out .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$out .= "\"Language: de_DE\\n\"\n";
$out .= "\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n";
$out .= "\"X-Domain: bw-credits-booking\\n\"\n\n";

foreach (ENGLISH_TO_GERMAN as $english => $german) {
    $escaped_en = bw_po_escape($english);
    $escaped_de = bw_po_escape($german);
    $out .= $pot_comments[$escaped_en] ?? '';
    $out .= "msgid \"{$escaped_en}\"\n";
    $out .= "msgstr \"{$escaped_de}\"\n\n";
}

file_put_contents(__DIR__ . '/../languages/bw-credits-booking-de_DE.po', $out);
printf("%d translations written to languages/bw-credits-booking-de_DE.po\n", count(ENGLISH_TO_GERMAN));
