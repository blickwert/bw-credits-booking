<?php
if (!defined('ABSPATH')) exit;

/**
 * "Only once per customer" for a product (e.g. an introductory offer).
 *
 * A checkbox on the WooCommerce product screen (General tab) marks the product.
 * Whoever already has an order with it (processing, completed or on hold — filter
 * bw_once_per_customer_statuses) can't buy it again:
 *
 *  - Customers with an account are stopped as soon as they add it to the cart.
 *  - At the checkout the billing e-mail address is checked as well (guests, a second account).
 *    The checkout shows the notice and blocks the order.
 *  - The product in the cart carries a short hint ("only once per person").
 *
 * The product's language versions (WPML) count as one product.
 */
class BW_Purchase_Limit {

    const META = '_bw_once_per_customer';

    public static function init() {
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'render_field']);
        add_action('woocommerce_admin_process_product_object', [__CLASS__, 'save_field']);

        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_add_to_cart'], 10, 2);
        add_action('woocommerce_check_cart_items', [__CLASS__, 'check_cart']);
        add_action('woocommerce_after_checkout_validation', [__CLASS__, 'validate_checkout'], 10, 2);
        add_filter('woocommerce_cart_item_name', [__CLASS__, 'cart_item_hint'], 10, 3);
    }

    /* ---------------------------------------------------------
     * Product setting
     * --------------------------------------------------------- */

    public static function render_field() {
        woocommerce_wp_checkbox([
            'id'          => self::META,
            'label'       => __('Only once per customer', 'bw-credits-booking'),
            'description' => __('Whoever already ordered this product (processing, completed or on hold) can\'t order it again. Checked by account and billing e-mail address; counts for all language versions.', 'bw-credits-booking'),
        ]);
    }

    public static function save_field($product) {
        $product->update_meta_data(self::META, isset($_POST[self::META]) ? 'yes' : 'no');
    }

    /* ---------------------------------------------------------
     * Which products, which customers
     * --------------------------------------------------------- */

    /** The product and its translations (WPML). */
    public static function product_ids(int $product_id): array {
        $ids = [$product_id];
        if (has_filter('wpml_object_id')) {
            $languages = apply_filters('wpml_active_languages', null);
            foreach (array_keys((array) $languages) as $lang) {
                $id = apply_filters('wpml_object_id', $product_id, 'product', false, $lang);
                if ($id) $ids[] = (int) $id;
            }
        }
        return array_values(array_unique($ids));
    }

    /** Marked on this product or on one of its translations. */
    public static function is_limited(int $product_id): bool {
        if ($product_id <= 0) return false;

        foreach (self::product_ids($product_id) as $id) {
            if (get_post_meta($id, self::META, true) === 'yes') return true;
        }
        return false;
    }

    /** An order of this customer (account or billing e-mail address) already holds one of the products. */
    public static function has_bought(array $product_ids, int $user_id, string $email = ''): bool {
        $statuses = (array) apply_filters('bw_once_per_customer_statuses', ['processing', 'completed', 'on-hold']);

        $queries = [];
        if ($user_id > 0)  $queries[] = ['customer_id' => $user_id];
        if ($email !== '') $queries[] = ['billing_email' => $email];

        foreach ($queries as $query) {
            $orders = wc_get_orders($query + ['status' => $statuses, 'limit' => -1]);
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    if (in_array((int) $item->get_product_id(), $product_ids, true)) return true;
                }
            }
        }
        return false;
    }

    private static function notice_text(int $product_id): string {
        return sprintf(
            /* translators: %s: product name */
            __('You have already booked “%s”. This offer is only available once per person.', 'bw-credits-booking'),
            html_entity_decode(get_the_title($product_id), ENT_QUOTES, 'UTF-8')
        );
    }

    /* ---------------------------------------------------------
     * Cart and checkout
     * --------------------------------------------------------- */

    public static function validate_add_to_cart($passed, $product_id) {
        $product_id = (int) $product_id;
        $user_id    = (int) get_current_user_id();

        if (!$passed || $user_id <= 0 || !self::is_limited($product_id)) return $passed;

        if (self::has_bought(self::product_ids($product_id), $user_id, self::user_email($user_id))) {
            wc_add_notice(self::notice_text($product_id), 'error');
            return false;
        }
        return $passed;
    }

    /** Logged-in customers: a limited product that is already in the cart (old session, bought meanwhile) goes. */
    public static function check_cart() {
        $user_id = (int) get_current_user_id();
        if ($user_id <= 0 || !function_exists('WC') || !WC()->cart) return;

        foreach (WC()->cart->get_cart() as $key => $item) {
            $product_id = (int) ($item['product_id'] ?? 0);
            if (!self::is_limited($product_id)) continue;

            if (self::has_bought(self::product_ids($product_id), $user_id, self::user_email($user_id))) {
                WC()->cart->remove_cart_item($key);
                wc_add_notice(self::notice_text($product_id), 'error');
            }
        }
    }

    /** Checkout: the billing e-mail address entered counts too (guests, a second account). */
    public static function validate_checkout($data, $errors) {
        if (!function_exists('WC') || !WC()->cart) return;

        $email   = strtolower(trim((string) ($data['billing_email'] ?? '')));
        $user_id = (int) get_current_user_id();

        foreach (WC()->cart->get_cart() as $item) {
            $product_id = (int) ($item['product_id'] ?? 0);
            if (!self::is_limited($product_id)) continue;

            if (self::has_bought(self::product_ids($product_id), $user_id, $email)) {
                $errors->add('bw_once_per_customer', self::notice_text($product_id));
            }
        }
    }

    public static function cart_item_hint($name, $cart_item = [], $cart_item_key = '') {
        $product_id = (int) ($cart_item['product_id'] ?? 0);
        if (!$product_id || !self::is_limited($product_id)) return $name;

        return $name . '<br><small class="bw-once-per-customer">' . esc_html__('Only available once per person.', 'bw-credits-booking') . '</small>';
    }

    private static function user_email(int $user_id): string {
        $user = get_userdata($user_id);
        return $user ? strtolower((string) $user->user_email) : '';
    }
}

BW_Purchase_Limit::init();
