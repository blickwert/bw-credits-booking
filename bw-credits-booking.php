<?php
/**
 * Plugin Name: BW Credits + Bookings (MVP)
 * Description: WooCommerce credits (1 credit = 1 row) + course_slot bookings table with capacity, FIFO expiry, cancel policy. Includes safe frontend book/cancel buttons (REST + nonce).
 * Version: 0.13.0
 * Author: Blickwert
 * Text Domain: bw-credits-booking
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('BW_CREDITS_BOOKING_FILE', __FILE__);
define('BW_CREDITS_BOOKING_VERSION', '0.13.0');

require_once plugin_dir_path(__FILE__) . 'includes/text.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/metaboxes.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-pages.php';
require_once plugin_dir_path(__FILE__) . 'includes/emails.php';
require_once plugin_dir_path(__FILE__) . 'includes/templates.php';
require_once plugin_dir_path(__FILE__) . 'includes/course-list.php';
require_once plugin_dir_path(__FILE__) . 'includes/views/access.php';
require_once plugin_dir_path(__FILE__) . 'includes/views/credits.php';
require_once plugin_dir_path(__FILE__) . 'includes/views/overview.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/membership.php';
require_once plugin_dir_path(__FILE__) . 'includes/updater.php';

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
    const PM_CREDIT_SOURCE   = '_bw_credit_source';

    const DB_VERSION         = 3;

    // 'manual' = Gutschrift durch den Admin (Barzahlung, Kulanz, Korrektur)
    const CREDIT_SOURCES     = ['purchase', 'membership', 'manual'];

    public static function init() {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);
        add_action('plugins_loaded', [__CLASS__, 'maybe_migrate']);

        add_action('woocommerce_order_status_completed', [__CLASS__, 'handle_order_completed'], 10, 1);

        // Rückerstattung/Storno der Bestellung entwertet noch nicht genutzte Credits
        add_action('woocommerce_order_status_refunded',  [__CLASS__, 'handle_order_reversed'], 10, 1);
        add_action('woocommerce_order_status_cancelled', [__CLASS__, 'handle_order_reversed'], 10, 1);

        // Buchungen im WooCommerce-Konto-Dashboard
        add_action('woocommerce_account_dashboard', [__CLASS__, 'render_account_dashboard'], 20);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_frontend_assets']);
        add_action('wp_ajax_bw_refresh_nonce', [__CLASS__, 'ajax_refresh_nonce']);

        // Frontend-Shortcodes werden zentral in includes/shortcodes.php registriert

        // Demo-/Testhilfen
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
            source VARCHAR(20) NOT NULL DEFAULT 'purchase',
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
            is_active TINYINT(1) NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            cancelled_at DATETIME NULL,
            reminded_at DATETIME NULL,
            access_sent_at DATETIME NULL,
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

        update_option('bw_db_version', self::DB_VERSION);
    }

    public static function deactivate() {
        BW_Emails::unschedule_cron();
    }

    // Führt DB-Migration durch wenn Plugin aktualisiert wird (ohne Deaktivierung)
    public static function maybe_migrate() {
        $installed = (int) get_option('bw_db_version', 1);
        if ($installed >= self::DB_VERSION) return;

        self::activate();

        if ($installed < 3) {
            global $wpdb;
            $bookings = $wpdb->prefix . self::BOOKINGS_TABLE;
            // is_active=0 kollidiert im Unique-Index sobald derselbe Slot ein
            // zweites Mal storniert wird — NULL erlaubt beliebig viele Zeilen
            $wpdb->query("UPDATE {$bookings} SET is_active = NULL WHERE is_active = 0");
        }
    }

    /* -------------------------
     * Frontend assets
     * ------------------------- */

    /**
     * Assets nur registrieren — das Enqueue passiert im Shortcode selbst.
     * Page-Builder (Elementor, Oxygen, …) legen ihren Inhalt außerhalb von
     * post_content ab, deshalb ist Shortcode-Erkennung per Inhaltsprüfung
     * unzuverlässig.
     */
    public static function register_frontend_assets() {
        $handle = 'bw-bwallet-frontend';

        wp_register_script(
            $handle,
            plugin_dir_url(__FILE__) . 'assets/bwallet-frontend.js',
            [], BW_CREDITS_BOOKING_VERSION, true
        );

        wp_register_style(
            $handle,
            plugin_dir_url(__FILE__) . 'assets/bwallet-frontend.css',
            [], BW_CREDITS_BOOKING_VERSION
        );

        wp_localize_script($handle, 'BW_BWALLET', [
            'restUrl' => esc_url_raw(rest_url('bw-credits/v1/')),
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    public static function ensure_assets() {
        wp_enqueue_script('bw-bwallet-frontend');
        wp_enqueue_style('bw-bwallet-frontend');
    }

    /**
     * Frischen REST-Nonce liefern. Nötig weil ein in gecachtem HTML
     * eingebetteter Nonce abläuft — admin-ajax wird nie gecacht.
     */
    public static function ajax_refresh_nonce() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'not logged in'], 401);
        }
        wp_send_json_success(['nonce' => wp_create_nonce('wp_rest')]);
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
            $credit_source = get_post_meta($product_id, self::PM_CREDIT_SOURCE, true) ?: 'purchase';

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
                'source'        => $credit_source,
            ]);
        }

        $order->update_meta_data('_bw_credits_processed', 'yes');
        $order->save();
    }

    /**
     * Bestellung erstattet oder storniert — noch verfügbare Credits daraus
     * entwerten. Bereits verbrauchte bleiben unangetastet, die hängen an
     * einer Buchung; die wird bei Bedarf separat storniert.
     */
    public static function handle_order_reversed($order_id) {
        self::revoke_order_credits((int) $order_id);
    }

    public static function revoke_order_credits(int $order_id): int {
        global $wpdb;
        $table = $wpdb->prefix . self::CREDITS_TABLE;

        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = 'expired'
             WHERE order_id = %d AND status = 'available'",
            $order_id
        ));
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
        $source        = in_array($args['source'] ?? '', self::CREDIT_SOURCES, true)
                         ? $args['source']
                         : 'purchase';

        if ($user_id <= 0 || $amount <= 0) return false;

        for ($i=0; $i<$amount; $i++) {
            $wpdb->insert($table, [
                'user_id'       => $user_id,
                'order_id'      => $order_id,
                'order_item_id' => $order_item_id,
                'product_id'    => $product_id,
                'expires_at'    => $expires_at,
                'status'        => 'available',
                'source'        => $source,
                'booking_id'    => null,
            ], ['%d','%d','%d','%d','%s','%s','%s','%s']);
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
     * Credits eines Nutzers inkl. Herkunft und Buchungsbezug.
     */
    public static function get_user_credits(int $user_id, int $limit = 200): array {
        global $wpdb;
        $table = $wpdb->prefix . self::CREDITS_TABLE;
        $limit = max(1, min(500, $limit));

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT id, order_id, product_id, expires_at, status, source, booking_id, created_at
             FROM {$table}
             WHERE user_id = %d
             ORDER BY created_at DESC, id DESC
             LIMIT %d",
            $user_id, $limit
        ), ARRAY_A);
    }

    /**
     * Zählt Credits nach Status. 'available' berücksichtigt das Ablaufdatum,
     * abgelaufene aber noch nicht umgeschriebene Zeilen laufen unter 'expired'.
     */
    public static function get_credit_summary(int $user_id): array {
        global $wpdb;
        $table = $wpdb->prefix . self::CREDITS_TABLE;
        $now   = (new DateTime('now', wp_timezone()))->format('Y-m-d H:i:s');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(status = 'available' AND (expires_at IS NULL OR expires_at > %s)) AS available,
                SUM(status = 'available' AND expires_at IS NOT NULL AND expires_at <= %s) AS lapsed,
                SUM(status = 'used')     AS used,
                SUM(status = 'expired')  AS expired,
                COUNT(*)                 AS total
             FROM {$table}
             WHERE user_id = %d",
            $now, $now, $user_id
        ), ARRAY_A);

        return [
            'available' => (int) ($row['available'] ?? 0),
            'used'      => (int) ($row['used'] ?? 0),
            'expired'   => (int) ($row['expired'] ?? 0) + (int) ($row['lapsed'] ?? 0),
            'total'     => (int) ($row['total'] ?? 0),
        ];
    }

    /**
     * Manuelle Gutschrift durch den Admin.
     */
    public static function grant_credits(int $user_id, int $amount, ?string $expires_at = null, string $source = 'manual') {
        if (!get_userdata($user_id)) {
            return new WP_Error('bw_user_invalid', 'Benutzer nicht gefunden.');
        }
        if ($amount < 1 || $amount > 500) {
            return new WP_Error('bw_amount_invalid', 'Anzahl muss zwischen 1 und 500 liegen.');
        }

        $ok = self::add_credit_units([
            'user_id'    => $user_id,
            'amount'     => $amount,
            'expires_at' => $expires_at ?: null,
            'source'     => $source,
        ]);

        return $ok ? true : new WP_Error('bw_grant_failed', 'Gutschrift fehlgeschlagen.');
    }

    /**
     * Einzelnen verfügbaren Credit entwerten. Verbrauchte Credits bleiben
     * unangetastet — die hängen an einer Buchung.
     */
    public static function revoke_credit(int $credit_id, int $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . self::CREDITS_TABLE;

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = 'expired'
             WHERE id = %d AND user_id = %d AND status = 'available'",
            $credit_id, $user_id
        ));

        if ($updated !== 1) {
            return new WP_Error('bw_revoke_failed', 'Credit nicht gefunden oder bereits verbraucht.');
        }

        return true;
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
            return new WP_Error('bw_no_credits', bw_text('error.no_credits'));
        }

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status='used', booking_id=%d
             WHERE id=%d AND status='available'",
            $booking_id, $credit_id
        ));

        if ($updated !== 1) {
            return new WP_Error('bw_race_credit', bw_text('error.retry'));
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
            return new WP_Error('bw_no_credit_for_booking', bw_text('error.generic'));
        }

        return true;
    }

    /* -------------------------
     * Slots: capacity + booked_count (system-owned)
     * ------------------------- */

    private static function get_slot_capacity(int $slot_id): int {
        $raw = get_post_meta($slot_id, self::META_CAPACITY, true);
        if ($raw === '' || $raw === null) {
            return BW_Settings::get_default_capacity();
        }
        return max(0, (int) $raw);
    }

    public static function get_slot_start_datetime(int $slot_id): ?DateTime {
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

        if ($updated === 1) {
            wp_cache_delete($slot_id, 'post_meta');
        }

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

        if ($updated === 1) {
            wp_cache_delete($slot_id, 'post_meta');
        }

        return $updated === 1;
    }

    /* -------------------------
     * Bookings: create/cancel
     * ------------------------- */

    public static function book_slot(int $user_id, int $slot_id) {
        return self::create_booking($user_id, $slot_id, true, true);
    }

    /**
     * Buchung durch den Admin (Walk-in). Darf auch für bereits begonnene
     * Termine eintragen und wahlweise ohne Credit-Abzug als Freiplatz.
     */
    public static function admin_book_slot(int $user_id, int $slot_id, bool $consume_credit = true) {
        if (!get_userdata($user_id)) {
            return new WP_Error('bw_user_invalid', 'Benutzer nicht gefunden.');
        }
        return self::create_booking($user_id, $slot_id, $consume_credit, false);
    }

    private static function create_booking(int $user_id, int $slot_id, bool $consume_credit, bool $enforce_future) {
        global $wpdb;

        if ($user_id <= 0 || $slot_id <= 0) {
            return new WP_Error('bw_invalid', bw_text('error.generic'));
        }

        $post = get_post($slot_id);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_Error('bw_slot_invalid', bw_text('error.slot_invalid'));
        }

        $capacity = self::get_slot_capacity($slot_id);
        if ($capacity <= 0) {
            return new WP_Error('bw_capacity_missing', bw_text('error.capacity_missing'));
        }

        if ($enforce_future) {
            $start = self::get_slot_start_datetime($slot_id);
            if ($start && $start <= new DateTime('now', wp_timezone())) {
                return new WP_Error('bw_slot_past', bw_text('error.slot_past'));
            }
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
            return new WP_Error('bw_already_booked', bw_text('error.already_booked'));
        }

        // capacity + increment booked_count atomically
        $ok = self::try_increment_booked_count($slot_id, $capacity);
        if (!$ok) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_full', bw_text('error.full'));
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
            return new WP_Error('bw_booking_insert_failed', bw_text('error.generic'));
        }

        $booking_id = (int) $wpdb->insert_id;

        // consume credit + link to booking — Freiplätze bleiben ohne credit_id
        $credit_id = 0;
        if ($consume_credit) {
            $credit_id = self::consume_one_credit($user_id, $booking_id);
            if (is_wp_error($credit_id)) {
                self::decrement_booked_count($slot_id);
                $wpdb->query($wpdb->prepare("DELETE FROM {$bookings_table} WHERE id=%d", $booking_id));
                $wpdb->query('ROLLBACK');
                return $credit_id;
            }
        }

        // finalize booking
        $updated = $credit_id > 0
            ? $wpdb->query($wpdb->prepare(
                "UPDATE {$bookings_table} SET status=%s, credit_id=%d WHERE id=%d",
                'booked', (int) $credit_id, $booking_id
              ))
            : $wpdb->query($wpdb->prepare(
                "UPDATE {$bookings_table} SET status=%s, credit_id=NULL WHERE id=%d",
                'booked', $booking_id
              ));

        if ($updated !== 1) {
            if ($credit_id > 0) {
                self::refund_credit_by_booking($user_id, $booking_id);
            }
            self::decrement_booked_count($slot_id);
            $wpdb->query($wpdb->prepare("DELETE FROM {$bookings_table} WHERE id=%d", $booking_id));
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_booking_finalize_failed', bw_text('error.generic'));
        }

        $wpdb->query('COMMIT');

        // Nach dem Commit — Empfänger dürfen keine offene Transaktion sehen
        do_action('bw_booking_created', $booking_id, $user_id, $slot_id);

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
            return new WP_Error('bw_invalid', bw_text('error.generic'));
        }

        $cutoff_hours = BW_Settings::get_cancel_cutoff_hours();
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
            return new WP_Error('bw_booking_not_found', bw_text('error.booking_not_found'));
        }

        if ((int)$booking['is_active'] !== 1 || $booking['status'] !== 'booked') {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_not_active', bw_text('error.not_active'));
        }

        $slot_id = (int) $booking['slot_id'];

        $start = self::get_slot_start_datetime($slot_id);
        if (!$start) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_slot_time_missing', bw_text('error.slot_time_missing'));
        }

        $cutoff = clone $start;
        $cutoff->modify('-' . max(0, $cutoff_hours) . ' hours');

        if ($now >= $cutoff) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_cutoff_passed', bw_text('error.cutoff_passed'));
        }

        $cancelled_at = $now->format('Y-m-d H:i:s');
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$bookings_table}
             SET status=%s, is_active=NULL, cancelled_at=%s
             WHERE id=%d",
            'cancelled', $cancelled_at, $booking_id
        ));

        if ($updated !== 1) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_cancel_failed', bw_text('error.generic'));
        }

        $ref = self::refund_credit_by_booking($user_id, $booking_id);
        if (is_wp_error($ref)) {
            $wpdb->query('ROLLBACK');
            return $ref;
        }

        $ok = self::decrement_booked_count($slot_id);
        if (!$ok) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_bookedcount_failed', bw_text('error.generic'));
        }

        $wpdb->query('COMMIT');

        do_action('bw_booking_cancelled', $booking_id, $user_id, $slot_id);

        return [
            'ok' => true,
            'booking_id' => $booking_id,
            'status' => 'cancelled'
        ];
    }

    /**
     * Alle Buchungen eines Termins inkl. Nutzerdaten — für die Teilnehmerliste.
     */
    public static function get_slot_bookings(int $slot_id): array {
        global $wpdb;
        $bookings_table = $wpdb->prefix . self::BOOKINGS_TABLE;

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.user_id, b.status, b.is_active, b.credit_id,
                    b.created_at, b.cancelled_at,
                    u.display_name, u.user_email
             FROM {$bookings_table} b
             LEFT JOIN {$wpdb->users} u ON u.ID = b.user_id
             WHERE b.slot_id = %d
             ORDER BY b.created_at ASC",
            $slot_id
        ), ARRAY_A);
    }

    /**
     * Gefilterte Buchungsliste für die Admin-Übersicht.
     * Gibt die Zeilen und die Gesamtzahl für die Blätterfunktion zurück.
     */
    public static function query_bookings(array $args = []): array {
        global $wpdb;
        $bookings_table = $wpdb->prefix . self::BOOKINGS_TABLE;

        $where  = [];
        $params = [];

        if (!empty($args['slot_id'])) {
            $where[]  = 'b.slot_id = %d';
            $params[] = (int) $args['slot_id'];
        }
        if (!empty($args['user_id'])) {
            $where[]  = 'b.user_id = %d';
            $params[] = (int) $args['user_id'];
        }
        if (!empty($args['status'])) {
            $where[]  = 'b.status = %s';
            $params[] = (string) $args['status'];
        }
        if (!empty($args['search'])) {
            $like     = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $where[]  = '(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $join      = "FROM {$bookings_table} b LEFT JOIN {$wpdb->users} u ON u.ID = b.user_id";

        $count_sql = "SELECT COUNT(*) {$join} {$where_sql}";
        $total     = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql);

        $per_page = max(1, min(200, (int) ($args['per_page'] ?? 30)));
        $page     = max(1, (int) ($args['page'] ?? 1));

        $rows_sql    = "SELECT b.*, u.display_name, u.user_email {$join} {$where_sql}
                        ORDER BY b.created_at DESC LIMIT %d OFFSET %d";
        $rows_params = array_merge($params, [$per_page, ($page - 1) * $per_page]);

        return [
            'rows'     => (array) $wpdb->get_results($wpdb->prepare($rows_sql, $rows_params), ARRAY_A),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
        ];
    }

    /**
     * Storno durch den Admin — ignoriert die Storno-Frist, gibt den Credit
     * zurück sofern einer verbraucht wurde.
     */
    public static function admin_cancel_booking(int $booking_id) {
        global $wpdb;
        $bookings_table = $wpdb->prefix . self::BOOKINGS_TABLE;

        if ($booking_id <= 0) {
            return new WP_Error('bw_invalid', bw_text('error.generic'));
        }

        $wpdb->query('START TRANSACTION');

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bookings_table} WHERE id=%d LIMIT 1 FOR UPDATE",
            $booking_id
        ), ARRAY_A);

        if (!$booking) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_booking_not_found', bw_text('error.booking_not_found'));
        }

        if ((int) $booking['is_active'] !== 1) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_not_active', bw_text('error.not_active'));
        }

        $user_id = (int) $booking['user_id'];
        $slot_id = (int) $booking['slot_id'];

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$bookings_table}
             SET status=%s, is_active=NULL, cancelled_at=%s
             WHERE id=%d",
            'cancelled',
            (new DateTime('now', wp_timezone()))->format('Y-m-d H:i:s'),
            $booking_id
        ));

        if ($updated !== 1) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_cancel_failed', bw_text('error.generic'));
        }

        // Freiplätze (Admin-Buchung ohne Credit) haben nichts zurückzugeben
        if (!empty($booking['credit_id'])) {
            $ref = self::refund_credit_by_booking($user_id, $booking_id);
            if (is_wp_error($ref)) {
                $wpdb->query('ROLLBACK');
                return $ref;
            }
        }

        if (!self::decrement_booked_count($slot_id)) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('bw_bookedcount_failed', bw_text('error.generic'));
        }

        $wpdb->query('COMMIT');

        do_action('bw_booking_cancelled', $booking_id, $user_id, $slot_id);

        return true;
    }

    /**
     * "Nicht erschienen" markieren bzw. zurücknehmen.
     * Der Platz bleibt belegt und der Credit verbraucht — deshalb bleibt
     * is_active=1 und booked_count unverändert.
     */
    public static function set_no_show(int $booking_id, bool $no_show) {
        global $wpdb;
        $bookings_table = $wpdb->prefix . self::BOOKINGS_TABLE;

        $to   = $no_show ? 'no_show' : 'booked';
        $from = $no_show ? 'booked'  : 'no_show';

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$bookings_table}
             SET status=%s
             WHERE id=%d AND status=%s AND is_active=1",
            $to, $booking_id, $from
        ));

        if ($updated !== 1) {
            return new WP_Error('bw_no_show_failed', 'Status konnte nicht geändert werden.');
        }

        return true;
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

    public static function status_labels(): array {
        return [
            'booked'    => bw_text('bookings.status.booked'),
            'cancelled' => bw_text('bookings.status.cancelled'),
            'pending'   => bw_text('bookings.status.pending'),
            'no_show'   => bw_text('bookings.status.no_show'),
        ];
    }

    // Display balance (block)
    /**
     * [bw_credits_user_balance] — Guthaben als Zahl oder als Absatz.
     * Vereint die früheren bw_balance_inline und bw_credits_balance.
     */
    public static function sc_balance($atts = []) {
        $atts = shortcode_atts([
            'mode'       => 'always',      // always | empty_only
            'format'     => 'inline',      // inline | block
            'label'      => '',   // leer = Text aus dem Katalog
            'empty_text' => '',
            'empty_link' => '',
            'shop_url'   => '',
            'logged_out' => '',
        ], $atts, 'bw_credits_user_balance');

        if (!is_user_logged_in()) {
            return $atts['logged_out'] !== '' ? esc_html($atts['logged_out']) : '';
        }

        $atts['label']      = $atts['label']      !== '' ? $atts['label']      : bw_text('balance.label');
        $atts['empty_text'] = $atts['empty_text'] !== '' ? $atts['empty_text'] : bw_text('balance.empty');
        $atts['empty_link'] = $atts['empty_link'] !== '' ? $atts['empty_link'] : bw_text('booking.link.topup');

        $user_id   = get_current_user_id();
        $available = self::get_available_credits($user_id);

        if ($atts['mode'] === 'empty_only') {
            // Wer nie Guthaben hatte, soll über den Shop einsteigen statt über
            // einen Hinweis auf leeres Guthaben. total zählt alle je vergebenen
            // Credits — auch manuelle Gutschriften aus Willkommensaktionen.
            $summary = self::get_credit_summary($user_id);
            if ($summary['total'] < 1) return '';
        }

        self::ensure_assets();

        // data-bw-balance wird vom JS nach Buchung und Storno aktualisiert
        $number = '<span data-bw-balance>' . esc_html($available) . '</span>';

        if ($atts['mode'] !== 'empty_only') {
            if ($atts['format'] === 'block') {
                return '<p class="bw-balance">' . esc_html($atts['label']) . ' <strong>' . $number . '</strong></p>';
            }
            return $number;
        }

        return self::render_balance_states($atts, $number, $available);
    }

    /**
     * Beide Zustände ins Markup — das JS schaltet über data-bw-state um.
     * Stünde nur der jeweils zutreffende Zweig da, erschiene der Hinweis erst
     * nach einem Reload, also gerade nicht in dem Moment in dem der Kunde
     * seinen letzten Credit verbraucht.
     */
    private static function render_balance_states(array $atts, string $number, int $available): string {
        $url  = $atts['shop_url'] !== '' ? $atts['shop_url'] : BW_Settings::get_shop_url();
        $hint = esc_html($atts['empty_text']);

        if ($url !== '' && $atts['empty_link'] !== '') {
            $hint .= ' <a href="' . esc_url($url) . '">' . esc_html($atts['empty_link']) . '</a>';
        }

        return sprintf(
            '<span class="bw-balance-state" data-bw-balance-wrap data-bw-state="%s">'
            . '<span data-bw-has-wrap>%s <strong>%s</strong></span>'
            . '<span data-bw-empty-wrap class="bw-balance-empty">%s</span>'
            . '</span>',
            $available > 0 ? 'has' : 'empty',
            esc_html($atts['label']),
            $number,
            $hint
        );
    }

    /** Shop-Ziel für Hinweise auf leeres Guthaben. */
    private static function shop_link(string $label): string {
        $url = BW_Settings::get_shop_url();
        if ($url === '') return '';

        return ' <a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }

    // [bw_book_button slot_id="123" label="Kurs buchen (1 Credit)"]
    public static function sc_book_button($atts) {
        if (!is_user_logged_in()) return '';

        $atts = shortcode_atts([
            'slot_id' => 0,
            'label'   => '',   // leer = Text aus dem Katalog
            'wrap'    => '1',
            'class'   => 'bw-bwallet-btn',
        ], $atts);

        $slot_id = (int) $atts['slot_id'];
        if ($slot_id <= 0) return '';

        self::ensure_assets();

        $atts['label'] = $atts['label'] !== '' ? $atts['label'] : bw_text('booking.button.book');

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
            'label'      => '',   // leer = Text aus dem Katalog
            'wrap'       => '1',
            'class'      => 'bw-bwallet-btn',
        ], $atts);

        $booking_id = (int) $atts['booking_id'];
        if ($booking_id <= 0) return '';

        self::ensure_assets();

        $atts['label'] = $atts['label'] !== '' ? $atts['label'] : bw_text('booking.button.cancel_short');

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

    /**
     * Slot-ID aus dem Shortcode oder — wenn leer — aus dem aktuellen Beitrag.
     * So funktionieren die Shortcodes auf der Termin-Einzelseite ohne dass
     * jemand die ID eintippen muss.
     */
    public static function resolve_course_id(int $slot_id): int {
        if ($slot_id > 0) return $slot_id;

        $post = get_post();
        if ($post && $post->post_type === BW_Settings::get_slot_post_type()) {
            return (int) $post->ID;
        }

        return 0;
    }

    public static function get_free_spots(int $slot_id): int {
        $capacity = self::get_slot_capacity($slot_id);
        if ($capacity <= 0) return 0;

        $booked = (int) get_post_meta($slot_id, self::META_BOOKED_CNT, true);
        return max(0, $capacity - $booked);
    }

    public static function get_active_booking(int $user_id, int $slot_id): ?array {
        global $wpdb;
        $bookings_table = $wpdb->prefix . self::BOOKINGS_TABLE;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM {$bookings_table}
             WHERE user_id = %d AND slot_id = %d AND is_active = 1
             LIMIT 1",
            $user_id, $slot_id
        ), ARRAY_A);

        return $row ?: null;
    }

    private static function can_cancel_now(?DateTime $start): bool {
        if (!$start) return false;

        $cutoff = (clone $start)->modify('-' . BW_Settings::get_cancel_cutoff_hours() . ' hours');
        return new DateTime('now', wp_timezone()) < $cutoff;
    }

    /** $suffix_html muss bereits escaped sein — gedacht für einen Link. */
    private static function note(string $text, string $modifier = '', string $suffix_html = ''): string {
        return '<p class="bw-slot-note' . ($modifier ? ' ' . esc_attr($modifier) : '') . '">'
             . esc_html($text) . $suffix_html . '</p>';
    }

    /**
     * [bw_slot_action] — ein Button der je nach Zustand bucht oder storniert.
     * Ohne slot_id greift der aktuelle Beitrag.
     */
    public static function sc_slot_action($atts) {
        $atts = shortcode_atts([
            'course_id'    => 0,
            'slot_id'      => 0,   // alter Name, bleibt gültig
            'label_book'   => '',  // leer = Text aus dem Katalog
            'label_cancel' => '',
            'class'        => 'bw-bwallet-btn',
        ], $atts, 'bw_credits_course_booking');

        // Attribut schlägt Admin-Override schlägt übersetzten Standard
        $atts['label_book']   = $atts['label_book']   !== '' ? $atts['label_book']   : bw_text('booking.button.book');
        $atts['label_cancel'] = $atts['label_cancel'] !== '' ? $atts['label_cancel'] : bw_text('booking.button.cancel');

        $slot_id = self::resolve_course_id((int) ($atts['course_id'] ?: $atts['slot_id']));
        if ($slot_id <= 0) return '';

        self::ensure_assets();

        $start = self::get_slot_start_datetime($slot_id);
        $past  = $start && $start <= new DateTime('now', wp_timezone());

        if (!is_user_logged_in()) {
            if ($past) return self::note(bw_text('booking.note.past'), 'bw-is-past');

            return '<p class="bw-slot-note"><a href="' . esc_url(wp_login_url(get_permalink() ?: '')) . '">'
                 . esc_html(bw_text('booking.note.login')) . '</a></p>';
        }

        $user_id = get_current_user_id();
        $booking = self::get_active_booking($user_id, $slot_id);

        // Bereits gebucht → stornieren, solange die Frist läuft
        if ($booking) {
            if ($booking['status'] !== 'booked' || !self::can_cancel_now($start)) {
                return self::note(bw_text('booking.note.booked'), 'bw-is-booked');
            }

            return self::action_button([
                'action'     => 'cancel',
                'booking_id' => (int) $booking['id'],
                'slot_id'    => $slot_id,
                'label'      => $atts['label_cancel'],
                'class'      => $atts['class'],
                'labels'     => $atts,
            ]);
        }

        if ($past)                              return self::note(bw_text('booking.note.past'), 'bw-is-past');
        if (self::get_free_spots($slot_id) < 1) return self::note(bw_text('booking.note.full'), 'bw-is-full');

        if (self::get_available_credits($user_id) < 1) {
            return self::note(
                bw_text('booking.note.no_credits'),
                'bw-no-credits',
                self::shop_link(bw_text('booking.link.topup'))
            );
        }

        return self::action_button([
            'action'  => 'book',
            'slot_id' => $slot_id,
            'label'   => $atts['label_book'],
            'class'   => $atts['class'],
            'labels'  => $atts,
        ]);
    }

    /** Umschaltbarer Button — das JS tauscht Aktion und Beschriftung nach dem Klick. */
    private static function action_button(array $args): string {
        $btn = sprintf(
            '<button type="button" class="%s" data-bw-action="%s" data-bw-toggle="1"'
            . ' data-slot-id="%d"%s data-label-book="%s" data-label-cancel="%s">%s</button>',
            esc_attr($args['class']),
            esc_attr($args['action']),
            (int) $args['slot_id'],
            !empty($args['booking_id']) ? ' data-booking-id="' . (int) $args['booking_id'] . '"' : '',
            esc_attr($args['labels']['label_book']),
            esc_attr($args['labels']['label_cancel']),
            esc_html($args['label'])
        );

        return '<div data-bw-wrap="1">' . $btn . '<div class="bw-bwallet-msg" data-bw-msg></div></div>';
    }

    /**
     * [bw_availability] — freie Plätze, auch ohne Login sichtbar.
     * Platzhalter {frei} statt printf-Format, damit eine fehlerhafte Angabe
     * im Shortcode keinen Fehler auslöst.
     */
    public static function sc_availability($atts) {
        $atts = shortcode_atts([
            'course_id' => 0,
            'slot_id'   => 0,   // alter Name, bleibt gültig
            'format'    => '',   // leer = Text aus dem Katalog
            'full'      => '',
        ], $atts, 'bw_credits_course_availability');

        $atts['format'] = $atts['format'] !== '' ? $atts['format'] : bw_text('availability.free');
        $atts['full']   = $atts['full']   !== '' ? $atts['full']   : bw_text('availability.full');

        $slot_id = self::resolve_course_id((int) ($atts['course_id'] ?: $atts['slot_id']));
        if ($slot_id <= 0) return '';

        self::ensure_assets();

        $free  = self::get_free_spots($slot_id);
        $parts = explode('{frei}', $atts['format'], 2);

        // Beide Varianten ausgeben und per Status umschalten — so kann das JS
        // nach Buchung oder Storno in beide Richtungen aktualisieren
        return sprintf(
            '<span class="bw-availability" data-bw-availability="%d" data-bw-state="%s">'
            . '<span data-bw-free-wrap>%s<span data-bw-free>%d</span>%s</span>'
            . '<span data-bw-full-wrap>%s</span>'
            . '</span>',
            $slot_id,
            $free > 0 ? 'free' : 'full',
            esc_html($parts[0]),
            $free,
            esc_html($parts[1] ?? ''),
            esc_html($atts['full'])
        );
    }

    /** Übersicht und Buchungen im WooCommerce-Konto-Dashboard. */
    public static function render_account_dashboard() {
        if (!is_user_logged_in()) return;

        echo BW_View_Overview::render([]);

        echo '<h2>' . esc_html(bw_text('overview.heading.courses')) . '</h2>';
        echo self::sc_my_bookings(['limit' => 10]);
    }

    // [bw_my_bookings limit="20"]
    public static function sc_my_bookings($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html(bw_text('bookings.login_required')) . '</p>';
        }

        self::ensure_assets();

        $atts = shortcode_atts([
            'limit'       => 20,
            'show_access' => 'true',
        ], $atts, 'bw_credits_user_bookings');
        $uid        = get_current_user_id();
        $bookings   = self::get_my_bookings($uid, (int) $atts['limit']);

        if (empty($bookings)) {
            return '<p class="bw-no-bookings">' . esc_html(bw_text('bookings.empty')) . '</p>';
        }

        $cutoff_hours = BW_Settings::get_cancel_cutoff_hours();
        $now          = new DateTime('now', wp_timezone());

        $status_labels = self::status_labels();
        $show_access   = filter_var($atts['show_access'], FILTER_VALIDATE_BOOLEAN);

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

            $permalink = get_permalink($slot_id);
            $meta_bits = array_filter([
                bw_cs_first_term($slot_id, 'course_type'),
                bw_cs_first_term($slot_id, 'course_level'),
                bw_cs_first_term($slot_id, 'course_lang'),
            ]);

            echo '<div class="bw-booking-item bw-status-' . esc_attr($status) . '">';

            echo '<div class="bw-booking-slot">';
            echo $permalink
                ? '<a href="' . esc_url($permalink) . '">' . esc_html($slot_title) . '</a>'
                : esc_html($slot_title);
            if ($meta_bits) {
                echo '<span class="bw-booking-meta">' . esc_html(implode(' · ', $meta_bits)) . '</span>';
            }
            echo '</div>';

            echo '<div class="bw-booking-time">' . esc_html($start_str) . '</div>';
            echo '<div class="bw-booking-status">' . esc_html($status_label) . '</div>';

            if ($can_cancel) {
                echo self::sc_cancel_button(['booking_id' => $booking_id, 'slot_id' => $slot_id]);
            }

            if ($show_access && $is_active && $status === 'booked') {
                echo BW_View_Access::render(['course_id' => $slot_id, 'title' => '']);
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