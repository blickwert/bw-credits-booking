<?php
/**
 * Generates languages/bw-credits-booking-de_DE.po — the German
 * translation of every English string this plugin ships: the text
 * catalogue (includes/text.php, flipped to English in v0.17.0), its
 * GROUPS headings, and the literal-string __()-style calls scattered
 * across the admin-facing PHP files (Phase 2, v0.18.0+).
 *
 * Validates the hand-maintained ENGLISH_TO_GERMAN map below against the
 * combined set of all three sources: every English string currently in
 * use must have a German translation, and vice versa — so the .po can
 * never silently drift out of sync with the source it's meant to cover.
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
require __DIR__ . '/scan-source-strings.php';
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

    /* =====================================================
     * includes/admin.php — course_slot admin column headers.
     * 'Level' and 'Language' reuse the identical catalogue texts
     * from course_list.filter.level / course_list.filter.lang above,
     * so only the genuinely new headers need an entry here.
     * ===================================================== */
    'Title' => 'Titel',
    'Start' => 'Beginn',
    'Type' => 'Typ',

    /* =====================================================
     * includes/text.php — BW_Text::GROUPS headings (10 entries)
     * ===================================================== */
    'Booking and Cancelling' => 'Buchen und Stornieren',
    'Availability' => 'Verfügbarkeit',
    'Credit Balance' => 'Guthaben',
    'Credit Balance Details' => 'Guthaben im Detail',
    'Booking List' => 'Buchungsliste',
    'Session List' => 'Terminliste',
    'Access Details' => 'Zugangsdaten',
    'Account Overview' => 'Konto-Übersicht',
    'Error Messages' => 'Fehlermeldungen',
    'Order Confirmation Email' => 'E-Mail nach Bestellung',

    /* =====================================================
     * includes/admin-pages.php (Phase 2b)
     * ===================================================== */
    'Sessions' => 'Termine',
    'Bookings' => 'Buchungen',
    'Texts' => 'Texte',
    'Not authorized.' => 'Keine Berechtigung.',
    'Credits' => 'Credits',
    'Shortcodes' => 'Shortcodes',
    'Templates' => 'Templates',
    'Upcoming' => 'Kommende',
    'Past' => 'Vergangene',
    'No sessions found.' => 'Keine Termine gefunden.',
    'Session' => 'Termin',
    'Occupancy' => 'Belegung',
    'Utilization' => 'Auslastung',
    'Actions' => 'Aktionen',
    'overbooked' => 'überbucht',
    'Participants' => 'Teilnehmer',
    'Edit' => 'Bearbeiten',
    'All sessions' => 'Alle Termine',
    'All statuses' => 'Alle Status',
    'Name or email' => 'Name oder E-Mail',
    'Filter' => 'Filtern',
    'Reset' => 'Zurücksetzen',
    'No bookings found.' => 'Keine Buchungen gefunden.',
    'Customer' => 'Kunde',
    'Booked on' => 'Gebucht am',
    'Status' => 'Status',
    'Credit' => 'Credit',
    'Action' => 'Aktion',
    'Free spot' => 'Freiplatz',
    'Cancel booking?' => 'Buchung stornieren?',
    'Cancel' => 'Stornieren',
    '%d bookings total.' => '%d Buchungen gesamt.',
    'Add booking' => 'Buchung hinzufügen',
    'Enrolls an existing user into a session — even if the session has already started. As a free spot, no credit is deducted.' =>
        'Trägt einen bestehenden Benutzer in einen Termin ein — auch wenn der Termin bereits begonnen hat. Als Freiplatz wird kein Credit abgezogen.',
    'User' => 'Benutzer',
    '— Select user —' => '— Benutzer wählen —',
    '— Select session —' => '— Termin wählen —',
    'Free spot — no credit deducted' => 'Freiplatz — ohne Credit-Abzug',
    'Create booking' => 'Buchung anlegen',
    'Name, email, or login' => 'Name, E-Mail oder Login',
    'Search users' => 'Benutzer suchen',
    'Search for a user to view and manage their credits.' => 'Benutzer suchen um dessen Credits zu sehen und zu verwalten.',
    'No users found.' => 'Keine Benutzer gefunden.',
    'Name' => 'Name',
    'Email' => 'E-Mail',
    'Available' => 'Verfügbar',
    'Manage' => 'Verwalten',
    'Purchase' => 'Kauf',
    'Membership' => 'Mitgliedschaft',
    'Manual credit' => 'Gutschrift',
    'Used' => 'Verbraucht',
    'Expired' => 'Abgelaufen',
    'Back to search' => 'Zurück zur Suche',
    'available' => 'verfügbar',
    'used' => 'verbraucht',
    'expired' => 'abgelaufen',
    'total' => 'gesamt',
    'Grant credits' => 'Credits gutschreiben',
    'Amount' => 'Anzahl',
    'Valid until' => 'Gültig bis',
    'empty = unlimited' => 'leer = unbegrenzt',
    'Grant' => 'Gutschreiben',
    'No credits yet.' => 'Keine Credits vorhanden.',
    'Source' => 'Herkunft',
    'Created' => 'Erstellt',
    'Booking' => 'Buchung',
    'Past expiry date' => 'Frist abgelaufen',
    'unlimited' => 'unbegrenzt',
    'Revoke this credit?' => 'Diesen Credit entwerten?',
    'Revoke' => 'Entwerten',
    'Select a user and a session.' => 'Benutzer und Termin auswählen.',
    'Booking created (free spot).' => 'Buchung angelegt (Freiplatz).',
    'Booking created.' => 'Buchung angelegt.',
    'Booking cancelled.' => 'Buchung storniert.',
    '1 credit granted.' => '1 Credit gutgeschrieben.',
    '%d credits granted.' => '%d Credits gutgeschrieben.',
    'Copy to theme' => 'In Theme kopieren',
    'Each template can be overridden in the active theme under %1$s — e.g.&nbsp;%2$s. "%3$s" creates the file there directly. Wording doesn\'t belong in the templates — that\'s maintained under %4$s.' =>
        'Jedes Template kann im aktiven Theme unter %1$s überschrieben werden — z.&nbsp;B. %2$s. „%3$s" legt die Datei dort direkt an. Wortlaut gehört nicht in die Templates, der wird unter %4$s gepflegt.',
    'Template' => 'Template',
    'Description' => 'Beschreibung',
    'Version' => 'Version',
    'Plugin default' => 'Plugin-Standard',
    'Overridden in theme — outdated' => 'Im Theme überschrieben — veraltet',
    'Overridden in theme' => 'Im Theme überschrieben',
    'Plugin:' => 'Plugin:',
    'Unknown template.' => 'Unbekanntes Template.',
    'Source file is not readable.' => 'Quelldatei nicht lesbar.',
    'Copy failed — check write permissions in the theme.' => 'Kopieren fehlgeschlagen — Schreibrechte im Theme prüfen.',
    '%s copied to theme.' => '%s ins Theme kopiert.',
    '%1$d texts, %2$d customized. An empty field uses the default text.' => '%1$d Texte, davon %2$d angepasst. Ein leeres Feld nutzt den Standardtext.',
    'Placeholders in curly braces are preserved, e.g.&nbsp;%1$s or %2$s.' => 'Platzhalter in geschweiften Klammern bleiben erhalten, z.&nbsp;B. %1$s oder %2$s.',
    'Default:' => 'Standard:',
    'Course' => 'Kurs',
    'Session list, grouped by day, with availability and a book button.' => 'Terminliste, nach Tagen gruppiert, mit Verfügbarkeit und Buchen-Button.',
    'A button that books or cancels depending on state.' => 'Ein Button der je nach Zustand bucht oder storniert.',
    'Available spots. Visible even without login.' => 'Freie Plätze. Auch ohne Login sichtbar.',
    'Access details for the online session. Only for participants with an active booking.' => 'Zugangsdaten zum Online-Kurs. Nur für Teilnehmer mit aktiver Buchung.',
    'Available credit balance. With mode="empty_only", only visible if the customer has had credits before and now has none.' =>
        'Verfügbares Guthaben. Mit mode="empty_only" nur sichtbar wenn der Kunde schon einmal Guthaben hatte und jetzt keines mehr hat.',
    'Credit balance in detail: origin and expiry date.' => 'Guthaben im Detail: Herkunft und Ablaufdatum.',
    "The customer's bookings, with the option to cancel." => 'Buchungen des Kunden mit Storno-Möglichkeit.',
    'View' => 'Ansicht',
    'Credit balance, next session, and links. Appears automatically in the account dashboard.' => 'Guthaben, nächster Termin und Links. Steht automatisch im Konto-Dashboard.',
    'On a single session page, %s can be omitted — the current post is used instead.' => 'Auf einer Termin-Einzelseite kann %s entfallen — dann greift der aktuelle Beitrag.',
    'Group' => 'Gruppe',
    'Shortcode' => 'Shortcode',
    'Parameters' => 'Parameter',
    'Credit revoked.' => 'Credit entwertet.',

    /* =====================================================
     * includes/metaboxes.php (Phase 2b)
     * ===================================================== */
    'Capacity' => 'Kapazität',
    'Online Access' => 'Online-Zugang',
    'Maximum participants' => 'Maximale Teilnehmer',
    'Leave empty to use the default (%s) from settings.' => 'Leer lassen für den Standardwert (%s) aus den Einstellungen.',
    'Booked:' => 'Belegt:',
    'Overbooked' => 'Überbucht',
    'capacity is below the number of existing bookings.' => 'die Kapazität liegt unter der Zahl bestehender Buchungen.',
    'Calculated automatically and cannot be edited directly.' => 'Wird automatisch berechnet und kann nicht direkt bearbeitet werden.',
    'Meeting link' => 'Meeting-Link',
    'Access details / notes' => 'Zugangsdaten / Hinweise',
    'Meeting ID, password, dial-in numbers …' => 'Meeting-ID, Passwort, Einwahlnummern …',
    'As soon as a link is saved here for the first time, the access details are sent to all participants automatically. Anyone who books afterwards receives them directly with the booking confirmation.' =>
        'Sobald hier zum ersten Mal ein Link gespeichert wird, gehen die Zugangsdaten automatisch an alle Teilnehmer. Wer danach noch bucht, bekommt sie direkt mit der Buchungsbestätigung.',
    'Resend access details to all participants?' => 'Zugangsdaten an alle Teilnehmer erneut senden?',
    'Resend access details' => 'Zugangsdaten erneut senden',
    'Only necessary if the link has changed since.' => 'Nur nötig wenn sich der Link nachträglich geändert hat.',
    'No bookings for this session yet.' => 'Noch keine Buchungen für diesen Termin.',
    'No.' => 'Nr.',
    'Cancel booking? A consumed credit will be refunded.' => 'Buchung stornieren? Ein verbrauchter Credit wird zurückgegeben.',
    'Attendance list as CSV' => 'Anwesenheitsliste als CSV',
    'Marked as no-show.' => 'Als nicht erschienen markiert.',
    'No-show mark removed.' => 'Markierung zurückgenommen.',

    /* =====================================================
     * includes/settings.php (Phase 2b)
     * ===================================================== */
    'Settings' => 'Einstellungen',
    'General' => 'Allgemein',
    'General settings for sessions and bookings.' => 'Grundeinstellungen für Kurstermine und Buchungen.',
    'Course session post type' => 'Kurstermin-Inhaltstyp',
    'Default capacity' => 'Standard-Kapazität',
    'Cancellation deadline (hours)' => 'Storno-Frist (Stunden)',
    'Reminder (hours before)' => 'Erinnerung (Stunden vorher)',
    'Availability threshold' => 'Verfügbarkeits-Schwelle',
    'Which post type holds the course sessions. Bookings, participant lists, and capacity all refer to this type.' =>
        'Welcher Inhaltstyp die Kurstermine enthält. Buchungen, Teilnehmerlisten und Kapazität beziehen sich auf diesen Typ.',
    'Warning:' => 'Achtung:',
    'The saved type %s is not currently registered.' => 'Der gespeicherte Typ %s ist aktuell nicht registriert.',
    'Used when no capacity is set for the session itself.' => 'Wird verwendet wenn beim Termin keine eigene Kapazität eingetragen ist.',
    'How many hours before the session start customers may cancel on their own.' => 'Bis wie viele Stunden vor Kursbeginn Kunden selbst stornieren dürfen.',
    'When the reminder email is sent. 0 = no reminder.' => 'Wann die Erinnerungs-E-Mail verschickt wird. 0 = keine Erinnerung.',
    'From how many free spots onward only "more than N spots available" is shown instead of the exact number. 0 = always show the exact number.' =>
        'Ab wie vielen freien Plätzen nur noch „mehr als N Plätze frei" statt der genauen Zahl angezeigt wird. 0 = immer die genaue Zahl.',

    /* =====================================================
     * includes/admin.php (Phase 2b, remaining non-domain-bug strings)
     * ===================================================== */
    '(system field – calculated automatically)' => '(Systemfeld – wird automatisch berechnet)',
    'Credit Amount' => 'Credit Amount',
    'How many credits this product tops up (e.g. 12 for a 10-pack).' => 'Wie viele Credits dieses Produkt auflädt (z.B. 12 für 10er Block).',
    'Valid Days' => 'Valid Days',
    'Validity in days (0 or empty = unlimited).' => 'Gültigkeit in Tagen (0 oder leer = unlimitiert).',
    'Credit Source' => 'Credit Source',
    'purchase = one-time purchase. membership = membership credits (expire on cancellation).' => 'purchase = Einmalkauf. membership = Membership-Credits (verfallen bei Kündigung).',
    'Purchase (one-time)' => 'Purchase (Einmalkauf)',
    'Membership (monthly)' => 'Membership (monatlich)',

    /* =====================================================
     * bw-credits-booking.php — JS i18n bridge (Phase 2b)
     * ===================================================== */
    'Cancelled' => 'Storniert',
    'booking_id missing (button needs data-booking-id)' => 'booking_id fehlt (Button braucht data-booking-id)',
    'Book' => 'Buchen',
    'Request failed (%d)' => 'Anfrage fehlgeschlagen (%d)',
];

// --- Validate against the combined live source set before writing anything ---

$live_texts = [];

$entries = BW_Text::catalogue();
foreach ($entries as $key => [$default, $description, $group]) {
    $live_texts[$default] = true;
}

foreach (BW_Text::GROUPS as $heading) {
    $live_texts[$heading] = true;
}

$scanned = bw_scan_source_strings(bw_scan_default_files());
foreach (array_keys($scanned) as $text) {
    $live_texts[$text] = true;
}

$missing = [];
foreach (array_keys($live_texts) as $text) {
    if (!array_key_exists($text, ENGLISH_TO_GERMAN)) $missing[] = $text;
}

$extra = [];
foreach (array_keys(ENGLISH_TO_GERMAN) as $text) {
    if (!isset($live_texts[$text])) $extra[] = $text;
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
