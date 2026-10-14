<?php
if (!defined('ABSPATH')) exit;

/**
 * Time zones: sessions run in the site's time zone (Settings → General), customers live elsewhere.
 *
 *  - Times shown to customers say which time zone they are in ("08:30 (Madrid time)").
 *  - On the website the browser adds the visitor's own time when it differs (assets/base.js, on <time class="bw-time">).
 *  - Emails are built on the server — no browser, no Ajax — so the customer's time zone is stored:
 *    detected by the browser (checkout, once per login) and changeable in the account; a customer's own
 *    choice is never overwritten by detection.
 */
class BW_Time_Zone {

    const META_TZ     = '_bw_timezone';
    const META_MANUAL = '_bw_timezone_manual';
    const FIELD       = 'bw_timezone';

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_route']);

        add_action('woocommerce_edit_account_form', [__CLASS__, 'render_account_field']);
        add_action('woocommerce_save_account_details', [__CLASS__, 'save_account_field']);

        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'save_from_checkout'], 10, 1);
        add_action('wp_footer', [__CLASS__, 'checkout_script'], 60);
    }

    /* =========================================================
     * The site's time zone
     * ========================================================= */

    /** "Madrid" for Europe/Madrid, "UTC+02:00" for an offset, else the raw string. */
    public static function site_city(): string {
        $zone = (string) wp_timezone_string();
        if (strpos($zone, '/') !== false) {
            return str_replace('_', ' ', substr($zone, strrpos($zone, '/') + 1));
        }
        return preg_match('/^[+-]\d/', $zone) ? 'UTC' . $zone : $zone;
    }

    /** "Madrid time" / "Madrid-Zeit" — from the text catalogue. */
    public static function site_label(): string {
        return bw_text('time.zone_label', ['city' => self::site_city()]);
    }

    /** <time> for a session start — the browser reads datetime (with offset) to add the visitor's time. */
    public static function time_tag(DateTime $start, string $inner_html, string $class = ''): string {
        return '<time class="bw-time' . ($class !== '' ? ' ' . esc_attr($class) : '') . '" datetime="' . esc_attr($start->format('c')) . '"'
             . ' title="' . esc_attr(wp_date('H:i', $start->getTimestamp()) . ' (' . self::site_label() . ')') . '">'
             . $inner_html . '</time>';
    }

    /** Visible zone name next to a time. */
    public static function zone_html(): string {
        return ' <span class="bw-time__zone">(' . esc_html(self::site_label()) . ')</span>';
    }

    /* =========================================================
     * The customer's time zone
     * ========================================================= */

    public static function valid(string $zone): bool {
        return $zone !== '' && in_array($zone, timezone_identifiers_list(), true);
    }

    public static function get_user_timezone(int $user_id): string {
        if ($user_id <= 0) return '';
        $zone = (string) get_user_meta($user_id, self::META_TZ, true);
        return self::valid($zone) ? $zone : '';
    }

    /** Detection ($manual = false) never overwrites a zone the customer chose. */
    public static function save_user_timezone(int $user_id, string $zone, bool $manual = false): bool {
        if ($user_id <= 0 || !self::valid($zone)) return false;
        if (!$manual && get_user_meta($user_id, self::META_MANUAL, true)) return false;

        update_user_meta($user_id, self::META_TZ, $zone);
        if ($manual) update_user_meta($user_id, self::META_MANUAL, 1);
        return true;
    }

    /** "07:30 (Europe/London)" — the session time in the customer's zone; '' when it is the same clock time. */
    public static function local_time(DateTime $start, string $zone): string {
        if (!self::valid($zone)) return '';

        $local = (clone $start)->setTimezone(new DateTimeZone($zone));
        if ($local->getOffset() === $start->getOffset()) return '';

        $format = $local->format('Y-m-d') !== $start->format('Y-m-d') ? 'd.m.Y H:i' : 'H:i';
        return $local->format($format) . ' (' . $zone . ')';
    }

    /** "08:30 (Madrid time)", plus "· 07:30 in your time zone (Europe/London)" when the customer's zone is known and differs. */
    public static function time_with_zone(DateTime $start, int $user_id = 0): string {
        $text  = wp_date('H:i', $start->getTimestamp()) . ' (' . self::site_label() . ')';
        $local = self::local_time($start, self::get_user_timezone($user_id));
        if ($local === '') return $text;

        [$time, $zone] = array_pad(explode(' (', rtrim($local, ')'), 2), 2, '');
        return $text . ' · ' . bw_text('time.local', ['time' => $time, 'zone' => $zone]);
    }

    /** Date and time with the zone name, for deadlines ("23.10.2026 08:30 (Madrid time)"). */
    public static function datetime_with_zone(DateTime $moment): string {
        return wp_date('d.m.Y H:i', $moment->getTimestamp()) . ' (' . self::site_label() . ')';
    }

    /* =========================================================
     * Detection: REST (logged in) and checkout (account created there)
     * ========================================================= */

    public static function register_route() {
        register_rest_route('bw-credits/v1', '/timezone', [
            'methods'             => 'POST',
            'permission_callback' => function () { return is_user_logged_in(); },
            'callback'            => [__CLASS__, 'rest_save'],
        ]);
    }

    public static function rest_save($req) {
        $zone = (string) $req->get_param('zone');
        $ok   = self::save_user_timezone(get_current_user_id(), $zone, false);
        $res  = new WP_REST_Response(['saved' => $ok]);
        $res->header('Cache-Control', 'no-store, private, max-age=0');
        return $res;
    }

    public static function save_from_checkout($order_id) {
        $order   = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        $user_id = $order && method_exists($order, 'get_user_id') ? (int) $order->get_user_id() : 0;
        $zone    = isset($_POST[self::FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::FIELD])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        if ($user_id > 0 && $zone !== '') self::save_user_timezone($user_id, $zone, false);
    }

    /** Adds the browser's zone to the checkout form (the account is created there). */
    public static function checkout_script() {
        if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return;
        ?>
<script id="bw-timezone-checkout">
(function(){
	try {
		var zone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
		var form = document.querySelector('form.checkout');
		if (!zone || !form || form.querySelector('input[name="<?php echo esc_js(self::FIELD); ?>"]')) { return; }
		var input = document.createElement('input');
		input.type = 'hidden'; input.name = '<?php echo esc_js(self::FIELD); ?>'; input.value = zone;
		form.appendChild(input);
	} catch (e) {}
})();
</script>
        <?php
    }

    /* =========================================================
     * Account: change the time zone
     * ========================================================= */

    public static function render_account_field() {
        $user_id = get_current_user_id();
        $current = self::get_user_timezone($user_id);
        ?>
        <p class="form-row form-row-wide">
            <label for="bw_timezone"><?php esc_html_e('Time zone', 'bw-credits-booking'); ?></label>
            <select name="<?php echo esc_attr(self::FIELD); ?>" id="bw_timezone" class="woocommerce-Input woocommerce-Input--select">
                <option value=""><?php esc_html_e('Automatic (from your browser)', 'bw-credits-booking'); ?></option>
                <?php foreach (timezone_identifiers_list() as $zone) : ?>
                    <option value="<?php echo esc_attr($zone); ?>" <?php selected($current, $zone); ?>><?php echo esc_html(str_replace('_', ' ', $zone)); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description">
            <?php esc_html_e('Session times are shown in your time zone too — on the website and in emails.', 'bw-credits-booking'); ?>
        </p>
        <?php
    }

    public static function save_account_field($user_id) {
        $zone = isset($_POST[self::FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::FIELD])) : null; // phpcs:ignore WordPress.Security.NonceVerification
        if ($zone === null) return;

        if ($zone === '') {
            delete_user_meta($user_id, self::META_MANUAL); // back to detection
            return;
        }
        self::save_user_timezone((int) $user_id, $zone, true);
    }
}

BW_Time_Zone::init();
