<?php
if (!defined('ABSPATH')) exit;

/**
 * [bw_credits_view_overview] — Konto-Übersicht.
 *
 * Guthaben, nächster gebuchter Termin und Einstiegslinks in einem Block.
 * Gedacht fürs WooCommerce-Konto-Dashboard.
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

        $user_id = get_current_user_id();

        // data-bw-balance wird vom JS aktualisiert — Skript und Styles nötig
        BW_Credits_Bookings_MVP::ensure_assets();

        ob_start();
        echo '<div class="bw-overview">';

        if (filter_var($atts['show_balance'], FILTER_VALIDATE_BOOLEAN)) {
            self::render_balance($user_id);
        }

        if (filter_var($atts['show_next'], FILTER_VALIDATE_BOOLEAN)) {
            self::render_next($user_id);
        }

        if (filter_var($atts['show_links'], FILTER_VALIDATE_BOOLEAN)) {
            self::render_links($atts['list_url']);
        }

        echo '</div>';
        return ob_get_clean();
    }

    private static function render_balance(int $user_id) {
        $available = BW_Credits_Bookings_MVP::get_available_credits($user_id);
        ?>
        <div class="bw-overview__balance">
            <span class="bw-overview__count" data-bw-balance><?php echo (int) $available; ?></span>
            <span class="bw-overview__label"><?php echo $available === 1 ? 'Credit verfügbar' : 'Credits verfügbar'; ?></span>
        </div>
        <?php
    }

    /**
     * Nächster anstehender Termin. Geht die Buchungen durch und nimmt den
     * frühesten der noch bevorsteht — die Startzeit liegt in Postmeta,
     * lässt sich also nicht direkt in der Buchungsabfrage sortieren.
     */
    private static function render_next(int $user_id) {
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

        if ($next === null) {
            echo '<p class="bw-overview__next bw-overview__next--none">Aktuell kein Kurs gebucht.</p>';
            return;
        }
        ?>
        <div class="bw-overview__next">
            <span class="bw-overview__next-label">Dein nächster Kurs</span>
            <a class="bw-overview__next-title" href="<?php echo esc_url(get_permalink($next['slot_id'])); ?>">
                <?php echo esc_html(get_the_title($next['slot_id'])); ?>
            </a>
            <span class="bw-overview__next-time">
                <?php echo esc_html(wp_date('l, j. F, H:i', $next['ts'])); ?>
            </span>

            <?php echo BW_View_Access::render(['course_id' => $next['slot_id'], 'title' => '']); ?>
        </div>
        <?php
    }

    private static function render_links(string $list_url) {
        $links = [];

        if ($list_url !== '') {
            $links[] = ['url' => $list_url, 'label' => 'Kurstermine ansehen'];
        }

        if (function_exists('wc_get_account_endpoint_url')) {
            $links[] = ['url' => wc_get_account_endpoint_url('orders'), 'label' => 'Meine Bestellungen'];
        }

        if (empty($links)) return;

        echo '<ul class="bw-overview__links">';
        foreach ($links as $link) {
            printf(
                '<li><a href="%s">%s</a></li>',
                esc_url($link['url']),
                esc_html($link['label'])
            );
        }
        echo '</ul>';
    }
}
