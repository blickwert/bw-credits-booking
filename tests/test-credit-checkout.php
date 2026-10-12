<?php
/**
 * Regression test: BW_Credit_Checkout — credit products go straight to the
 * checkout, replace the cart, are never mixed with other products, and orders
 * that consist only of credit products are completed after payment.
 * Loads the real includes/credit-checkout.php against a stub WordPress/WooCommerce environment.
 */

define('ABSPATH', true);

$GLOBALS['__hooks']   = [];
$GLOBALS['__meta']    = [];   // product_id => credit amount
$GLOBALS['__types']   = [];   // id => post type
$GLOBALS['__parent']  = [];   // variation id => parent id
$GLOBALS['__notices'] = [];
$GLOBALS['__direct']  = true;
$GLOBALS['__auto']    = true;

function add_filter($n, $cb, $p = 10, $a = 1) { $GLOBALS['__hooks'][$n][] = $cb; }
function add_action($n, $cb, $p = 10, $a = 1) { $GLOBALS['__hooks'][$n][] = $cb; }
function __($t, $d = null) { return $t; }
function bw_text($k) { return '[' . $k . ']'; }
function get_post_type($id) { return $GLOBALS['__types'][$id] ?? 'product'; }
function wp_get_post_parent_id($id) { return $GLOBALS['__parent'][$id] ?? 0; }
function get_post_meta($id, $k, $single = false) { return $k === '_bw_credit_amount' ? ($GLOBALS['__meta'][$id] ?? '') : ''; }
function wc_add_notice($m, $type = 'success') { $GLOBALS['__notices'][] = [$type, $m]; }
function wc_get_checkout_url() { return 'https://shop.test/checkout/'; }

class BW_Credits_Bookings_MVP { const PM_CREDIT_AMOUNT = '_bw_credit_amount'; }
class BW_Settings {
    public static function get_credit_direct_checkout(): bool { return $GLOBALS['__direct']; }
    public static function get_credit_auto_complete(): bool { return $GLOBALS['__auto']; }
}

class Fake_Cart {
    public $items = [];
    public $emptied = 0;
    public function is_empty() { return !$this->items; }
    public function get_cart() { return $this->items; }
    public function empty_cart() { $this->items = []; $this->emptied++; }
}
$GLOBALS['__cart'] = new Fake_Cart();
class Fake_WC { public $cart; }
function WC() { static $wc; if (!$wc) { $wc = new Fake_WC(); } $wc->cart = $GLOBALS['__cart']; return $wc; }

class Fake_Product { public $id; function __construct($id) { $this->id = $id; } function get_id() { return $this->id; } }
class Fake_Item { public $pid; function __construct($pid) { $this->pid = $pid; } function get_product_id() { return $this->pid; } }
class Fake_Order {
    public $items, $user, $status = 'processing', $updated = [];
    function __construct(array $pids, int $user = 5) { $this->items = array_map(fn($p) => new Fake_Item($p), $pids); $this->user = $user; }
    function get_items() { return $this->items; }
    function get_user_id() { return $this->user; }
    function get_status() { return $this->status; }
    function update_status($s, $note = '') { $this->status = $s; $this->updated[] = [$s, $note]; }
}

require __DIR__ . '/../includes/credit-checkout.php';

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; printf("PASS  %s\n", $label); } else { $fail++; printf("FAIL  %s\n", $label); }
}
function reset_state() {
    $GLOBALS['__cart'] = new Fake_Cart();
    $GLOBALS['__notices'] = [];
    $GLOBALS['__direct'] = true;
    $GLOBALS['__auto'] = true;
}
function cart_with(array $pids) { foreach ($pids as $p) $GLOBALS['__cart']->items[] = ['product_id' => $p]; }

// 10 = package, 11 = other package, 20 = private session (no credits), 30 = variable credit product with variation 31
$GLOBALS['__meta']   = [10 => 3, 11 => 10, 30 => 5];
$GLOBALS['__types']  = [31 => 'product_variation'];
$GLOBALS['__parent'] = [31 => 30];

/* --- which products are credit products --- */
check('credit product: credit amount greater than 0', BW_Credit_Checkout::is_credit_product(10));
check('no credit amount: not a credit product', !BW_Credit_Checkout::is_credit_product(20));
check('variation: the parent decides', BW_Credit_Checkout::is_credit_product(31));
check('invalid id: not a credit product', !BW_Credit_Checkout::is_credit_product(0));

/* --- hooks are registered --- */
foreach (['woocommerce_add_to_cart_validation', 'woocommerce_add_to_cart_redirect', 'woocommerce_is_sold_individually', 'woocommerce_loop_add_to_cart_args', 'woocommerce_check_cart_items', 'woocommerce_payment_complete_order_status', 'woocommerce_order_status_processing', 'woocommerce_email_enabled_customer_processing_order'] as $h) {
    check("hook registered: $h", !empty($GLOBALS['__hooks'][$h]));
}

/* --- adding to the cart --- */
reset_state();
check('empty cart: a credit product is added as it is', BW_Credit_Checkout::validate_add_to_cart(true, 10) === true && $GLOBALS['__cart']->emptied === 0 && !$GLOBALS['__notices']);

reset_state(); cart_with([20]);
$r = BW_Credit_Checkout::validate_add_to_cart(true, 10);
check('another product in the cart: the cart is replaced', $r === true && $GLOBALS['__cart']->emptied === 1 && $GLOBALS['__cart']->is_empty());
check('replaced cart: the customer is told', $GLOBALS['__notices'] === [['notice', '[cart.credit_replaced]']]);

reset_state(); cart_with([10]);
$r = BW_Credit_Checkout::validate_add_to_cart(true, 10);
check('same credit product again: replaced without a notice', $r === true && $GLOBALS['__cart']->emptied === 1 && !$GLOBALS['__notices']);

reset_state(); cart_with([10]);
$r = BW_Credit_Checkout::validate_add_to_cart(true, 11);
check('a different package replaces the first (with a notice)', $r === true && $GLOBALS['__cart']->emptied === 1 && $GLOBALS['__notices'] === [['notice', '[cart.credit_replaced]']]);

reset_state(); cart_with([10]);
$r = BW_Credit_Checkout::validate_add_to_cart(true, 20);
check('credit product in the cart: another product is refused with an error', $r === false && $GLOBALS['__notices'] === [['error', '[cart.credit_only]']] && !$GLOBALS['__cart']->is_empty());

reset_state(); cart_with([20]);
check('no credit product involved: nothing changes', BW_Credit_Checkout::validate_add_to_cart(true, 20) === true && !$GLOBALS['__notices'] && $GLOBALS['__cart']->emptied === 0);

reset_state(); cart_with([20]);
check('an earlier failed validation stays failed', BW_Credit_Checkout::validate_add_to_cart(false, 10) === false && $GLOBALS['__cart']->emptied === 0);

reset_state(); cart_with([20]); $GLOBALS['__direct'] = false;
check('switched off: the cart is left alone', BW_Credit_Checkout::validate_add_to_cart(true, 10) === true && $GLOBALS['__cart']->emptied === 0);

/* --- redirect, quantity, shop button --- */
reset_state();
check('credit product: redirect to the checkout', BW_Credit_Checkout::redirect_to_checkout(false, new Fake_Product(10)) === 'https://shop.test/checkout/');
check('other product: redirect unchanged', BW_Credit_Checkout::redirect_to_checkout(false, new Fake_Product(20)) === false);
check('variable credit product: redirect to the checkout', BW_Credit_Checkout::redirect_to_checkout('x', new Fake_Product(30)) === 'https://shop.test/checkout/');
check('no product given: redirect unchanged', BW_Credit_Checkout::redirect_to_checkout('x', false) === 'x');
check('credit product is sold individually', BW_Credit_Checkout::sold_individually(false, new Fake_Product(10)) === true && BW_Credit_Checkout::sold_individually(false, new Fake_Product(20)) === false);
$args = BW_Credit_Checkout::loop_button_args(['class' => 'button add_to_cart_button ajax_add_to_cart'], new Fake_Product(10));
check('shop button: no AJAX class for a credit product', $args['class'] === 'button add_to_cart_button');
$args = BW_Credit_Checkout::loop_button_args(['class' => 'button ajax_add_to_cart'], new Fake_Product(20));
check('shop button: other products keep AJAX', $args['class'] === 'button ajax_add_to_cart');
$GLOBALS['__direct'] = false;
check('switched off: redirect, quantity and button unchanged', BW_Credit_Checkout::redirect_to_checkout(false, new Fake_Product(10)) === false && BW_Credit_Checkout::sold_individually(false, new Fake_Product(10)) === false && BW_Credit_Checkout::loop_button_args(['class' => 'ajax_add_to_cart'], new Fake_Product(10))['class'] === 'ajax_add_to_cart');

/* --- cart check --- */
reset_state(); cart_with([10, 20]); BW_Credit_Checkout::check_cart();
check('mixed cart: an error blocks the checkout', $GLOBALS['__notices'] === [['error', '[cart.credit_only]']]);
reset_state(); cart_with([10, 11]); BW_Credit_Checkout::check_cart();
check('only credit products: fine', !$GLOBALS['__notices']);
reset_state(); cart_with([20]); BW_Credit_Checkout::check_cart();
check('only other products: fine', !$GLOBALS['__notices']);

/* --- orders --- */
reset_state();
check('payment_complete: credit-only order of a customer → completed', BW_Credit_Checkout::payment_complete_status('processing', 1, new Fake_Order([10])) === 'completed');
check('payment_complete: two packages → completed', BW_Credit_Checkout::payment_complete_status('processing', 1, new Fake_Order([10, 11])) === 'completed');
check('payment_complete: with another product → stays processing', BW_Credit_Checkout::payment_complete_status('processing', 1, new Fake_Order([10, 20])) === 'processing');
check('payment_complete: without credit products → stays processing', BW_Credit_Checkout::payment_complete_status('processing', 1, new Fake_Order([20])) === 'processing');
check('payment_complete: guest order (no credits can be granted) → stays processing', BW_Credit_Checkout::payment_complete_status('processing', 1, new Fake_Order([10], 0)) === 'processing');
check('payment_complete: other statuses (e.g. on-hold) are not touched', BW_Credit_Checkout::payment_complete_status('on-hold', 1, new Fake_Order([10])) === 'on-hold');
check('payment_complete: empty order → unchanged', BW_Credit_Checkout::payment_complete_status('processing', 1, new Fake_Order([])) === 'processing');
check('payment_complete: no order object → unchanged', BW_Credit_Checkout::payment_complete_status('processing', 1, null) === 'processing');
$GLOBALS['__auto'] = false;
check('payment_complete: switched off → unchanged', BW_Credit_Checkout::payment_complete_status('processing', 1, new Fake_Order([10])) === 'processing');
$GLOBALS['__auto'] = true;

$o = new Fake_Order([10]);
BW_Credit_Checkout::complete_after_manual_processing(1, $o);
check('set to processing by hand: credit-only order is completed with a note', $o->status === 'completed' && count($o->updated) === 1 && str_contains($o->updated[0][1], 'credit products'));
$o = new Fake_Order([10, 20]);
BW_Credit_Checkout::complete_after_manual_processing(1, $o);
check('set to processing by hand: mixed order is left alone', $o->status === 'processing' && !$o->updated);
$o = new Fake_Order([10]); $o->status = 'on-hold';
BW_Credit_Checkout::complete_after_manual_processing(1, $o);
check('only orders that are processing are completed', $o->status === 'on-hold');

check('processing mail: skipped for such an order', BW_Credit_Checkout::skip_processing_mail(true, new Fake_Order([10])) === false);
check('processing mail: kept for other orders', BW_Credit_Checkout::skip_processing_mail(true, new Fake_Order([20])) === true);
check('processing mail: already disabled stays disabled, no order is fine', BW_Credit_Checkout::skip_processing_mail(false, new Fake_Order([20])) === false && BW_Credit_Checkout::skip_processing_mail(true, null) === true);

printf("\n%d/%d checks passed\n", $pass, $pass + $fail);
exit($fail ? 1 : 0);
