<?php
if (!defined('ABSPATH')) exit;

/**
 * [bw_credits_view_overview] — account overview.
 *
 * Credit balance, upcoming sessions (reused from the session list,
 * including availability and a book/cancel button), and entry-point
 * links, all in one block. Intended for the WooCommerce account
 * dashboard. Markup lives in
 * templates/view_overview/view_overview.php, overridable in the theme.
 */

class BW_View_Overview {

    public static function render($atts) {
        $atts = shortcode_atts([
            'show_balance' => 'true',
            'show_next'    => 'true',
            'show_links'   => 'true',
            'list_url'     => '',
            'next_limit'   => 5,
        ], $atts, 'bw_credits_view_overview');

        if (!is_user_logged_in()) return '';

        // data-bw-balance is updated by the JS — script and styles are needed
        BW_Credits_Bookings_MVP::ensure_assets();

        $user_id       = get_current_user_id();
        $show_balance  = filter_var($atts['show_balance'], FILTER_VALIDATE_BOOLEAN);
        $available     = $show_balance ? BW_Credits_Bookings_MVP::get_available_credits($user_id) : 0;

        ob_start();
        bw_get_template('view_overview/view_overview.php', [
            'show_balance'  => $show_balance,
            'available'     => $available,
            'balance_label' => bw_text($available === 1 ? 'balance.count.one' : 'balance.count.many'),
            'show_next'     => filter_var($atts['show_next'], FILTER_VALIDATE_BOOLEAN),
            'next_limit'    => max(1, (int) $atts['next_limit']),
            'show_links'    => filter_var($atts['show_links'], FILTER_VALIDATE_BOOLEAN),
            'links'         => self::build_links((string) $atts['list_url']),
        ]);
        return ob_get_clean();
    }

    private static function build_links(string $list_url): array {
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

        return $links;
    }
}
