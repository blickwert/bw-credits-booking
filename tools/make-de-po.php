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
if (!function_exists('add_filter'))            { function add_filter(...$a) {} }
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
require __DIR__ . '/../includes/emails.php';

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
    'Booking for this session has closed.' => 'Die Buchung für diesen Termin ist geschlossen.',
    'Booking closes (minutes before start)' => 'Buchung möglich bis (Minuten vor Beginn)',
    'How many minutes before the session start customers can still book. 0 = until the session starts. Bookings by the admin are not affected.' => 'Bis wie viele Minuten vor Kursbeginn Kunden noch buchen können. 0 = bis zum Beginn. Buchungen durch die Admin sind nicht betroffen.',
    'This session is fully booked.' => 'Dieser Termin ist ausgebucht.',
    'You are booked for this session.' => 'Du bist für diesen Termin angemeldet.',
    'You have no credits.' => 'Du hast keine Credits.',
    'Top up now' => 'Jetzt aufladen',
    'Buy now' => 'Jetzt kaufen',
    'Your cart was replaced by the selected credit package.' => 'Dein Warenkorb wurde durch das gewählte Guthaben-Paket ersetzt.',
    'A credit package can only be ordered on its own. Please complete this order first or empty your cart.' => 'Ein Guthaben-Paket kann nur einzeln bestellt werden. Bitte schließe zuerst diese Bestellung ab oder leere deinen Warenkorb.',
    'Newsletter Opt-in' => 'Newsletter-Anmeldung',
    'Yes, I would like to receive the newsletter once a week and get the Beginner Special once for €40 instead of €45. I will get a confirmation email first and can unsubscribe at any time.' => 'Ja, ich möchte den Newsletter einmal pro Woche erhalten und bekomme das Einsteiger-Special einmalig für 40 € statt 45 €. Ich erhalte zuerst eine Bestätigungs-E-Mail und kann mich jederzeit abmelden.',
    'Privacy Policy' => 'Datenschutzerklärung',
    'Newsletter opt-in' => 'Newsletter-Anmeldung',
    'Optional checkbox at the account registration and the checkout. Ticking it starts the double opt-in of the Brevo newsletter form (form 4 for German, form 5 for all other languages).' => 'Optionale Checkbox bei der Kontoregistrierung und an der Kasse. Mit dem Häkchen startet das Double-Opt-in des Brevo-Newsletter-Formulars (Formular 4 für Deutsch, Formular 5 für alle anderen Sprachen).',
    'Newsletter checkbox' => 'Newsletter-Checkbox',
    'Show the newsletter checkbox at the registration and the checkout' => 'Newsletter-Checkbox bei der Registrierung und an der Kasse anzeigen',
    'Off by default. The wording can be changed under Newsletter Opt-in in the text settings.' => 'Standardmäßig aus. Der Wortlaut lässt sich in den Texteinstellungen unter „Newsletter-Anmeldung“ ändern.',
    'New here? Sign up for the newsletter and get the Beginner Special (3 credits, valid for 90 days) once for €40 instead of €45.' => 'Neu bei uns? Melde dich für den Newsletter an und erhalte das Einsteiger-Special (3 Credits, 90 Tage gültig) einmalig für 40 € statt 45 €.',
    'Switch to the Beginner Special' => 'Zum Einsteiger-Special wechseln',
    'With a newsletter sign-up you pay only €40 instead of €45 – once per person.' => 'Mit Newsletter-Anmeldung zahlst du nur 40 € statt 45 € – einmalig pro Person.',
    'Sign up for the newsletter' => 'Zum Newsletter anmelden',
    'Beginner Special (product ID)' => 'Einsteiger-Special (Produkt-ID)',
    'Discount code' => 'Rabattcode',
    'The product the newsletter offer is about (in the default language; translations are found automatically). The cart and checkout notices, the product page line and the automatic discount refer to it.' => 'Das Produkt, um das es im Newsletter-Angebot geht (in der Standardsprache; Übersetzungen werden automatisch gefunden). Die Hinweise im Warenkorb und an der Kasse, die Zeile auf der Produktseite und der automatische Rabatt beziehen sich darauf.',
    'WooCommerce coupon that is applied automatically to confirmed newsletter subscribers when the Beginner Special is in the cart. It is only valid for confirmed subscribers.' => 'WooCommerce-Gutschein, der bestätigten Newsletter-Abonnent:innen automatisch gegeben wird, wenn das Einsteiger-Special im Warenkorb ist. Er gilt nur für bestätigte Abonnent:innen.',
    'New here? With a newsletter sign-up the Beginner Special costs only €40 instead of €45 (once per person). Once you have confirmed your sign-up by email, the discount is applied here automatically.' => 'Neu bei uns? Mit Newsletter-Anmeldung kostet das Einsteiger-Special nur 40 € statt 45 € (einmalig pro Person). Sobald du deine Anmeldung per E-Mail bestätigt hast, wird der Rabatt hier automatisch angewendet.',
    'Thank you for being part of the newsletter! Your newsletter discount has been applied automatically.' => 'Danke, dass du beim Newsletter dabei bist! Dein Newsletter-Rabatt wurde automatisch angewendet.',
    'Sign up for the newsletter (new tab)' => 'Zur Newsletter-Anmeldung (neuer Tab)',
    'This code is only valid for confirmed newsletter subscribers. Please order with the email address you used to sign up.' => 'Dieser Code gilt nur für bestätigte Newsletter-Abonnent:innen. Bitte bestelle mit der E-Mail-Adresse, mit der du dich angemeldet hast.',
    'Cancellation rule' => 'Stornoregel',
    'New here? Sign up for the newsletter and get the Beginner Special once for €40 instead of €45. Once you have confirmed your sign-up, the discount is applied automatically at the checkout.' => 'Neu bei uns? Melde dich für den Newsletter an und erhalte das Einsteiger-Special einmalig für 40 € statt 45 €. Sobald du deine Anmeldung bestätigt hast, wird der Rabatt an der Kasse automatisch angewendet.',
    'Sign up for the newsletter now' => 'Jetzt zum Newsletter anmelden',
    'Deleted user #%d' => 'Gelöschter Benutzer #%d',
    'Credit products in the shop' => 'Guthaben-Produkte im Shop',
    'A credit product is a product with a credit amount greater than 0 (product data › General).' => 'Ein Guthaben-Produkt ist ein Produkt mit einem Guthaben-Betrag größer 0 (Produktdaten › Allgemein).',
    'Direct checkout' => 'Direkt zur Kasse',
    'Complete after payment' => 'Nach Zahlung abschließen',
    'Straight to the checkout, replace the cart, quantity 1' => 'Direkt zur Kasse, Warenkorb ersetzen, Menge 1',
    'Buying a credit product skips the cart page and replaces whatever was in the cart. Credit products and other products are never mixed in one cart.' => 'Beim Kauf eines Guthaben-Produkts entfällt die Warenkorb-Seite, und der bisherige Warenkorb wird ersetzt. Guthaben-Produkte und andere Produkte liegen nie gemeinsam im Warenkorb.',
    'Complete orders with credit products only once they are paid' => 'Bestellungen, die nur Guthaben-Produkte enthalten, nach der Zahlung abschließen',
    'Credits are granted when an order is completed. This way customers get them right after paying. Orders of guests and orders with other products stay as they are.' => 'Das Guthaben wird beim Abschluss einer Bestellung gutgeschrieben. So erhalten Kundinnen es direkt nach der Zahlung. Bestellungen von Gästen und Bestellungen mit anderen Produkten bleiben unverändert.',
    'Completed automatically: the order consists only of credit products.' => 'Automatisch abgeschlossen: Die Bestellung enthält nur Guthaben-Produkte.',

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
    'Cart and Checkout' => 'Warenkorb und Kasse',
    'Booking and Cancelling' => 'Buchen und Stornieren',
    'Time Zone' => 'Zeitzone',
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
    'Every 5 minutes' => 'Alle 5 Minuten',
    'Admin: participant list' => 'Admin: Teilnehmerliste',
    'Sent to the address set below shortly before the session starts (after booking has closed) with the active participants — timing set in Settings.' => 'Geht an die unten eingestellte Adresse kurz vor Kursbeginn (nach Buchungsschluss) mit den aktiven Teilnehmern – Zeitpunkt unter Einstellungen.',
    'Participant list to the studio (minutes before start)' => 'Teilnehmerliste ans Studio (Minuten vor Beginn)',
    'The studio gets an email with the active participants this many minutes before the session starts (after booking has closed). 0 = off. Recipient and text: BW Credits → E-mails.' => 'Das Studio bekommt so viele Minuten vor Kursbeginn (nach Buchungsschluss) eine E-Mail mit den aktiven Teilnehmern. 0 = aus. Empfänger und Text: BW Credits → E-Mails.',
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
    'Session list with a calendar-leaf date per session, availability, and a book button.' => 'Terminliste mit Kalenderblatt-Datum je Termin, Verfügbarkeit und Buchen-Button.',
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

    /* =====================================================
     * bw-credits-booking.php — WP_Error messages and demo
     * shortcodes found and wrapped during the Phase 3 comment sweep
     * ===================================================== */
    'User not found.' => 'Benutzer nicht gefunden.',
    'Amount must be between 1 and 500.' => 'Anzahl muss zwischen 1 und 500 liegen.',
    'Credit grant failed.' => 'Gutschrift fehlgeschlagen.',
    'Credit not found or already used.' => 'Credit nicht gefunden oder bereits verbraucht.',
    'Status could not be changed.' => 'Status konnte nicht geändert werden.',
    'slot_id is missing.' => 'slot_id fehlt.',
    'Booked.' => 'Gebucht.',
    'booking_id is missing.' => 'booking_id fehlt.',
    'Cancelled.' => 'Storniert.',

    /* =====================================================
     * includes/emails.php — email-type labels/descriptions and the
     * Emails settings page chrome (out of scope for Phase 2, fixed
     * during the Phase 3 comment sweep). The actual default email
     * subject/body content (BW_Emails::defaults()) is a separate,
     * WPML-driven subsystem deliberately left untouched — see the
     * Phase 3 summary for why.
     * ===================================================== */
    'Booking confirmation' => 'Buchungsbestätigung',
    'Sent to the customer right after a successful booking.' => 'Direkt nach einer erfolgreichen Buchung an den Kunden.',
    'Cancellation confirmation' => 'Stornobestätigung',
    'Sent to the customer after a cancellation.' => 'Nach einer Stornierung an den Kunden.',
    'Reminder' => 'Erinnerung',
    'Before the session starts — timing set in Settings.' => 'Vor Kursbeginn — Zeitpunkt in den Einstellungen.',
    'Meeting link and access details for online sessions — timing set in Settings → Online Access.' =>
        'Meeting-Link und Zugangsdaten für Online-Termine — Zeitpunkt in Einstellungen → Online-Zugang.',
    'Defaults for online sessions. A meeting link or access details entered on a session itself take precedence.' =>
        'Standardwerte für Online-Termine. Ein direkt am Termin eingetragener Meeting-Link bzw. Zugangsdaten haben Vorrang.',
    'Same access for every session' => 'Gleiche Zugangsdaten für alle Termine',
    'Use the meeting link and access details below for all sessions' =>
        'Meeting-Link und Zugangsdaten unten für alle Termine verwenden',
    'New sessions start with these values in their own fields; sessions without values of their own use them as well. Untick if every session has its own link.' =>
        'Neue Termine übernehmen diese Werte in ihre eigenen Felder; Termine ohne eigene Werte verwenden sie ebenfalls. Haken entfernen, wenn jeder Termin einen eigenen Link hat.',
    'Default meeting link' => 'Standard-Meeting-Link',
    'Default access details / notes' => 'Standard-Zugangsdaten / Hinweise',
    'Send access details (hours before)' => 'Zugangsdaten senden (Stunden vorher)',
    'Used for every session that has no meeting link of its own.' =>
        'Gilt für jeden Termin ohne eigenen Meeting-Link.',
    'Used for every session that has no access details of its own.' =>
        'Gilt für jeden Termin ohne eigene Zugangsdaten.',
    'When participants receive the access details email. 0 = immediately, as soon as a meeting link is available. Otherwise this many hours before the session starts — bookings made later receive it right away.' =>
        'Wann Teilnehmer die Zugangsdaten-Mail erhalten. 0 = sofort, sobald ein Meeting-Link vorhanden ist. Sonst so viele Stunden vor Kursbeginn — spätere Buchungen erhalten sie sofort.',
    'Leave empty to use the default from Settings (shown in grey).' =>
        'Leer lassen, um den Standardwert aus den Einstellungen zu verwenden (grau angezeigt).',
    'The access details are sent to all participants automatically %d hours before the session starts. Anyone who books later receives them right away.' =>
        'Die Zugangsdaten werden automatisch %d Stunden vor Kursbeginn an alle Teilnehmer verschickt. Wer später bucht, erhält sie sofort.',
    'Admin: new booking' => 'Admin: Neue Buchung',
    'Sent to the address set below for every new booking, with the current number of participants.' => 'Bei jeder neuen Buchung an die unten hinterlegte Adresse, mit der aktuellen Teilnehmerzahl.',
    'Admin: cancellation' => 'Admin: Stornierung',
    'Sent to the address set below whenever a participant cancels, with the current number of participants.' => 'Bei jeder Stornierung an die unten hinterlegte Adresse, mit der aktuellen Teilnehmerzahl.',
    'Access details sent to %d participants.' => 'Zugangsdaten an %d Teilnehmer verschickt.',
    'Nothing sent — meeting link is missing or there are no active bookings.' => 'Nichts verschickt — Meeting-Link fehlt oder keine aktiven Buchungen.',
    'Emails' => 'E-Mails',
    'Email Texts' => 'E-Mail-Texte',
    'The subject and body below are the source text. To translate them into other languages, use WPML → String Translation, filtered by context %s.' => 'Betreff und Text unten sind der Ausgangstext. Übersetzungen in andere Sprachen erfolgen über WPML → String-Übersetzung, gefiltert nach Kontext %s.',
    'Open WPML String Translation' => 'WPML String-Übersetzung öffnen',
    'Avoid applying formatting (bold, links, …) to only part of a placeholder — e.g. bolding half of {course_title} can split it apart so it no longer gets replaced.' => 'Formatierungen (fett, Links, …) sollten nicht nur einen Teil eines Platzhalters betreffen — z. B. kann das Fettmarkieren der Hälfte von {course_title} ihn so zerteilen, dass er nicht mehr ersetzt wird.',
    'Available placeholders:' => 'Verfügbare Platzhalter:',
    'Address for admin copies' => 'Adresse für Admin-Kopien',
    'Leave empty to use the recipient of the WooCommerce “New order” email.' => 'Leer lassen, um den Empfänger der WooCommerce-E-Mail „Neue Bestellung“ zu verwenden.',
    'Active' => 'Aktiv',
    'Send this email' => 'Diese E-Mail verschicken',
    'Subject' => 'Betreff',
    'Body' => 'Text',
    'Reset to default' => 'Auf Standard zurücksetzen',
    'Reset all to default' => 'Alle auf Standard zurücksetzen',
    '%d min.' => '%d Min.',
    'Only once per customer' => 'Nur einmal pro Person',
    "Whoever already ordered this product (processing, completed or on hold) can't order it again. Checked by account and billing e-mail address; counts for all language versions." => 'Wer dieses Produkt schon bestellt hat (in Bearbeitung, abgeschlossen oder wartend), kann es nicht erneut bestellen. Geprüft wird über das Konto und die Rechnungs-E-Mail-Adresse; gilt für alle Sprachversionen.',
    'You have already booked “%s”. This offer is only available once per person.' => 'Du hast „%s“ bereits gebucht. Dieses Angebot gibt es nur einmal pro Person.',
    'Only available once per person.' => 'Nur einmal pro Person erhältlich.',
    'One value of a session, for the single-session template: type, level and language (names and descriptions), start, date, time, duration, calendar leaf.' => 'Ein Wert einer Session für die Einzel-Session-Vorlage: Typ, Level und Sprache (Namen und Beschreibungen), Start, Datum, Uhrzeit, Dauer, Kalenderblatt.',
    'All emails reset to the default text.' => 'Alle E-Mails wurden auf den Standardtext zurückgesetzt.',
    'Reset ALL emails to the default text? Your saved changes to every subject and body will be lost.' => 'ALLE E-Mails auf den Standardtext zurücksetzen? Deine gespeicherten Änderungen an allen Betreffs und Texten gehen verloren.',
    'Reset %s to the default text? Your saved changes will be lost.' => '%s auf den Standardtext zurücksetzen? Deine gespeicherten Änderungen gehen dabei verloren.',
    '%s reset to the default text.' => '%s auf den Standardtext zurückgesetzt.',
    'Unknown email type.' => 'Unbekannter E-Mail-Typ.',

    /* =====================================================
     * includes/email-language.php (v0.32.0) — 'Language' reuses the
     * existing course_list filter label entry above, not repeated here.
     * ===================================================== */
    'Save' => 'Speichern',
    'The language your booking confirmations, reminders, and other emails are sent in.' => 'Die Sprache, in der deine Buchungsbestätigungen, Erinnerungen und andere E-Mails verschickt werden.',
    'Language saved.' => 'Sprache gespeichert.',
    'Unknown language.' => 'Unbekannte Sprache.',

    /* =====================================================
     * includes/product-features.php (v0.33.0)
     * ===================================================== */
    'Product feature list' => 'Produkt-Feature-Liste',
    'Enable the product feature list (fields below on each product, plus the [bw_product_feature] shortcode)' => 'Produkt-Feature-Liste aktivieren (Felder unten bei jedem Produkt, plus der [bw_product_feature]-Shortcode)',
    'Feature List' => 'Feature-Liste',
    'Feature %d Title' => 'Feature %d Titel',
    'Feature %d Description' => 'Feature %d Beschreibung',
    'Number of features' => 'Anzahl der Features',
    'How many title+description pairs appear per product. Lowering this hides, but does not delete, data already saved in the now-hidden slots.' => 'Wie viele Titel+Beschreibung-Paare pro Produkt erscheinen. Ein Verringern blendet bereits gespeicherte Daten in den dadurch ausgeblendeten Feldern nur aus, löscht sie aber nicht.',
    'Meta key: %1$s — Shortcode: %2$s' => 'Meta-Key: %1$s — Shortcode: %2$s',

    /* =====================================================
     * includes/emails.php — BW_Emails::defaults() (v0.31.0):
     * the German text ships as the gettext translation of the English
     * source, resolved automatically for a de_DE site with no WPML
     * setup — see subject_source()/body_source() in emails.php.
     * ===================================================== */
    "Before your class" => "Vor deiner Klasse",
    "One day before your class" => "Einen Tag vor deiner Klasse",
    "Two days before your class" => "Zwei Tage vor deiner Klasse",
    "%d days before your class" => "%d Tage vor deiner Klasse",
    "One hour before your class" => "Eine Stunde vor deiner Klasse",
    "%d hours before your class" => "%d Stunden vor deiner Klasse",
    "See You on the Mat Soon! 🧘‍♀️" => "Wir sehen uns bald auf der Matte! 🧘‍♀️",
    "Greetings {first_name} 🌿,\n\nYour class has been successfully booked! ✨\n\nI’m looking forward to welcoming you and sharing this practice with you. 🤍\n\n<strong>Class Details 🧘‍♀️</strong>\nDate: {date}\nTime: {time}\nClass Type: {course_type}\nLevel: {course_level}\n\nYou can view, manage or cancel your booking anytime in your account: {account_link}\n{cancel_note}\n\n{access_timing}, you will receive the Zoom link along with all additional details needed to join the session. 💻✨\n\nIf you do not receive this email or if you experience any issues accessing the class, please feel free to contact me at {contact_email}. I’ll be happy to assist you. 🌱\n\nI look forward to seeing you on the mat soon. 🤍\n\nWith warmth,\nHelena 🌸" =>
        "Hallo {first_name} 🌿,\n\ndeine Klasse wurde erfolgreich gebucht! ✨\n\nIch freue mich darauf, dich willkommen zu heißen und diese Praxis mit dir zu teilen. 🤍\n\n<strong>Klassendetails 🧘‍♀️</strong>\nDatum: {date}\nUhrzeit: {time}\nArt der Klasse: {course_type}\nLevel: {course_level}\n\nDu kannst deine Buchung jederzeit in deinem Konto ansehen, verwalten oder stornieren: {account_link}\n{cancel_note}\n\n{access_timing} erhältst du den Zoom-Link sowie alle weiteren Informationen, die du benötigst, um an der Sitzung teilzunehmen. 💻✨\n\nFalls du diese E-Mail nicht erhältst oder Schwierigkeiten beim Zugang zur Klasse hast, kannst du dich jederzeit unter {contact_email} bei mir melden. Ich helfe dir gerne weiter. 🌱\n\nIch freue mich darauf, dich bald auf der Matte zu sehen. 🤍\n\nMit Wärme,\nHelena 🌸",
    // Time zones (0.49.0)
    '{city} time' => '{city}-Zeit',
    '{time} in your time zone ({zone})' => '{time} in deiner Zeitzone ({zone})',
    'You can cancel until {deadline}; your credit is refunded.' => 'Du kannst bis {deadline} stornieren, dein Guthaben wird dir gutgeschrieben.',
    'Cancellation is no longer possible (less than {hours} hours before the start).' => 'Eine Stornierung ist nicht mehr möglich (weniger als {hours} Stunden vor Beginn).',
    'If you book now, you can no longer cancel (less than {hours} hours before the start).' => 'Wenn du jetzt buchst, ist keine Stornierung mehr möglich (weniger als {hours} Stunden vor Beginn).',
    'Time zone' => 'Zeitzone',
    'Automatic (from your browser)' => 'Automatisch (aus deinem Browser)',
    'Session times are shown in your time zone too — on the website and in emails.' => 'Kurszeiten werden dir auch in deiner Zeitzone angezeigt — auf der Website und in E-Mails.',
    '%s your time' => '%s bei dir',
    'Your local time' => 'Deine Ortszeit',
    'Zoom link:' => 'Zoom-Link:',
    'The access details follow by email before the class.' => 'Die Zugangsdaten bekommst du vor dem Kurs per E-Mail.',
    'Cancellation: {course_title}' => 'Stornierung: {course_title}',
    "Hi {customer_name},\n\nyour booking has been cancelled:\n\n{course_title}\n{date} at {time}\n\nCredits remaining: {credits_remaining}\n\nManage your bookings here: {account_link}" =>
        "Hallo {customer_name},\n\ndeine Buchung wurde storniert:\n\n{course_title}\n{date} um {time}\n\nVerbleibende Credits: {credits_remaining}\n\nDeine Buchungen verwaltest du hier: {account_link}",
    'Session cancelled' => 'Kurs abgesagt',
    'Sent to the customers of an upcoming session that was moved to the trash or deleted — the credit is refunded automatically.' => 'Geht an die Kunden eines kommenden Kurses, der in den Papierkorb gelegt oder gelöscht wurde — das Guthaben wird automatisch zurückgebucht.',
    'Session cancelled: {course_title}' => 'Kurs abgesagt: {course_title}',
    "Hi {customer_name},\n\nunfortunately this session had to be cancelled:\n\n{course_title}\n{date} at {time}\n\nYour credit has been refunded, you can use it for another session. Credits available: {credits_remaining}\n\nFind another session here: {account_link}\n\nI am sorry for the inconvenience." =>
        "Hallo {customer_name},\n\nleider musste dieser Kurs abgesagt werden:\n\n{course_title}\n{date} um {time}\n\nDein Guthaben wurde zurückgebucht, du kannst es für einen anderen Kurs nutzen. Verfügbare Credits: {credits_remaining}\n\nEinen anderen Kurs findest du hier: {account_link}\n\nEs tut mir leid für die Umstände.",
    'Session removed: %s' => 'Kurs entfernt: %s',
    "The session was moved to the trash or deleted. %1\$d of %2\$d bookings were cancelled, the credits were refunded and the customers informed:\n\n%3\$s" => "Der Kurs wurde in den Papierkorb gelegt oder gelöscht. %1\$d von %2\$d Buchungen wurden storniert, die Credits zurückgebucht und die Kunden informiert:\n\n%3\$s",
    'Account deleted: %s' => 'Konto gelöscht: %s',
    "The account of %1\$s (%2\$s) was deleted.\n\nUpcoming bookings cancelled and refunded: %3\$d of %4\$d.\nUnused credits left: %5\$d. They stay on file without a name and can no longer be used." => "Das Konto von %1\$s (%2\$s) wurde gelöscht.\n\nKommende Buchungen storniert und zurückgebucht: %3\$d von %4\$d.\nÜbrige ungenutzte Credits: %5\$d. Sie bleiben ohne Namen gespeichert und können nicht mehr verwendet werden.",
    '%1$s: %2$d upcoming booking(s) will be cancelled and refunded, %3$d unused credit(s) stay on file without a name and can no longer be used.' => '%1$s: %2$d kommende Buchung(en) werden storniert und zurückgebucht, %3$d ungenutzte Credit(s) bleiben ohne Namen gespeichert und können nicht mehr verwendet werden.',
    'Credits and bookings' => 'Credits und Buchungen',
    'Reminder: {course_title} on {date}' => 'Erinnerung: {course_title} am {date}',
    "Hi {customer_name},\n\nyour session is coming up:\n\n{course_title}\n{date} at {time}\n\nSession details: {course_link}\nManage your bookings here: {account_link}\n\nWe look forward to seeing you!" =>
        "Hallo {customer_name},\n\ndein Kurs steht an:\n\n{course_title}\n{date} um {time}\n\nDetails zum Kurs: {course_link}\nDeine Buchungen verwaltest du hier: {account_link}\n\nWir freuen uns auf dich!",
    "Your Class Link & Joining Details 💻✨" => "Dein Klassen-Link & Teilnahmeinformationen 💻✨",
    "A mindful hello {first_name} 🌿,\n\nYour class is coming up soon. ✨ Below you will find all the details you need to join the session and prepare for your practice.\n\n<strong>Class Details 🧘‍♀️</strong>\nDate: {date}\nTime: {time}\nClass Type: {course_type}\nLevel: {course_level}\n\n<strong>Zoom Details 💻</strong>\nJoin Link: {meeting_link}\n{access_details}\n\nTo join the class, you can either click directly on the Zoom link above, or open Zoom and enter the Meeting ID and Passcode manually. 🌱\n\nTo ensure a smooth and welcoming experience for everyone, please make sure to:<ul><li>Join using your real first and last name, so I can confirm your identity and welcome you into the session. 🤍</li><li>Keep your camera turned on during the class. 📹</li></ul>\nIf you experience any difficulties joining or have any questions, please reach out at {contact_email} and I’ll be happy to assist you. 🌿\n\nI’m looking forward to our upcoming session and sharing this practice with you. ✨\n\nWith gratitude,\nHelena 🌸" =>
        "Ein achtsames Hallo {first_name} 🌿,\n\ndeine Klasse findet bald statt. ✨ Nachfolgend findest du alle Informationen, die du benötigst, um an der Sitzung teilzunehmen und dich auf deine Praxis vorzubereiten.\n\n<strong>Klassendetails 🧘‍♀️</strong>\nDatum: {date}\nUhrzeit: {time}\nArt der Klasse: {course_type}\nLevel: {course_level}\n\n<strong>Zoom-Details 💻</strong>\nTeilnahmelink: {meeting_link}\n{access_details}\n\nUm an der Klasse teilzunehmen, kannst du entweder direkt auf den Zoom-Link oben klicken oder Zoom öffnen und die Meeting-ID sowie das Passwort manuell eingeben. 🌱\n\nDamit die Teilnahme für alle reibungslos und angenehm verläuft, bitte ich dich, Folgendes zu beachten:<ul><li>Trete mit deinem echten Vor- und Nachnamen bei, damit ich deine Identität bestätigen und dich persönlich in der Sitzung begrüßen kann. 🤍</li><li>Bitte lasse deine Kamera während der Klasse eingeschaltet. 📹</li></ul>\nFalls du Schwierigkeiten beim Beitreten hast oder Fragen auftreten, kannst du dich jederzeit unter {contact_email} bei mir melden. Ich helfe dir gerne weiter. 🌿\n\nIch freue mich auf unsere gemeinsame Praxis und darauf, diese Erfahrung mit dir zu teilen. ✨\n\nMit Dankbarkeit,\nHelena 🌸",
    'New booking: {course_title} ({booked_count}/{capacity})' => 'Neue Buchung: {course_title} ({booked_count}/{capacity})',
    "{customer_name} ({customer_email}) booked:\n\n{course_title}\n{date} at {time}\n\nParticipants now: {booked_count} of {capacity}\nParticipant list: {admin_link}" =>
        "{customer_name} ({customer_email}) hat gebucht:\n\n{course_title}\n{date} um {time}\n\nTeilnehmer jetzt: {booked_count} von {capacity}\nTeilnehmerliste: {admin_link}",
    'Participants: {course_title}, {date} at {time} ({booked_count}/{capacity})' => 'Teilnehmer: {course_title}, {date} um {time} ({booked_count}/{capacity})',
    "{course_title}\n{date} at {time}\n\nParticipants ({booked_count} of {capacity}):\n{participant_list}\n\nParticipant list in the plugin: {admin_link}" =>
        "{course_title}\n{date} um {time}\n\nTeilnehmer ({booked_count} von {capacity}):\n{participant_list}\n\nTeilnehmerliste im Plugin: {admin_link}",
    'Cancellation: {course_title} ({booked_count}/{capacity})' => 'Stornierung: {course_title} ({booked_count}/{capacity})',
    "{customer_name} ({customer_email}) cancelled:\n\n{course_title}\n{date} at {time}\n\nParticipants now: {booked_count} of {capacity}\nParticipant list: {admin_link}" =>
        "{customer_name} ({customer_email}) hat storniert:\n\n{course_title}\n{date} um {time}\n\nTeilnehmer jetzt: {booked_count} von {capacity}\nTeilnehmerliste: {admin_link}",

    /* =====================================================
     * includes/templates.php — template registry descriptions
     * (Phase 3): shown on BW Credits → Templates, mirrors the
     * GROUPS/description pattern — the value must be wrapped at its
     * source here, not at the esc_html() call site in admin-pages.php.
     * ===================================================== */
    'Session list — [bw_credits_course_list]' => 'Terminliste — [bw_credits_course_list]',
    'Available spots — [bw_credits_course_availability]' => 'Freie Plätze — [bw_credits_course_availability]',
    'Access details — [bw_credits_course_access]' => 'Zugangsdaten — [bw_credits_course_access]',
    'Book/Cancel — [bw_credits_course_booking]' => 'Buchen/Stornieren — [bw_credits_course_booking]',
    'Credit balance — [bw_credits_user_balance]' => 'Guthaben — [bw_credits_user_balance]',
    'Credit balance in detail — [bw_credits_user_credits]' => 'Guthaben im Detail — [bw_credits_user_credits]',
    'Booking list — [bw_credits_user_bookings]' => 'Buchungsliste — [bw_credits_user_bookings]',
    'Account overview — [bw_credits_view_overview]' => 'Konto-Übersicht — [bw_credits_view_overview]',

    /* =====================================================
     * includes/updater.php (Phase 3)
     * ===================================================== */
    'BW Credits + Bookings – a WooCommerce credit-based booking system for courses.' => 'BW Credits + Bookings – WooCommerce Credit-Buchungssystem für Kurse.',
    'No changelog information available.' => 'Keine Changelog-Info vorhanden.',
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

foreach (BW_Emails::defaults() as $texts) {
    $live_texts[$texts['subject']] = true;
    $live_texts[$texts['body']] = true;
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
