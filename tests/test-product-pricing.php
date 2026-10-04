<?php
/**
 * Regression test: BW_Product_Pricing ([bw_product_tax_info],
 * [bw_product_price_per_unit], [bw_product_price]). Loads the real
 * includes/product-pricing.php against a stub WordPress/WooCommerce
 * environment.
 */

define('ABSPATH', true);

$GLOBALS['__postmeta']  = [];
$GLOBALS['__products']  = []; // id => FakeWCProduct
$GLOBALS['__tax_rates'] = []; // tax_class => rates array
$GLOBALS['product']     = null; // the WC "global $product"

function add_shortcode(...$a) {}
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }

function get_post_meta($id, $key, $single = false) {
    return $GLOBALS['__postmeta'][$id][$key] ?? '';
}

$GLOBALS['__current_post'] = 0; // the "current post" (get_the_ID())
function get_the_ID() { return $GLOBALS['__current_post'] ?: false; }
function get_post_type($id) { return isset($GLOBALS['__products'][$id]) ? 'product' : 'page'; }

function wc_get_product($id) {
    return $GLOBALS['__products'][$id] ?? false;
}

function wc_price($amount) {
    return number_format((float) $amount, 2, ',', '.') . ' €';
}

// Simplified real shortcode_atts(): merge known keys, ignore unknown ones.
function shortcode_atts($pairs, $atts, $shortcode = '') {
    $atts = (array) $atts;
    $out  = [];
    foreach ($pairs as $name => $default) {
        $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
    }
    return $out;
}

class WC_Product {
    public $id;
    public $price;
    public $tax_class;
    public function __construct($id, $price, $tax_class = '') {
        $this->id = $id;
        $this->price = $price;
        $this->tax_class = $tax_class;
    }
    public function get_id() { return $this->id; }
    public function get_price() { return $this->price; }
    public function get_tax_class() { return $this->tax_class; }
}

class WC_Tax {
    public static function get_rates($tax_class) {
        return $GLOBALS['__tax_rates'][$tax_class] ?? [];
    }
}

class BW_Credits_Bookings_MVP {
    const PM_CREDIT_AMOUNT = '_bw_credit_amount';
}

require __DIR__ . '/../includes/product-pricing.php';

$pass = 0;
$fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; printf("PASS  %s\n", $label); }
    else       { $fail++; printf("FAIL  %s\n", $label); }
}

function reset_state() {
    $GLOBALS['__postmeta']  = [];
    $GLOBALS['__products']  = [];
    $GLOBALS['__tax_rates'] = [];
    $GLOBALS['product']     = null;
    $GLOBALS['__current_post'] = 0;
}

/* ---------------------------------------------------------------
 * 1. shortcode_tax_info(): no product at all
 * --------------------------------------------------------------- */
reset_state();
check('tax_info(): no global $product, no product_id -> empty', BW_Product_Pricing::shortcode_tax_info([]) === '');

/* ---------------------------------------------------------------
 * 2. shortcode_tax_info(): via global $product
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['product'] = new WC_Product(42, '150.00', 'standard');
$GLOBALS['__tax_rates']['standard'] = [['rate' => '20.0000', 'label' => 'VAT']];
check('tax_info(): bare rate value, no surrounding text', BW_Product_Pricing::shortcode_tax_info([]) === '20.0000');

/* ---------------------------------------------------------------
 * 3. shortcode_tax_info(): empty tax rates -> empty
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['product'] = new WC_Product(42, '150.00', 'zero-rate');
$GLOBALS['__tax_rates']['zero-rate'] = [];
check('tax_info(): no tax rates for the product\'s tax class -> empty', BW_Product_Pricing::shortcode_tax_info([]) === '');

/* ---------------------------------------------------------------
 * 4. shortcode_tax_info(): via explicit product_id, overriding global
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['product'] = new WC_Product(1, '10.00', 'reduced');
$GLOBALS['__products'][99] = new WC_Product(99, '50.00', 'standard');
$GLOBALS['__tax_rates']['reduced']  = [['rate' => '7.0000']];
$GLOBALS['__tax_rates']['standard'] = [['rate' => '19.0000']];
check('tax_info(): product_id attribute overrides global $product', BW_Product_Pricing::shortcode_tax_info(['product_id' => 99]) === '19.0000');

check('tax_info(): unknown product_id -> empty (not silently falling back to global)', BW_Product_Pricing::shortcode_tax_info(['product_id' => 12345]) === '');

/* ---------------------------------------------------------------
 * 5. shortcode_price_per_unit(): no credit amount set -> empty
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['product'] = new WC_Product(42, '150.00');
check('price_per_unit(): no credit amount set -> empty', BW_Product_Pricing::shortcode_price_per_unit([]) === '');

$GLOBALS['__postmeta'][42]['_bw_credit_amount'] = '0';
check('price_per_unit(): credit amount 0 -> empty (avoids division by zero)', BW_Product_Pricing::shortcode_price_per_unit([]) === '');

$GLOBALS['__postmeta'][42]['_bw_credit_amount'] = '-3';
check('price_per_unit(): negative credit amount -> empty', BW_Product_Pricing::shortcode_price_per_unit([]) === '');

/* ---------------------------------------------------------------
 * 6. shortcode_price_per_unit(): correct division + wc_price()
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['product'] = new WC_Product(42, '150.00');
$GLOBALS['__postmeta'][42]['_bw_credit_amount'] = '10';
check('price_per_unit(): 150.00 / 10 credits -> "15,00 €"', BW_Product_Pricing::shortcode_price_per_unit([]) === '15,00 €');

/* ---------------------------------------------------------------
 * 7. shortcode_price_per_unit(): via explicit product_id
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['product'] = null;
$GLOBALS['__products'][7] = new WC_Product(7, '90.00');
$GLOBALS['__postmeta'][7]['_bw_credit_amount'] = '5';
check('price_per_unit(): product_id attribute works without a global $product', BW_Product_Pricing::shortcode_price_per_unit(['product_id' => 7]) === '18,00 €');

/* ---------------------------------------------------------------
 * 8. shortcode_price(): no product -> empty
 * --------------------------------------------------------------- */
reset_state();
check('price(): no global $product, no product_id -> empty', BW_Product_Pricing::shortcode_price([]) === '');

/* ---------------------------------------------------------------
 * 9. shortcode_price(): plain formatted price, via global $product
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['product'] = new WC_Product(42, '150.00');
check('price(): formats the product\'s price via wc_price()', BW_Product_Pricing::shortcode_price([]) === '150,00 €');

/* ---------------------------------------------------------------
 * 10. shortcode_price(): an actual price of 0 is shown, not treated
 *     as "no price"
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['product'] = new WC_Product(42, '0');
check('price(): an actual price of 0 is formatted as "0,00 €", not empty', BW_Product_Pricing::shortcode_price([]) === '0,00 €');

/* ---------------------------------------------------------------
 * 11. shortcode_price(): no price set at all -> empty
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['product'] = new WC_Product(42, '');
check('price(): no price set at all -> empty (distinct from an actual 0)', BW_Product_Pricing::shortcode_price([]) === '');

/* ---------------------------------------------------------------
 * 12. shortcode_price(): via explicit product_id
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['product'] = null;
$GLOBALS['__products'][7] = new WC_Product(7, '29.90');
check('price(): product_id attribute works without a global $product', BW_Product_Pricing::shortcode_price(['product_id' => 7]) === '29,90 €');

/* ---------------------------------------------------------------
 * 13. No global $product (e.g. Elementor atomic Loop): falls back to
 *     the current post if it is a product
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__products'][9] = new WC_Product(9, '45.00');
$GLOBALS['__current_post'] = 9;
check('price(): falls back to the current product post without a global $product', BW_Product_Pricing::shortcode_price([]) === '45,00 €');

reset_state();
$GLOBALS['__current_post'] = 3; // a page, not a product
check('price(): current post is not a product -> empty', BW_Product_Pricing::shortcode_price([]) === '');

reset_state();
$GLOBALS['__products'][9] = new WC_Product(9, '45.00');
$GLOBALS['__current_post'] = 9;
$GLOBALS['product'] = new WC_Product(42, '150.00');
check('price(): global $product still wins over the current post', BW_Product_Pricing::shortcode_price([]) === '150,00 €');

printf("\n%d/%d checks passed\n", $pass, $pass + $fail);
exit($fail > 0 ? 1 : 0);
