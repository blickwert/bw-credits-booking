<?php
/**
 * Regression test: BW_Product_Features (product feature list, global
 * on/off switch, configurable slot count, [bw_product_feature]
 * shortcode). Loads the real includes/product-features.php against a
 * stub WordPress environment.
 */

define('ABSPATH', true);

$GLOBALS['__options']   = [];
$GLOBALS['__postmeta']  = [];
$GLOBALS['__current_id'] = 0;

function add_action(...$a) {}
function add_shortcode(...$a) {}
function register_setting(...$a) {}
function add_settings_field(...$a) {}

function get_option($k, $d = false) { return $GLOBALS['__options'][$k] ?? $d; }
function update_option($k, $v) { $GLOBALS['__options'][$k] = $v; return true; }
function checked($a, $b = true) { if ((string) $a === (string) $b) echo ' checked="checked"'; }

function get_post_meta($id, $key, $single = false) {
    return $GLOBALS['__postmeta'][$id][$key] ?? '';
}
function get_the_ID() { return $GLOBALS['__current_id']; }

function __($text, $domain = null) { return $text; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
function esc_html__($t, $d = null) { return __($t, $d); }
function esc_html_e($t, $d = null) { echo esc_html__($t, $d); }
function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }

function sanitize_text_field($t) { return trim((string) $t); }
function sanitize_textarea_field($t) { return trim((string) $t); }
function wp_unslash($t) { return $t; }

// Simplified real shortcode_atts(): merge known keys, ignore unknown ones.
function shortcode_atts($pairs, $atts, $shortcode = '') {
    $atts = (array) $atts;
    $out  = [];
    foreach ($pairs as $name => $default) {
        $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
    }
    return $out;
}

function woocommerce_wp_text_input($args) {}
function woocommerce_wp_textarea_input($args) {}

class BW_Settings {
    const MENU_SLUG = 'bw-credits';
}

// Fake WC_Product-like object for save_fields() tests
class FakeProduct {
    public $meta = [];
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
}

require __DIR__ . '/../includes/product-features.php';

$pass = 0;
$fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; printf("PASS  %s\n", $label); }
    else       { $fail++; printf("FAIL  %s\n", $label); }
}

function reset_state() {
    $GLOBALS['__options']    = [];
    $GLOBALS['__postmeta']   = [];
    $GLOBALS['__current_id'] = 0;
}

/* ---------------------------------------------------------------
 * 1. is_enabled()
 * --------------------------------------------------------------- */
reset_state();
check('is_enabled(): false by default (opt-in)', BW_Product_Features::is_enabled() === false);

$GLOBALS['__options'][BW_Product_Features::OPT_ENABLED] = 1;
check('is_enabled(): true once the option is set', BW_Product_Features::is_enabled() === true);

/* ---------------------------------------------------------------
 * 2. shortcode(): disabled -> always empty, regardless of data
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__postmeta'][42]['_bw_feature_1_title'] = 'Includes a mat';
check('shortcode(): empty when the global switch is off', BW_Product_Features::shortcode(['n' => '1', 'field' => 'title', 'product_id' => 42]) === '');

/* ---------------------------------------------------------------
 * 3. shortcode(): enabled, valid title/desc
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__options'][BW_Product_Features::OPT_ENABLED] = 1;
$GLOBALS['__postmeta'][42]['_bw_feature_1_title'] = 'Includes a mat';
$GLOBALS['__postmeta'][42]['_bw_feature_2_desc']  = "Line one\nLine two";

check('shortcode(): returns the title, escaped', BW_Product_Features::shortcode(['n' => '1', 'field' => 'title', 'product_id' => 42]) === 'Includes a mat');
check('shortcode(): description gets nl2br() applied', BW_Product_Features::shortcode(['n' => '2', 'field' => 'desc', 'product_id' => 42]) === "Line one&lt;br /&gt;\nLine two" || str_contains(BW_Product_Features::shortcode(['n' => '2', 'field' => 'desc', 'product_id' => 42]), '<br'));

/* ---------------------------------------------------------------
 * 4. shortcode(): invalid n / field / missing data
 * --------------------------------------------------------------- */
check('shortcode(): invalid n (4) -> empty', BW_Product_Features::shortcode(['n' => '4', 'field' => 'title', 'product_id' => 42]) === '');
check('shortcode(): invalid n (0) -> empty', BW_Product_Features::shortcode(['n' => '0', 'field' => 'title', 'product_id' => 42]) === '');
check('shortcode(): invalid field -> empty', BW_Product_Features::shortcode(['n' => '1', 'field' => 'bogus', 'product_id' => 42]) === '');
check('shortcode(): missing field attribute -> empty', BW_Product_Features::shortcode(['n' => '1', 'product_id' => 42]) === '');
check('shortcode(): unfilled field on an existing product -> empty', BW_Product_Features::shortcode(['n' => '3', 'field' => 'title', 'product_id' => 42]) === '');

/* ---------------------------------------------------------------
 * 5. shortcode(): product_id defaults to the current post
 * --------------------------------------------------------------- */
$GLOBALS['__current_id'] = 42;
check('shortcode(): falls back to get_the_ID() when product_id is omitted', BW_Product_Features::shortcode(['n' => '1', 'field' => 'title']) === 'Includes a mat');

$GLOBALS['__current_id'] = 0;
check('shortcode(): empty when neither product_id nor a current post is available', BW_Product_Features::shortcode(['n' => '1', 'field' => 'title']) === '');

/* ---------------------------------------------------------------
 * 6. save_fields(): only writes POST keys that are present, sanitized
 * --------------------------------------------------------------- */
reset_state();
$_POST = [
    '_bw_feature_1_title' => '  Padded Title  ',
    '_bw_feature_1_desc'  => "Multi\nline",
    // feature 2/3 intentionally absent from POST
];
$product = new FakeProduct();
BW_Product_Features::save_fields($product);

check('save_fields(): title is sanitized (trimmed) and saved', $product->meta['_bw_feature_1_title'] === 'Padded Title');
check('save_fields(): description is saved with line breaks intact', $product->meta['_bw_feature_1_desc'] === "Multi\nline");
check('save_fields(): fields absent from POST are not touched', !isset($product->meta['_bw_feature_2_title']) && !isset($product->meta['_bw_feature_3_desc']));

/* ---------------------------------------------------------------
 * 7. get_count() / configurable slot count
 * --------------------------------------------------------------- */
reset_state();
check('get_count(): defaults to 3', BW_Product_Features::get_count() === 3);

$GLOBALS['__options'][BW_Product_Features::OPT_COUNT] = 5;
check('get_count(): reflects a custom saved value', BW_Product_Features::get_count() === 5);

$GLOBALS['__options'][BW_Product_Features::OPT_COUNT] = -2;
check('get_count(): negative values clamp to 0', BW_Product_Features::get_count() === 0);

/* ---------------------------------------------------------------
 * 8. shortcode() honors a reduced/increased count (regression guard
 *    for range(1, 0) returning [1, 0] in PHP instead of [])
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__options'][BW_Product_Features::OPT_ENABLED] = 1;
$GLOBALS['__options'][BW_Product_Features::OPT_COUNT]   = 0;
$GLOBALS['__postmeta'][42]['_bw_feature_1_title'] = 'Includes a mat';
check('shortcode(): count 0 -> even slot 1 (already has data) is unavailable', BW_Product_Features::shortcode(['n' => '1', 'field' => 'title', 'product_id' => 42]) === '');

$GLOBALS['__options'][BW_Product_Features::OPT_COUNT] = 5;
$GLOBALS['__postmeta'][42]['_bw_feature_5_title'] = 'Feature five';
check('shortcode(): count 5 -> slot 5 becomes available', BW_Product_Features::shortcode(['n' => '5', 'field' => 'title', 'product_id' => 42]) === 'Feature five');
check('shortcode(): count 5 -> slot 6 still rejected', BW_Product_Features::shortcode(['n' => '6', 'field' => 'title', 'product_id' => 42]) === '');

printf("\n%d/%d checks passed\n", $pass, $pass + $fail);
exit($fail > 0 ? 1 : 0);
