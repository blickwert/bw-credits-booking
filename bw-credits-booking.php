<?php
/**
 * Plugin Name: BW Credits + Bookings (MVP)
 * Description: WooCommerce credits (1 credit = 1 row) + course_slot bookings table with capacity, FIFO expiry, cancel policy. Includes safe frontend book/cancel buttons (REST + nonce).
 * Version: 0.4.0
 * Author: Blickwert
 */

if (!defined('ABSPATH')) exit;

class BW_Credits_Bookings_MVP {
    const CREDITS_TABLE      = 'bwallet_credits';
    const BOOKINGS_TABLE     = 'bwallet_bookings';
    const OPT_CUTOFF_HOURS   = 'bw_booking_cancel_cutoff_hours';

    // Meta keys on course_slot posts
    const META_START_DT      = 'start_datetime';
    const META_CAPACITY      = 'capacity';
    const META_BOOKED_CNT    = 'booked_count';

    // Product meta keys
    const PM_CREDIT_AMOUNT   = '_bw_credit_amount';
    const PM_VALID_DAYS      = '_bw_credit_valid_days';

    public static function init() {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);

        add_action('woocommerce_order_status_completed', [__CLASS__, 'handle_order_completed'], 10, 1);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);

        // Frontend shortcodes
        add_shortcode('bw_book_button', [__CLASS__, 'sc_book_button']);
        add_shortcode('bw_cancel_button', [__CLASS__, 'sc_cancel_button']);
        add_shortcode('bw_balance_inline', [__CLASS__, 'sc_balance_inline']);
        add_shortcode('bw_credits_balance', [__CLASS__, 'sc_balance']); // legacy display

        add_shortcode('bw_my_bookings', [__CLASS__, 'sc_my_bookings']);

        // Quick demo/testing shortcodes (optional)
        add_shortcode('bw_demo_book_slot', [__CLASS__, 'sc_demo_book_slot']);
        add_shortcode('bw_demo_cancel_booking', [__CLASS__, 'sc_demo_cancel_booking']);
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $credits  = $wpdb->prefix . self::CREDITS_TABLE;
        $bookings = $wpdb->prefix . self::BOOKINGS_TABLE;

        // Credits table: 1 credit = 1 row
        $sql1 = "CREATE TABLE {$credits} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED NULL,
            order_item_id BIGINT(20) UNSIGNED NULL,
            product_id BIGINT(20) UNSIGNED NULL,
            expires_at DATETIME NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'available',
            booking_id BIGINT(20) UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_status_expires (user_id, status, expires_at),
            KEY user_status (user_id, status),
            KEY booking_id (booking_id),
            KEY order_item (order_item_id),
            KEY order_id (order_id)
        ) {$charset_collate};";

        // Bookings table
        $sql2 = "CREATE TABLE {$bookings} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            slot_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED NULL,
            order_item_id BIGINT(20) UNSIGNED NULL,
            credit_id BIGINT(20) UNSIGNED NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cancelled_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_active_user_slot (user_id, slot_id, is_active),
            KEY slot_active (slot_id, is_active),
            KEY user_active (user_id, is_active),
            KEY credit_id (credit_id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta($sql1);
        dbDelta($sql2);

        if (get_option(self::OPT_CUTOFF_HOURS) === false) {
            add_option(self::OPT_CUTOFF_HOURS, 24);
        }
    }

    /* -------------------------
     * Frontend assets
     * ------------------------- */

    public static function enqueue_frontend_assets() {
        if (!is_user_logged_in()) return;

        // Optional: only load if page contains our shortcodes
        if (is_singular()) {
            $post = get_post();
            if ($post) {
                $c = (string) $post->post_content;
                $needs = (
                    strpos($c, '[bw_book_button') !== false ||
                    strpos($c, '[bw_cancel_button') !== false ||
                    strpos($c, '[bw_balance_inline') !== false ||
                    strpos($c, '[bw_my_bookings') !== false
                );
                if (!$needs) return;
            }
        }

        $js_handle  = 'bw-bwallet-frontend';
        $css_handle = 'bw-bwallet-frontend';

        $js_src  = plugin_dir_url(__FILE__) . 'assets/bwallet-frontend.js';
        $css_src = plugin_dir_url(__FILE__) . 'assets/bwallet-frontend.css';

        wp_enqueue_script($js_handle, $js_src, [], '0.4.0', true);
        wp_enqueue_style($css_handle, $css_src, [], '0.4.0');

        wp_localize_script($js_handle, 'BW_BWALLET', [
            'restUrl' => esc_url_raw(rest_url('bw-credits/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    /* -------------------------
     * Woo: Order -> Credits
     * ------------------------- */

    /**
     * Product meta:
     *  - _bw_credit_amount (int)
     *  - _bw_credit_valid_days (int; 0/empty = unlimited)
     */
    public static function handle_order_completed($order_id) {
        if (!class_exists('WC_Order')) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $user_id = (int) $order->get_user_id();
        if ($user_id <= 0) return;

        if ($order->get_meta('_bw_credits_processed') === 'yes') {
            return;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = (int) $item->get_product_id();
            if ($product_id <= 0) continue;

            $credit_amount = (int) get_post_meta($product_id, self::PM_CREDIT_AMOUNT, true);
            $valid_days    = (int) get_post_meta($product_id, self::PM_VALID_DAYS, true);

            if ($credit_amount <= 0) continue;

            $expires_at = null;
            if ($valid_days > 0) {
                $dt = new DateTime('now', wp_timezone());
                $dt->modify('+' . $valid_days . ' days');
                $expires_at = $dt->format('Y-m-d H:i:s');
            }

            self::add_credit_units([
                'user_id'       => $user_id,
                'order_id'      => (int) $order_id,
                'order_item_id' => (int) $item_id,
                'product_id'    => $product_id,
                'expires_at'    => $expires_at,
                'amount'        => $credit_amount,
            ]);
        }

        $order->update_meta_data('_bw_credits_processed', 'yes');
        $order->save();
    }

    private static function add_credit_units(array $args) {
        global $wpdb;
        $table = $wpdb->prefix . self::CREDITS_TABLE;

        $user_id       = (int) ($args['user_id'] ?? 0);
        $order_id      = isset($args['order_id']) ? (int) $args['order_id'] : null;
        $order_item_id = isset($args['order_item_id']) ? (int) $args['order_item_id'] : null;
        $product_id    = isset($args['product_id']) ? (int) $args['product_id'] : null;
        $expires_at    = $args['expires_at'] ?? null;
        $amount        = (int) ($args['amount'] ?? 0);

        if ($user_id <= 0 || $amount <= 0) return false;

        for ($i=0; $i<$amount; $i++) {
            $wpdb->insert($table, [
                'user_id'       => $user_id,
                'order_id'      => $order_id,
                'order_item_id' => $order_item_id,
                'product_id'    => $product_id,
                'expires_at'    => $expires_at,
                'status'        => 'available',
                'booking_id'    => null,
            ], ['%d','%d','%d','%d','%s','%s','%s']);
        }
        return true;
    }

    /* -------------------------
     * Credits: balance + consume/refund
     * ------------------------- */

    public static function get_available_credits(int $user_id): int {
        global $wpdb;
        $table = $wpdb->prefix . self::CREDITS_TABLE;
        $now = (new DateTime('now', wp_timezone()))->format('Y-m-d H:i:s');

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE user_id = %d
               AND status = 'available'
               AND (expires_at IS NULL OR expires_at > %s)",
            $user_id, $now
        ));
    }

    /**
     * Atomically consume 1 credit for booking_id (FIFO by expiry, unlimited last).
     * Must be called inside an open SQL transaction.
     */
    private static function consume_one_credit(int $user_id, int $booking_id) {
        global $wpdb;
        $table = $wpdb->prefix . self::CREDITS_TABLE;

        $now = (new DateTime('now', wp_timezone()))->format('Y-m-d H:i:s');

        $credit_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE user_id = %d
               AND status = 'available'
               AND (expires_at IS NULL OR expires_at > %s)
             ORDER BY (expires_at IS NULL) ASC, expires_at ASC, id ASC
             LIMIT 1
             FOR UPDATE",
            $user_id, $now
        ));

        if ($credit_id <= 0) {
            return new WP_Error('bw_no_credits', 'No available credits.');
        }

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status='used', booking_id=%d
             WHERE id=%d AND status='available'",
            $booking_id, $credit_id
        ));

        if ($updated !== 1) {
            return new WP_Error('bw_race_credit', 'Could not reserve credit. Please try again.');
        }

        return $credit_id;
    }

    private static function refund_credit_by_booking(int $user_id, int $booking_id) {
        global $wpdb;
        $table = $wpdb->prefix . self::CREDITS_TABLE;

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status='available', booking_id=NULL
             WHERE user_id=%d AND booking_id=%d AND status='used'",
            $user_id, $booking_id
        ));

        if ($updated !== 1) {
            return new WP_Error('bw_no_credit_for_booking', 'No used credit found for this booking.');
        }

        return true;
    }

    /* -------------------------
     * Slots: capacity + booked_count (system-owned)
     * ------------------------- */

    private static function get_slot_capacity(int $slot_id): int {
        $cap = (int) get_post_meta($slot_id, self::META_CAPACITY, true);
        return max(0, $cap);
    }

    private static function get_slot_start_datetime(int $slot_id): ?DateTime {
        $raw = get_post_meta($slot_id, self::META_START_DT, true);
        if (!$raw) return null;

        $tz = wp_timezone();

        // ACF return_format is Y-m-d H:i:s — try that first
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $raw, $tz);
        if ($dt instanceof DateTime) return $dt;

        $dt = DateTime::createFromFormat('Y-m-d H:i', $raw, $tz);
        if ($dt instanceof DateTime) return $dt;

        try {
            return new DateTime($raw, $tz);
        } catch (Exception $e) {
            return null;
        }
    }

    private static function ensure_booked_count_exists(int $slot_id) {
        $val = get_post_meta($slot_id, self::META_BOOKED_CNT, true);
        if ($val === '' || $val === null) {
            update_post_meta($slot_id, self::META_BOOKED_CNT, 0);
        }
    }

    /**
     * Try to increment booked_count if below capacity (row-level locking via postmeta).
     * Must be called inside a transaction.
     */
    private static function try_increment_booked_count(int $slot_id, int $capacity): bool {
        global $wpdb;
        $pm = $wpdb->postmeta;

        self::ensure_booked_count_exists($slot_id);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT meta_id, meta_value
             FROM {$pm}
             WHERE post_id=%d AND meta_key=%s
             LIMIT 1
             FOR UPDATE",
            $slot_id, self::META_BOOKED_CNT
        ), ARRAY_A);

        if (!$row) return false;

        $current = (int) $row['meta_value'];
        if ($capacity > 0 && $current >= $capacity) {
            return false;
        }

        $new = $current + 1;

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$pm}
             SET meta_value=%s
             WHERE meta_id=%d",
            (string)$new, (int)$row['meta_id']
        ));

        return $updated === 1;
    }

    /**
     * Decrement booked_count if > 0. Must be called inside a transaction.
     */
    private static function decrement_booked_count(int $slot_id): bool {
        global $wpdb;
        $pm = $wpdb->postmeta;

        self::ensure_booked_count_exists($slot_id);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT meta_id, meta_value
             FROM {$pm}
             WHERE post_id=%d AND meta_key=%s
             LIMIT 1
             FOR UPDATE",
            $slot_id, self::META_BOOKED_CNT
        ), ARRAY_A);

        if (!$row) return false;

        $current = (int) $row['meta_value'];
        $new = max(0, $current - 1);

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$pm}
             SET meta_value=%s
             WHERE meta_id=%d",
            (string)$new, (int)$row['meta_id']
        ));

        return $updated === 1;
    }

    /* -------------------------
     * Bookings: create/cancel
     * ------------------------- */

    public static function book_slot(int $user_id, int $slot_id) {
        global $wpdb;

        if ($user_id <= 0 || $slot_id <= 0) {
            return new WP_Error('bw_invalid', 'Invalid user or slot.');
        }

        $post = get_post($slot_id);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_Error('bw_slot_invalid', 'Slot not found or not published.');
        }

        $capacity = self::get_slot_capacity($slot_id);
        if ($capacity <= 0) {
            return new WP_Error('bw_capacity_missing', 'Slot capacity missing or zero.');
        }

        $start = self::get_slot_start_datetime($slot_id);
        if ($start && $start <= new DateTime('now', wp_timezone())) {
            return new WP_Error('bw_slot_past', 'Dieser Termin liegt in der Vergangenheit.');
        }

        $bookings_table = $wpdb->prefix . self::BOOKINGS_TABLE;

        $wpdb->query('START TRANSACTION');

        // prevent double active booking (unique index also protects)
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$bookings_table}
             WHERE user_id=%d AND slot_id=%d AND is_active=1
             LIMIT 1
             FOR UPDATE",
            $user_id, $slot_id
        ));
        if ($existing > 0) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_already_booked', 'You already booked this slot.');
        }

        // capacity + increment booked_count atomically
        $ok = self::try_increment_booked_count($slot_id, $capacity);
        if (!$ok) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_full', 'Slot is full.');
        }

        // insert booking pending
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$bookings_table}
                (user_id, slot_id, status, is_active, created_at)
             VALUES
                (%d, %d, %s, 1, %s)",
            $user_id, $slot_id, 'pending',
            (new DateTime('now', wp_timezone()))->format('Y-m-d H:i:s')
        ));

        if ($inserted !== 1) {
            self::decrement_booked_count($slot_id);
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_booking_insert_failed', 'Could not create booking.');
        }

        $booking_id = (int) $wpdb->insert_id;

        // consume credit + link to booking
        $credit_id = self::consume_one_credit($user_id, $booking_id);
        if (is_wp_error($credit_id)) {
            self::decrement_booked_count($slot_id);
            $wpdb->query($wpdb->prepare("DELETE FROM {$bookings_table} WHERE id=%d", $booking_id));
            $wpdb->query('ROLLBACK');
            return $credit_id;
        }

        // finalize booking
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$bookings_table}
             SET status=%s, credit_id=%d
             WHERE id=%d",
            'booked', (int)$credit_id, $booking_id
        ));

        if ($updated !== 1) {
            self::refund_credit_by_booking($user_id, $booking_id);
            self::decrement_booked_count($slot_id);
            $wpdb->query($wpdb->prepare("DELETE FROM {$bookings_table} WHERE id=%d", $booking_id));
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_booking_finalize_failed', 'Could not finalize booking.');
        }

        $wpdb->query('COMMIT');

        return [
            'booking_id' => $booking_id,
            'credit_id'  => (int)$credit_id,
            'slot_id'    => $slot_id,
            'status'     => 'booked'
        ];
    }

    public static function cancel_booking(int $user_id, int $booking_id) {
        global $wpdb;

        $bookings_table = $wpdb->prefix . self::BOOKINGS_TABLE;

        if ($user_id <= 0 || $booking_id <= 0) {
            return new WP_Error('bw_invalid', 'Invalid user or booking.');
        }

        $cutoff_hours = (int) get_option(self::OPT_CUTOFF_HOURS, 24);
        $now = new DateTime('now', wp_timezone());

        $wpdb->query('START TRANSACTION');

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bookings_table}
             WHERE id=%d AND user_id=%d
             LIMIT 1
             FOR UPDATE",
            $booking_id, $user_id
        ), ARRAY_A);

        if (!$booking) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_booking_not_found', 'Booking not found.');
        }

        if ((int)$booking['is_active'] !== 1 || $booking['status'] !== 'booked') {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_not_active', 'Booking is not active.');
        }

        $slot_id = (int) $booking['slot_id'];

        $start = self::get_slot_start_datetime($slot_id);
        if (!$start) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_slot_time_missing', 'Slot start time missing.');
        }

        $cutoff = clone $start;
        $cutoff->modify('-' . max(0, $cutoff_hours) . ' hours');

        if ($now >= $cutoff) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_cutoff_passed', 'Cancellation cutoff passed.');
        }

        $cancelled_at = $now->format('Y-m-d H:i:s');
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$bookings_table}
             SET status=%s, is_active=0, cancelled_at=%s
             WHERE id=%d",
            'cancelled', $cancelled_at, $booking_id
        ));

        if ($updated !== 1) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_cancel_failed', 'Could not cancel booking.');
        }

        $ref = self::refund_credit_by_booking($user_id, $booking_id);
        if (is_wp_error($ref)) {
            $wpdb->query('ROLLBACK');
            return $ref;
        }

        $ok = self::decrement_booked_count($slot_id);
        if (!$ok) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_bookedcount_failed', 'Could not update booked_count.');
        }

        $wpdb->query('COMMIT');

        return [
            'ok' => true,
            'booking_id' => $booking_id,
            'status' => 'cancelled'
        ];
    }

    public static function get_my_bookings(int $user_id, int $limit = 50): array {
        global $wpdb;
        $bookings_table = $wpdb->prefix . self::BOOKINGS_TABLE;

        $limit = max(1, min(200, $limit));

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT id, slot_id, status, is_active, credit_id, created_at, cancelled_at
             FROM {$bookings_table}
             WHERE user_id=%d
             ORDER BY created_at DESC
             LIMIT %d",
            $user_id, $limit
        ), ARRAY_A);
    }

    /* -------------------------
     * REST API
     * ------------------------- */

    public static function register_rest_routes() {
        register_rest_route('bw-credits/v1', '/balance', [
            'methods' => 'GET',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => function() {
                $uid = get_current_user_id();
                return [
                    'user_id'   => $uid,
                    'available' => self::get_available_credits($uid),
                ];
            }
        ]);

        register_rest_route('bw-credits/v1', '/book', [
            'methods' => 'POST',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => function(WP_REST_Request $req) {
                $uid = get_current_user_id();
                $slot_id = (int) $req->get_param('slot_id');
                $res = self::book_slot($uid, $slot_id);
                if (is_wp_error($res)) {
                    return new WP_REST_Response(['error' => $res->get_message()], 400);
                }
                return $res;
            }
        ]);

        register_rest_route('bw-credits/v1', '/cancel', [
            'methods' => 'POST',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => function(WP_REST_Request $req) {
                $uid = get_current_user_id();
                $booking_id = (int) $req->get_param('booking_id');
                $res = self::cancel_booking($uid, $booking_id);
                if (is_wp_error($res)) {
                    return new WP_REST_Response(['error' => $res->get_message()], 400);
                }
                return $res;
            }
        ]);

        register_rest_route('bw-credits/v1', '/my-bookings', [
            'methods' => 'GET',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => function(WP_REST_Request $req) {
                $uid = get_current_user_id();
                $limit = (int) $req->get_param('limit');
                return [
                    'user_id'  => $uid,
                    'bookings' => self::get_my_bookings($uid, $limit ?: 50),
                ];
            }
        ]);
    }

    /* -------------------------
     * Shortcodes
     * ------------------------- */

    // Display balance (block)
    public static function sc_balance() {
        if (!is_user_logged_in()) return '<p>Bitte einloggen.</p>';
        $uid = get_current_user_id();
        return '<p>Verfügbare Credits: <strong>' . esc_html(self::get_available_credits($uid)) . '</strong></p>';
    }

    // Inline balance span (auto-updated by JS)
    public static function sc_balance_inline() {
        if (!is_user_logged_in()) return '';
        $uid = get_current_user_id();
        $available = self::get_available_credits($uid);
        return '<span data-bw-balance>' . esc_html($available) . '</span>';
    }

    // [bw_book_button slot_id="123" label="Kurs buchen (1 Credit)"]
    public static function sc_book_button($atts) {
        if (!is_user_logged_in()) return '';

        $atts = shortcode_atts([
            'slot_id' => 0,
            'label'   => 'Kurs buchen (1 Credit)',
            'wrap'    => '1',
            'class'   => 'bw-bwallet-btn',
        ], $atts);

        $slot_id = (int) $atts['slot_id'];
        if ($slot_id <= 0) return '';

        $btn = sprintf(
            '<button type="button" class="%s" data-bw-action="book" data-slot-id="%d">%s</button>',
            esc_attr($atts['class']),
            $slot_id,
            esc_html($atts['label'])
        );

        if ($atts['wrap'] === '0') return $btn;

        return '<div data-bw-wrap="1">' . $btn . '<div class="bw-bwallet-msg" data-bw-msg></div></div>';
    }

    // [bw_cancel_button booking_id="456" label="Stornieren"]
    public static function sc_cancel_button($atts) {
        if (!is_user_logged_in()) return '';

        $atts = shortcode_atts([
            'booking_id' => 0,
            'slot_id'    => 0,
            'label'      => 'Stornieren',
            'wrap'       => '1',
            'class'      => 'bw-bwallet-btn',
        ], $atts);

        $booking_id = (int) $atts['booking_id'];
        if ($booking_id <= 0) return '';

        $slot_id = (int) $atts['slot_id'];

        $btn = sprintf(
            '<button type="button" class="%s" data-bw-action="cancel" data-booking-id="%d"%s>%s</button>',
            esc_attr($atts['class']),
            $booking_id,
            $slot_id ? ' data-slot-id="'.(int)$slot_id.'"' : '',
            esc_html($atts['label'])
        );

        if ($atts['wrap'] === '0') return $btn;

        return '<div data-bw-wrap="1">' . $btn . '<div class="bw-bwallet-msg" data-bw-msg></div></div>';
    }

    // [bw_my_bookings limit="20"]
    public static function sc_my_bookings($atts) {
        if (!is_user_logged_in()) return '<p>Bitte einloggen.</p>';

        $atts       = shortcode_atts(['limit' => 20], $atts);
        $uid        = get_current_user_id();
        $bookings   = self::get_my_bookings($uid, (int) $atts['limit']);

        if (empty($bookings)) {
            return '<p class="bw-no-bookings">Noch keine Buchungen vorhanden.</p>';
        }

        $cutoff_hours = (int) get_option(self::OPT_CUTOFF_HOURS, 24);
        $now          = new DateTime('now', wp_timezone());

        $status_labels = [
            'booked'    => 'Gebucht',
            'cancelled' => 'Storniert',
            'pending'   => 'Ausstehend',
        ];

        ob_start();
        echo '<div class="bw-my-bookings">';

        foreach ($bookings as $b) {
            $slot_id    = (int) $b['slot_id'];
            $booking_id = (int) $b['id'];
            $status     = $b['status'];
            $is_active  = (int) $b['is_active'];

            $slot_title   = get_the_title($slot_id) ?: 'Slot #' . $slot_id;
            $start_dt     = self::get_slot_start_datetime($slot_id);
            $start_str    = $start_dt ? $start_dt->format('d.m.Y H:i') : '—';

            $can_cancel = false;
            if ($is_active && $status === 'booked' && $start_dt) {
                $cutoff = clone $start_dt;
                $cutoff->modify('-' . $cutoff_hours . ' hours');
                $can_cancel = $now < $cutoff;
            }

            $status_label = $status_labels[$status] ?? ucfirst($status);

            echo '<div class="bw-booking-item bw-status-' . esc_attr($status) . '">';
            echo '<div class="bw-booking-slot">' . esc_html($slot_title) . '</div>';
            echo '<div class="bw-booking-time">' . esc_html($start_str) . '</div>';
            echo '<div class="bw-booking-status">' . esc_html($status_label) . '</div>';

            if ($can_cancel) {
                echo do_shortcode('[bw_cancel_button booking_id="' . $booking_id . '" slot_id="' . $slot_id . '"]');
            }

            echo '</div>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    // Demo: [bw_demo_book_slot slot_id="123"]
    public static function sc_demo_book_slot($atts) {
        if (!is_user_logged_in()) return '<p>Bitte einloggen.</p>';
        $atts = shortcode_atts(['slot_id' => 0], $atts);
        $slot_id = (int) $atts['slot_id'];
        if ($slot_id <= 0) return '<p>slot_id fehlt.</p>';

        $uid = get_current_user_id();
        $res = self::book_slot($uid, $slot_id);
        if (is_wp_error($res)) {
            return '<p>❌ ' . esc_html($res->get_message()) . '</p>';
        }
        return '<p>✅ Gebucht. booking_id=' . esc_html($res['booking_id']) . ', credit_id=' . esc_html($res['credit_id']) . '</p>';
    }

    // Demo: [bw_demo_cancel_booking booking_id="123"]
    public static function sc_demo_cancel_booking($atts) {
        if (!is_user_logged_in()) return '<p>Bitte einloggen.</p>';
        $atts = shortcode_atts(['booking_id' => 0], $atts);
        $booking_id = (int) $atts['booking_id'];
        if ($booking_id <= 0) return '<p>booking_id fehlt.</p>';

        $uid = get_current_user_id();
        $res = self::cancel_booking($uid, $booking_id);
        if (is_wp_error($res)) {
            return '<p>❌ ' . esc_html($res->get_message()) . '</p>';
        }
        return '<p>✅ Storniert. booking_id=' . esc_html($booking_id) . '</p>';
    }
}

BW_Credits_Bookings_MVP::init();