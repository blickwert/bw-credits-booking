<?php
/**
 * Regression test: BW_Purchase_Limit — a product marked "only once per customer" can't be
 * bought again by someone who already has an order with it (account or billing e-mail), at add-to-cart
 * and at the checkout; the cart item carries a hint. Loads the real includes/purchase-limit.php
 * against a stub WordPress/WooCommerce environment.
 */

define('ABSPATH', true);

$GLOBALS['__meta']     = [];   // product id => 'yes'
$GLOBALS['__orders']   = [];   // list of ['user' => id, 'email' => string, 'status' => string, 'products' => [ids]]
$GLOBALS['__notices']  = [];
$GLOBALS['__user']     = 0;
$GLOBALS['__wpml']     = [];   // product id => [lang => id]
$GLOBALS['__queries']  = [];

function add_action(...$a) {}
function add_filter(...$a) {}
function __($t, $d = null) { return $t; }
function esc_html__($t, $d = null) { return htmlspecialchars($t); }
function apply_filters($n, $v, ...$a) {
    if ($n === 'wpml_active_languages') return ['en' => 1, 'de' => 1];
    if ($n === 'wpml_object_id') return $GLOBALS['__wpml'][$v][$a[2]] ?? null;
    return $v;
}
function has_filter($n) { return $n === 'wpml_object_id' && !empty($GLOBALS['__wpml']); }
function get_post_meta($id, $k, $single = false) { return $GLOBALS['__meta'][$id] ?? ''; }
function get_the_title($id) { return 'Beginner Special'; }
function get_current_user_id() { return $GLOBALS['__user']; }
function get_userdata($id) { return (object) ['user_email' => 'User' . $id . '@Example.test']; }
function wc_add_notice($m, $type = 'success') { $GLOBALS['__notices'][] = [$type, $m]; }

class Fake_Item { public $pid; function __construct($p) { $this->pid = $p; } function get_product_id() { return $this->pid; } }
class Fake_Order {
    public $d; function __construct($d) { $this->d = $d; }
    function get_items() { return array_map(fn($p) => new Fake_Item($p), $this->d['products']); }
}
function wc_get_orders($args) {
    $GLOBALS['__queries'][] = $args;
    $out = [];
    foreach ($GLOBALS['__orders'] as $o) {
        if (!in_array($o['status'], $args['status'], true)) continue;
        if (isset($args['customer_id']) && $o['user'] !== $args['customer_id']) continue;
        if (isset($args['billing_email']) && strtolower($o['email']) !== strtolower($args['billing_email'])) continue;
        $out[] = new Fake_Order($o);
    }
    return $out;
}

class Fake_Cart {
    public $items = []; public $removed = [];
    function get_cart() { return $this->items; }
    function remove_cart_item($k) { $this->removed[] = $k; unset($this->items[$k]); }
}
$GLOBALS['__cart'] = new Fake_Cart();
class Fake_WC { public $cart; }
function WC() { static $wc; if (!$wc) $wc = new Fake_WC(); $wc->cart = $GLOBALS['__cart']; return $wc; }
class Fake_Errors { public $e = []; function add($c, $m) { $this->e[$c][] = $m; } }

require __DIR__ . '/../includes/purchase-limit.php';

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; printf("PASS  %s\n", $label); } else { $fail++; printf("FAIL  %s\n", $label); }
}
function reset_state() {
    $GLOBALS['__notices'] = []; $GLOBALS['__orders'] = []; $GLOBALS['__user'] = 0;
    $GLOBALS['__cart'] = new Fake_Cart(); $GLOBALS['__wpml'] = [];
}

$GLOBALS['__meta'] = [159 => 'yes', 1263 => 'yes'];

reset_state();
check('is_limited(): marked product', BW_Purchase_Limit::is_limited(159));
check('is_limited(): unmarked product', !BW_Purchase_Limit::is_limited(158));
$GLOBALS['__meta'] = [1263 => 'yes'];
$GLOBALS['__wpml'] = [159 => ['en' => 159, 'de' => 1263]];
check('is_limited(): the flag on a translation counts for the original', BW_Purchase_Limit::is_limited(159));
check('product_ids(): the product and its translations', BW_Purchase_Limit::product_ids(159) === [159, 1263]);
$GLOBALS['__meta'] = [159 => 'yes', 1263 => 'yes'];

reset_state();
$GLOBALS['__orders'] = [['user' => 7, 'email' => 'a@x.test', 'status' => 'completed', 'products' => [159]]];
check('has_bought(): by account', BW_Purchase_Limit::has_bought([159], 7));
check('has_bought(): other account, no e-mail', !BW_Purchase_Limit::has_bought([159], 8));
check('has_bought(): by billing e-mail (any case)', BW_Purchase_Limit::has_bought([159], 0, 'A@X.test'));
check('has_bought(): another product', !BW_Purchase_Limit::has_bought([158], 7));
$GLOBALS['__orders'] = [['user' => 7, 'email' => 'a@x.test', 'status' => 'pending', 'products' => [159]]];
check('has_bought(): an unpaid (pending) order does not count', !BW_Purchase_Limit::has_bought([159], 7));
$GLOBALS['__orders'] = [['user' => 7, 'email' => 'a@x.test', 'status' => 'on-hold', 'products' => [159]]];
check('has_bought(): on hold counts (bank transfer waiting)', BW_Purchase_Limit::has_bought([159], 7));
$GLOBALS['__orders'] = [['user' => 7, 'email' => 'a@x.test', 'status' => 'cancelled', 'products' => [159]]];
check('has_bought(): a cancelled order does not count', !BW_Purchase_Limit::has_bought([159], 7));
$GLOBALS['__orders'] = [['user' => 7, 'email' => 'a@x.test', 'status' => 'completed', 'products' => [1263]]];
check('has_bought(): bought the other language version', BW_Purchase_Limit::has_bought([159, 1263], 7));

/* add to cart */
reset_state();
$GLOBALS['__orders'] = [['user' => 7, 'email' => 'user7@example.test', 'status' => 'completed', 'products' => [159]]];
$GLOBALS['__user'] = 7;
check('add to cart: repeat buyer is stopped', BW_Purchase_Limit::validate_add_to_cart(true, 159) === false);
check('add to cart: ... with an error notice', ($GLOBALS['__notices'][0][0] ?? '') === 'error' && str_contains($GLOBALS['__notices'][0][1], 'Beginner Special'));
$GLOBALS['__notices'] = [];
check('add to cart: an unmarked product passes', BW_Purchase_Limit::validate_add_to_cart(true, 158) === true);
$GLOBALS['__user'] = 9;
check('add to cart: someone who never bought passes', BW_Purchase_Limit::validate_add_to_cart(true, 159) === true);
$GLOBALS['__user'] = 0;
check('add to cart: guests pass (checked at the checkout)', BW_Purchase_Limit::validate_add_to_cart(true, 159) === true);
$GLOBALS['__user'] = 7;
check('add to cart: an earlier refusal stays refused', BW_Purchase_Limit::validate_add_to_cart(false, 159) === false);

/* cart */
reset_state();
$GLOBALS['__orders'] = [['user' => 7, 'email' => 'user7@example.test', 'status' => 'completed', 'products' => [159]]];
$GLOBALS['__user'] = 7;
$GLOBALS['__cart']->items = ['k1' => ['product_id' => 159], 'k2' => ['product_id' => 158]];
BW_Purchase_Limit::check_cart();
check('check_cart(): the limited product is removed, the other stays', $GLOBALS['__cart']->removed === ['k1'] && isset($GLOBALS['__cart']->items['k2']));
check('check_cart(): with an error notice', count($GLOBALS['__notices']) === 1);

/* checkout */
reset_state();
$GLOBALS['__orders'] = [['user' => 0, 'email' => 'guest@x.test', 'status' => 'processing', 'products' => [159]]];
$GLOBALS['__cart']->items = ['k1' => ['product_id' => 159]];
$err = new Fake_Errors();
BW_Purchase_Limit::validate_checkout(['billing_email' => 'Guest@x.test'], $err);
check('checkout: a guest with an earlier order (same e-mail) is blocked', isset($err->e['bw_once_per_customer']));
$err = new Fake_Errors();
BW_Purchase_Limit::validate_checkout(['billing_email' => 'new@x.test'], $err);
check('checkout: a new e-mail address passes', !$err->e);
$GLOBALS['__cart']->items = ['k1' => ['product_id' => 158]];
$err = new Fake_Errors();
BW_Purchase_Limit::validate_checkout(['billing_email' => 'guest@x.test'], $err);
check('checkout: an unmarked product is never blocked', !$err->e);

/* hint */
check('cart item hint: marked product gets the hint', str_contains(BW_Purchase_Limit::cart_item_hint('Beginner Special', ['product_id' => 159]), 'Only available once per person.'));
check('cart item hint: unmarked product unchanged', BW_Purchase_Limit::cart_item_hint('Single Class', ['product_id' => 158]) === 'Single Class');

printf("\n%d/%d checks passed\n", $pass, $pass + $fail);
exit($fail ? 1 : 0);
