<?php
if (!defined('ABSPATH')) exit;

/**
 * Template-Loader nach WooCommerce-Konvention.
 *
 * Suchreihenfolge: Child-Theme → Parent-Theme → Plugin. Trennt die
 * Struktur (hier) vom Wortlaut (includes/text.php, seit v0.12.0) — ein
 * Theme-Override legt damit nur das Layout fest, nie die Formulierung.
 */

class BW_Templates {

    /**
     * Bekannte Templates: relativer Pfad => Beschreibung.
     * Eine Quelle für den Loader und die Status-Seite BW Credits → Templates.
     */
    public static function registry(): array {
        return [
            'course_list/course_list.php'               => 'Terminliste — [bw_credits_course_list]',
            'course_availability/course_availability.php' => 'Freie Plätze — [bw_credits_course_availability]',
            'course_access/course_access.php'           => 'Zugangsdaten — [bw_credits_course_access]',
            'course_booking/course_booking.php'         => 'Buchen/Stornieren — [bw_credits_course_booking]',
            'user_balance/user_balance.php'             => 'Guthaben — [bw_credits_user_balance]',
            'user_credits/user_credits.php'             => 'Guthaben im Detail — [bw_credits_user_credits]',
            'user_bookings/user_bookings.php'           => 'Buchungsliste — [bw_credits_user_bookings]',
            'view_overview/view_overview.php'           => 'Konto-Übersicht — [bw_credits_view_overview]',
        ];
    }

    public static function plugin_path(string $name): string {
        return plugin_dir_path(BW_CREDITS_BOOKING_FILE) . 'templates/' . $name;
    }

    /** Version aus dem @version-Header einer Template-Datei, falls vorhanden. */
    public static function file_version(string $path): ?string {
        if (!is_readable($path)) return null;

        // Reicht bis zum Ende des Docblocks — Templates sind kurz genug
        $head = file_get_contents($path, false, null, 0, 4096);
        if ($head === false) return null;

        return preg_match('/@version\s+([0-9][0-9.]*)/', $head, $m) ? $m[1] : null;
    }
}

/**
 * Pfad zu einem Template — Theme-Override falls vorhanden, sonst die
 * Plugin-Kopie unter templates/.
 */
function bw_locate_template(string $name): string {
    $found = locate_template(['bw-credits-booking/' . $name]);

    if (!$found) {
        $found = BW_Templates::plugin_path($name);
    }

    return apply_filters('bw_locate_template', $found, $name);
}

/**
 * Ein Template einbinden. $args wird als lokale Variablen extrahiert,
 * genau wie bei WooCommerce — die Templates dokumentieren ihre erwarteten
 * Variablen im @var-Block.
 */
function bw_get_template(string $name, array $args = []): void {
    $file = bw_locate_template($name);
    if (!is_readable($file)) return;

    extract($args, EXTR_SKIP);
    include $file;
}
