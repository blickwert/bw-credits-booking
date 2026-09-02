<?php
if (!defined('ABSPATH')) exit;

/**
 * Zentraler Text-Katalog.
 *
 * Trennt drei Ebenen die sonst gern vermischt werden:
 *   Struktur  → Template-Datei (folgt in v0.13.0)
 *   Wortlaut  → dieser Katalog, im Admin überschreibbar
 *   Sprache   → gettext und WPML
 *
 * Auflösung: Shortcode-Attribut → Admin-Override → übersetzter Standard.
 *
 * Ein neuer Text braucht nur einen Eintrag in catalogue(). Er erscheint
 * dadurch automatisch auf der Einstellungsseite, in der WPML-Registrierung
 * und in der erzeugten .pot-Datei.
 */

class BW_Text {

    const DOMAIN        = 'bw-credits-booking';
    const OPT_OVERRIDES = 'bw_texts';

    /** Gruppe => Überschrift auf der Einstellungsseite */
    const GROUPS = [
        'booking'      => 'Buchen und Stornieren',
        'availability' => 'Verfügbarkeit',
        'balance'      => 'Guthaben',
        'credits'      => 'Guthaben im Detail',
        'bookings'     => 'Buchungsliste',
        'course_list'  => 'Terminliste',
        'access'       => 'Zugangsdaten',
        'overview'     => 'Konto-Übersicht',
        'error'        => 'Fehlermeldungen',
        'order_email'  => 'E-Mail nach Bestellung',
    ];

    /**
     * Schlüssel => [Standardtext, Beschreibung, Gruppe]
     *
     * Platzhalter in geschweiften Klammern, damit eine fehlerhafte Eingabe
     * im Admin keinen Fehler auslöst — anders als bei printf-Formaten.
     */
    public static function catalogue(): array {
        return [
            /* --- Buchen und Stornieren --- */
            'booking.button.book' => [
                'Kurs buchen (1 Credit)', 'Beschriftung des Buchen-Buttons', 'booking',
            ],
            'booking.button.cancel' => [
                'Buchung stornieren', 'Beschriftung wenn der Termin gebucht ist', 'booking',
            ],
            'booking.button.cancel_short' => [
                'Stornieren', 'Kurze Beschriftung in der Buchungsliste', 'booking',
            ],
            'booking.note.login' => [
                'Bitte einloggen um zu buchen.', 'Hinweis für nicht angemeldete Besucher', 'booking',
            ],
            'booking.note.past' => [
                'Dieser Termin ist vorbei.', 'Der Termin liegt in der Vergangenheit', 'booking',
            ],
            'booking.note.full' => [
                'Dieser Termin ist ausgebucht.', 'Kein Platz mehr frei', 'booking',
            ],
            'booking.note.booked' => [
                'Du bist für diesen Termin angemeldet.', 'Gebucht, aber Stornofrist abgelaufen', 'booking',
            ],
            'booking.note.no_credits' => [
                'Du hast keine Credits.', 'Guthaben aufgebraucht, vor dem Aufladen-Link — passt auch für Kunden ohne bisherige Buchung', 'booking',
            ],
            'booking.link.topup' => [
                'Jetzt aufladen', 'Beschriftung des Links zur Shop-Seite', 'booking',
            ],

            /* --- Verfügbarkeit --- */
            'availability.free' => [
                '{frei} freie Plätze', 'Platzhalter {frei} wird durch die Zahl ersetzt — bis zur Schwelle aus den Einstellungen', 'availability',
            ],
            'availability.more_than' => [
                'mehr als {n} Plätze frei', 'Ab der Schwelle aus den Einstellungen statt der exakten Zahl', 'availability',
            ],
            'availability.full' => [
                'Ausgebucht', 'Wenn kein Platz mehr frei ist', 'availability',
            ],

            /* --- Guthaben --- */
            'balance.label' => [
                'Verfügbare Credits:', 'Beschriftung vor der Zahl', 'balance',
            ],
            'balance.empty' => [
                'Dein Guthaben ist aufgebraucht.', 'Hinweis bei null Credits', 'balance',
            ],
            'balance.count.one' => [
                'Credit verfügbar', 'Einzahl in der Konto-Übersicht', 'balance',
            ],
            'balance.count.many' => [
                'Credits verfügbar', 'Mehrzahl in der Konto-Übersicht', 'balance',
            ],

            /* --- Guthaben im Detail --- */
            'credits.empty' => [
                'Du hast aktuell kein Guthaben.', 'Wenn keine Credits vorhanden sind', 'credits',
            ],
            'credits.source.purchase' => [
                'Kauf', 'Herkunft: über den Shop gekauft', 'credits',
            ],
            'credits.source.membership' => [
                'Mitgliedschaft', 'Herkunft: aus einer Mitgliedschaft', 'credits',
            ],
            'credits.source.manual' => [
                'Gutschrift', 'Herkunft: manuell gutgeschrieben', 'credits',
            ],
            'credits.expired' => [
                'abgelaufen', 'Status eines verfallenen Credits', 'credits',
            ],
            'credits.unlimited' => [
                'unbegrenzt gültig', 'Credit ohne Ablaufdatum', 'credits',
            ],
            'credits.valid_until' => [
                'gültig bis {datum}', 'Credit mit Ablaufdatum', 'credits',
            ],

            /* --- Buchungsliste --- */
            'bookings.empty' => [
                'Noch keine Buchungen vorhanden.', 'Leere Buchungsliste', 'bookings',
            ],
            'bookings.login_required' => [
                'Bitte einloggen.', 'Buchungsliste für nicht angemeldete Besucher', 'bookings',
            ],
            'bookings.status.booked' => [
                'Gebucht', 'Status einer aktiven Buchung', 'bookings',
            ],
            'bookings.status.cancelled' => [
                'Storniert', 'Status einer stornierten Buchung', 'bookings',
            ],
            'bookings.status.pending' => [
                'Ausstehend', 'Zwischenstatus während des Buchens', 'bookings',
            ],
            'bookings.status.no_show' => [
                'Nicht erschienen', 'Vom Studio als Nichterscheinen markiert', 'bookings',
            ],

            /* --- Terminliste --- */
            'course_list.empty' => [
                'Aktuell sind keine Termine geplant.', 'Keine Termine gefunden', 'course_list',
            ],
            'course_list.filter.all' => [
                'Alle', 'Erste Option in den Filter-Auswahlfeldern', 'course_list',
            ],
            'course_list.filter.submit' => [
                'Filtern', 'Schaltfläche im Filterformular', 'course_list',
            ],
            'course_list.filter.reset' => [
                'Zurücksetzen', 'Link zum Aufheben der Filter', 'course_list',
            ],
            'course_list.filter.type' => [
                'Kursart', 'Beschriftung des Filters für course_type', 'course_list',
            ],
            'course_list.filter.level' => [
                'Level', 'Beschriftung des Filters für course_level', 'course_list',
            ],
            'course_list.filter.lang' => [
                'Sprache', 'Beschriftung des Filters für course_lang', 'course_list',
            ],

            /* --- Zugangsdaten --- */
            'access.title' => [
                'Zugangsdaten', 'Überschrift über Meeting-Link und Hinweisen', 'access',
            ],
            'access.link' => [
                'Zum Online-Kurs', 'Beschriftung des Meeting-Links', 'access',
            ],

            /* --- Konto-Übersicht --- */
            'overview.heading.courses' => [
                'Meine Kurse', 'Überschrift über der Buchungsliste im Konto', 'overview',
            ],
            'overview.upcoming.label' => [
                'Kommende Kurse', 'Überschrift über der Kursliste im Konto', 'overview',
            ],
            'overview.link.courses' => [
                'Kurstermine ansehen', 'Link zur Terminliste', 'overview',
            ],
            'overview.link.orders' => [
                'Meine Bestellungen', 'Link zu den WooCommerce-Bestellungen', 'overview',
            ],
            'overview.link.topup' => [
                'Guthaben aufladen', 'Link zur Shop-Seite', 'overview',
            ],

            /* --- Fehlermeldungen ---
             * Diese landen über die REST-Schnittstelle direkt in der
             * Meldungszeile beim Kunden.
             */
            'error.no_credits' => [
                'Du hast kein Guthaben mehr.', 'Buchung ohne verfügbare Credits', 'error',
            ],
            'error.full' => [
                'Dieser Termin ist ausgebucht.', 'Buchung bei voller Kapazität', 'error',
            ],
            'error.slot_past' => [
                'Dieser Termin liegt in der Vergangenheit.', 'Buchung eines vergangenen Termins', 'error',
            ],
            'error.already_booked' => [
                'Du hast diesen Termin bereits gebucht.', 'Doppelbuchung', 'error',
            ],
            'error.booking_not_found' => [
                'Buchung nicht gefunden.', 'Storno einer unbekannten Buchung', 'error',
            ],
            'error.not_active' => [
                'Diese Buchung ist bereits storniert.', 'Storno einer stornierten Buchung', 'error',
            ],
            'error.cutoff_passed' => [
                'Die Stornofrist ist abgelaufen.', 'Storno nach Ablauf der Frist', 'error',
            ],
            'error.slot_invalid' => [
                'Dieser Termin ist nicht verfügbar.', 'Termin fehlt oder ist nicht veröffentlicht', 'error',
            ],
            'error.capacity_missing' => [
                'Für diesen Termin ist keine Kapazität hinterlegt.', 'Kapazität fehlt oder ist null', 'error',
            ],
            'error.slot_time_missing' => [
                'Für diesen Termin fehlt die Startzeit.', 'start_datetime nicht gesetzt', 'error',
            ],
            'error.retry' => [
                'Das hat nicht geklappt. Bitte versuche es noch einmal.', 'Gleichzeitiger Zugriff, Wiederholung nötig', 'error',
            ],
            'error.generic' => [
                'Die Aktion konnte nicht ausgeführt werden.', 'Unerwarteter Fehler beim Buchen oder Stornieren', 'error',
            ],

            /* --- E-Mail nach Bestellung --- */
            'order_email.heading' => [
                'Dein Guthaben wurde aufgeladen', 'Überschrift in der Woo-Bestell-E-Mail, wenn Credits gutgeschrieben wurden', 'order_email',
            ],
            'order_email.body' => [
                "{credits_hinzugefuegt} Credits wurden deinem Konto gutgeschrieben. Aktuelles Guthaben: {credits_verbleibend}.\n\nHier verwaltest du dein Guthaben und deine Buchungen: {konto_link}",
                'Text in der Woo-Bestell-E-Mail direkt nach der Bestellübersicht', 'order_email',
            ],
        ];
    }

    /* ---------------------------------------------------------
     * Auflösung
     * --------------------------------------------------------- */

    public static function get(string $key, array $vars = []): string {
        $entry = self::catalogue()[$key] ?? null;

        if ($entry === null) {
            // Fehlender Schlüssel darf die Seite nicht zerlegen
            return '';
        }

        $overrides = self::overrides();
        $text      = isset($overrides[$key]) && $overrides[$key] !== ''
            ? self::translate_override($key, $overrides[$key])
            // Lookup zur Laufzeit erfolgt über den Textwert; die .pot wird aus
            // dem Katalog erzeugt, siehe tools/make-pot.php
            : __($entry[0], self::DOMAIN);

        return $vars ? self::fill($text, $vars) : $text;
    }

    private static function fill(string $text, array $vars): string {
        $map = [];
        foreach ($vars as $name => $value) {
            $map['{' . $name . '}'] = (string) $value;
        }
        return strtr($text, $map);
    }

    public static function overrides(): array {
        $stored = get_option(self::OPT_OVERRIDES, []);
        return is_array($stored) ? $stored : [];
    }

    public static function default_for(string $key): string {
        return self::catalogue()[$key][0] ?? '';
    }

    /* ---------------------------------------------------------
     * WPML
     * --------------------------------------------------------- */

    public static function init() {
        add_action('init', [__CLASS__, 'load_textdomain']);
        add_action('init', [__CLASS__, 'register_wpml_strings'], 20);
    }

    public static function load_textdomain() {
        load_plugin_textdomain(
            self::DOMAIN,
            false,
            dirname(plugin_basename(BW_CREDITS_BOOKING_FILE)) . '/languages'
        );
    }

    /** Jeder Katalogeintrag wird als WPML-String angeboten. */
    public static function register_wpml_strings() {
        if (!has_action('wpml_register_single_string')) return;

        $overrides = self::overrides();

        foreach (self::catalogue() as $key => $entry) {
            $value = $overrides[$key] ?? $entry[0];
            do_action('wpml_register_single_string', 'BW Credits Texte', $key, $value);
        }
    }

    private static function translate_override(string $key, string $value): string {
        if (!has_filter('wpml_translate_single_string')) return $value;

        return (string) apply_filters(
            'wpml_translate_single_string', $value, 'BW Credits Texte', $key
        );
    }
}

BW_Text::init();

/**
 * Kurzform für den Einsatz im Code und in Templates.
 *
 *   bw_text('booking.note.full')
 *   bw_text('credits.valid_until', ['datum' => '31.12.2026'])
 */
function bw_text(string $key, array $vars = []): string {
    return BW_Text::get($key, $vars);
}
