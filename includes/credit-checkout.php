<?php
if (!defined('ABSPATH')) exit;

/**
 * Credit products: straight to checkout, one at a time, completed after payment.
 *
 * A credit product is a WooCommerce product with a credit amount greater than 0
 * (field "_bw_credit_amount", see includes/admin.php) — not a category.
 *
 *  1. Buying a credit product skips the cart page: after "add to cart" the
 *     customer lands on the checkout. The cart is replaced (anything in it is
 *     removed, with a notice), and the quantity is fixed to 1.
 *  2. Credit products and other products are never mixed: another product can't
 *     be added while a credit product is in the cart, and an already mixed cart
 *     (e.g. from an old session) blocks the checkout with a notice.
 *  3. An order that consists only of credit products and belongs to a customer
 *     account is completed automatically once it is paid. The credits are
 *     granted when an order is completed, so the customer gets them right
 *     after paying, without anyone setting the status by hand. The separate
 *     "processing" mail is skipped in that case (the "completed" mail
 *     carries the credit note).
 *
 * Both parts can be switched off in BW Credits › Settings.
 */

class BW_Credit_Checkout {

    public static function init() {
        // 1 + 2: cart
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_add_to_cart'], 5, 5);
        add_filter('woocommerce_add_to_cart_redirect', [__CLASS__, 'redirect_to_checkout'], 10, 2);
        add_filter('woocommerce_is_sold_individually', [__CLASS__, 'sold_individually'], 10, 2);
        add_filter('woocommerce_loop_add_to_cart_args', [__CLASS__, 'loop_button_args'], 10, 2);
        add_action('woocommerce_check_cart_items', [__CLASS__, 'check_cart']);

        // 3: order
        add_filter('woocommerce_payment_complete_order_status', [__CLASS__, 'payment_complete_status'], 10, 3);
        add_action('woocommerce_order_status_processing', [__CLASS__, 'complete_after_manual_processing'], 20, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', [__CLASS__, 'skip_processing_mail'], 10, 2);
    }

    /* ---------------------------------------------------------
     * Which products are credit products
     * --------------------------------------------------------- */

    /** Product (or the parent of a variation) with a credit amount > 0. */
    public static function is_credit_product(int $product_id): bool {
        if ($product_id <= 0) return false;

        if (get_post_type($product_id) === 'product_variation') {
            $product_id = (int) wp_get_post_parent_id($product_id);
        }

        return (int) get_post_meta($product_id, BW_Credits_Bookings_MVP::PM_CREDIT_AMOUNT, true) > 0;
    }

    /** What the cart holds: ['credit' => bool, 'other' => bool, 'other_than' => bool]. */
    private static function cart_contents(int $adding_product_id = 0): array {
        $has = ['credit' => false, 'other' => false, 'other_than_adding' => false];
        if (!function_exists('WC') || !WC()->cart) return $has;

        foreach (WC()->cart->get_cart() as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            if (self::is_credit_product($pid)) {
                $has['credit'] = true;
            } else {
                $has['other'] = true;
            }
            if ($pid !== $adding_product_id) $has['other_than_adding'] = true;
        }
        return $has;
    }

    /** All items are credit products (and there is at least one). */
    public static function order_is_credit_only($order): bool {
        if (!$order || !method_exists($order, 'get_items')) return false;

        $items = $order->get_items();
        if (!$items) return false;

        foreach ($items as $item) {
            if (!self::is_credit_product((int) $item->get_product_id())) return false;
        }
        return true;
    }

    /* ---------------------------------------------------------
     * Cart: replace, quantity 1, no mixing
     * --------------------------------------------------------- */

    public static function validate_add_to_cart($passed, $product_id = 0, $quantity = 1, $variation_id = 0, $variations = []) {
        if (!$passed || !BW_Settings::get_credit_direct_checkout() || !function_exists('WC') || !WC()->cart) {
            return $passed;
        }

        $product_id = (int) $product_id;
        $cart       = WC()->cart;
        if ($cart->is_empty()) return $passed;

        $contents = self::cart_contents($product_id);

        if (self::is_credit_product($product_id)) {
            // Replace the cart. Tell the customer only if something else was in it.
            $cart->empty_cart();
            if ($contents['other_than_adding']) {
                wc_add_notice(bw_text('cart.credit_replaced'), 'notice');
            }
            return $passed;
        }

        if ($contents['credit']) {
            wc_add_notice(bw_text('cart.credit_only'), 'error');
            return false;
        }

        return $passed;
    }

    /** After adding a credit product: straight to the checkout. */
    public static function redirect_to_checkout($url, $adding_to_cart = null) {
        if (!BW_Settings::get_credit_direct_checkout() || !is_object($adding_to_cart) || !method_exists($adding_to_cart, 'get_id')) {
            return $url;
        }
        return self::is_credit_product((int) $adding_to_cart->get_id()) ? wc_get_checkout_url() : $url;
    }

    /** Quantity 1: WooCommerce hides the quantity field and rejects a second one. */
    public static function sold_individually($sold, $product = null) {
        if ($sold || !BW_Settings::get_credit_direct_checkout() || !is_object($product) || !method_exists($product, 'get_id')) {
            return $sold;
        }
        return self::is_credit_product((int) $product->get_id());
    }

    /** Shop buttons: a normal link (no AJAX), so the redirect to the checkout works. */
    public static function loop_button_args($args, $product = null) {
        if (!BW_Settings::get_credit_direct_checkout() || !is_object($product) || !method_exists($product, 'get_id') || !is_array($args)) {
            return $args;
        }
        if (self::is_credit_product((int) $product->get_id()) && isset($args['class'])) {
            $args['class'] = trim(str_replace('ajax_add_to_cart', '', (string) $args['class']));
        }
        return $args;
    }

    /** An already mixed cart (e.g. from an old session) can't be checked out. */
    public static function check_cart() {
        if (!BW_Settings::get_credit_direct_checkout()) return;

        $contents = self::cart_contents();
        if ($contents['credit'] && $contents['other']) {
            wc_add_notice(bw_text('cart.credit_only'), 'error');
        }
    }

    /* ---------------------------------------------------------
     * Order: completed after payment
     * --------------------------------------------------------- */

    /** Whether this order is completed automatically (credit products only, customer account). */
    private static function completes_automatically($order): bool {
        return BW_Settings::get_credit_auto_complete()
            && $order && method_exists($order, 'get_user_id') && (int) $order->get_user_id() > 0
            && self::order_is_credit_only($order);
    }

    /** payment_complete() — Stripe, PayPal, …: straight to "completed" instead of "processing". */
    public static function payment_complete_status($status, $order_id = 0, $order = null) {
        if ($status === 'processing' && self::completes_automatically($order)) {
            return 'completed';
        }
        return $status;
    }

    /** Manually set to "processing" (e.g. bank transfer received): same treatment. */
    public static function complete_after_manual_processing($order_id, $order = null) {
        if (!$order && function_exists('wc_get_order')) $order = wc_get_order($order_id);
        if (!$order || $order->get_status() !== 'processing' || !self::completes_automatically($order)) return;

        $order->update_status(
            'completed',
            __('Completed automatically: the order consists only of credit products.', 'bw-credits-booking')
        );
    }

    /** Customers of such an order get the "completed" mail with the credit note, not an extra "processing" mail. */
    public static function skip_processing_mail($enabled, $order = null) {
        return ($enabled && self::completes_automatically($order)) ? false : $enabled;
    }
}

BW_Credit_Checkout::init();
