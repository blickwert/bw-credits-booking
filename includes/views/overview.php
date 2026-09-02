<?php
if (!defined('ABSPATH')) exit;

/**
 * [bw_credits_view_overview] — Konto-Übersicht.
 *
 * Guthaben, nächster gebuchter Termin und Einstiegslinks in einem Block.
 * Gedacht fürs WooCommerce-Konto-Dashboard. Markup liegt in
 * templates/overview/, überschreibbar im Theme.
 */

class BW_View_Overview {

    public static function render($atts) {
        $atts = shortcode_atts([
            'show_balance' => 'true',
            'show_next'    => 'true',
            'show_links'   => 'true',
            'list_url'     => '',
        ], $atts, 'bw_credits_view_overview');

        if (!is_user_logged_in()) return '';

        // data-bw-balance wird vom JS aktualisiert — Skript und Styles nötig
        BW_Credits_Bookings_MVP::ensure_assets();

        ob_start();
        bw_get_template('overview/wrapper.php', ['atts' => $atts]);
        return ob_get_clean();
    }

    public static function render_balance(int $user_id) {
        $available = BW_Credits_Bookings_MVP::get_available_credits($user_id);

        bw_get_template('overview/balance.php', [
            'available' => $available,
            'label'     => bw_text($available === 1 ? 'balance.count.one' : 'balance.count.many'),
        ]);
    }

    /**
     * Nächster anstehender Termin. Geht die Buchungen durch und nimmt den
     * frühesten der noch bevorsteht — die Startzeit liegt in Postmeta,
     * lässt sich also nicht direkt in der Buchungsabfrage sortieren.
     */
    public static function render_next(int $user_id) {
        $bookings = BW_Credits_Bookings_MVP::get_my_bookings($user_id, 100);
        $now      = time();
        $next     = null;

        foreach ($bookings as $booking) {
            if ((int) $booking['is_active'] !== 1 || $booking['status'] !== 'booked') continue;

            $start = BW_Credits_Bookings_MVP::get_slot_start_datetime((int) $booking['slot_id']);
            if (!$start || $start->getTimestamp() <= $now) continue;

            if ($next === null || $start->getTimestamp() < $next['ts']) {
                $next = [
                    'ts'      => $start->getTimestamp(),
                    'slot_id' => (int) $booking['slot_id'],
                ];
            }
        }

        bw_get_template('overview/next.php', [
            'next'       => $next,
            'empty_text' => bw_text('overview.next.none'),
        ]);
    }

    public static function render_links(string $list_url) {
        $links = [];

        if ($list_url !== '') {
            $links[] = ['url' => $list_url, 'label' => bw_text('overview.link.courses')];
        }

        $shop_url = BW_Credits_Bookings_MVP::shop_url();
        if ($shop_url !== '') {
            $links[] = ['url' => $shop_url, 'label' => bw_text('overview.link.topup')];
        }

        if (function_exists('wc_get_account_endpoint_url')) {
            $links[] = ['url' => wc_get_account_endpoint_url('orders'), 'label' => bw_text('overview.link.orders')];
        }

        bw_get_template('overview/links.php', ['links' => $links]);
    }
}
