<?php
if (!defined('ABSPATH')) exit;

/**
 * Two small utility shortcodes for WooCommerce product pages — plain
 * pricing/tax data, no admin UI, no on/off switch (unlike the product
 * feature list). Both resolve the product from an optional product_id
 * attribute, falling back to WooCommerce's own `global $product`
 * (set automatically in the product-page/loop context).
 */

class BW_Product_Pricing {

    public static function init() {
        add_shortcode('bw_product_tax_info', [__CLASS__, 'shortcode_tax_info']);
        add_shortcode('bw_product_price_per_unit', [__CLASS__, 'shortcode_price_per_unit']);
    }

    private static function resolve_product(array $atts): ?WC_Product {
        $product_id = (int) $atts['product_id'];
        if ($product_id > 0) {
            $product = wc_get_product($product_id);
            return $product instanceof WC_Product ? $product : null;
        }

        global $product;
        return $product instanceof WC_Product ? $product : null;
    }

    /**
     * [bw_product_tax_info] — the product's tax rate, as a bare number
     * (e.g. "20"), no surrounding text — any wording ("incl. 20% VAT")
     * belongs in the (translatable) content around the shortcode, not
     * hardcoded here, so this works the same on every language version.
     */
    public static function shortcode_tax_info($atts): string {
        $atts    = shortcode_atts(['product_id' => 0], $atts, 'bw_product_tax_info');
        $product = self::resolve_product($atts);
        if (!$product) return '';

        $tax_rates = WC_Tax::get_rates($product->get_tax_class());
        if (empty($tax_rates)) return '';

        $rate = reset($tax_rates);
        return esc_html($rate['rate']);
    }

    /**
     * [bw_product_price_per_unit] — the product's price divided by its
     * Credit Amount (_bw_credit_amount, BW_Credits_Bookings_MVP::PM_CREDIT_AMOUNT),
     * e.g. a €150 / 10-credit package shows "15,00 €" — lets customers
     * compare packages of different sizes. Empty without a credit
     * amount set (avoids a division by zero, and there's nothing
     * meaningful to show for a non-credit product anyway).
     */
    public static function shortcode_price_per_unit($atts): string {
        $atts    = shortcode_atts(['product_id' => 0], $atts, 'bw_product_price_per_unit');
        $product = self::resolve_product($atts);
        if (!$product) return '';

        $amount = (int) get_post_meta($product->get_id(), BW_Credits_Bookings_MVP::PM_CREDIT_AMOUNT, true);
        if ($amount <= 0) return '';

        $per_unit = (float) $product->get_price() / $amount;
        return wc_price($per_unit);
    }
}

BW_Product_Pricing::init();
