<?php
/**
 * Regression test: BW_Newsletter — the opt-in checkbox at registration/checkout is off by default;
 * when ticked it starts the Brevo plugin's double opt-in of the language form, skips addresses that are
 * already on the list and handles an address only once per request. Loads the real
 * includes/newsletter.php against stubs of WordPress and the Brevo plugin.
 */

define('ABSPATH', true);

$GLOBALS['__opts']  = [];
$GLOBALS['__post']  = [];
$GLOBALS['__sent']  = [];   // [email, templateId]
$GLOBALS['__codes'] = [];
$GLOBALS['__lang']  = 'de';
$GLOBALS['__contact'] = null; // listIds of an existing contact, null = unknown contact

function add_action(...$a) {}
function add_filter(...$a) {}
function get_option($k, $d = false) { return $GLOBALS['__opts'][$k] ?? $d; }
function apply_filters($n, $v, ...$a) { return $n === 'wpml_current_language' ? $GLOBALS['__lang'] : $v; }
function get_locale() { return 'en_US'; }
function sanitize_email($e) { return trim($e); }
function is_email($e) { return (bool) preg_match('/^[^@\s]+@[^@\s]+\.[a-z]+$/i', $e); }
function wp_unslash($v) { return $v; }
function get_current_user_id() { return 0; }
function get_userdata($id) { return false; }
function bw_text($k) { return 'TEXT:' . $k; }
$GLOBALS['__transients'] = [];
function get_transient($k) { return $GLOBALS['__transients'][$k] ?? false; }
function set_transient($k, $v, $ttl = 0) { $GLOBALS['__transients'][$k] = $v; return true; }
class Fake_Coupon { public $c; function __construct($c) { $this->c = $c; } function get_code() { return $this->c; } }
function maybe_unserialize($v) { return is_string($v) && $v !== '' && @unserialize($v) !== false ? unserialize($v) : $v; }

class SIB_Forms {
    static function getForm($id) {
        $forms = [
            4 => ['isDopt' => '1', 'listID' => serialize(['10']), 'templateID' => '94', 'redirectInEmail' => 'https://x/de/newsletter-subscribed/'],
            5 => ['isDopt' => '1', 'listID' => serialize(['11']), 'templateID' => '98', 'redirectInEmail' => 'https://x/newsletter-subscribed/'],
        ];
        return $forms[$id] ?? [];
    }
}
class SIB_Manager {
    static $instance;
    function create_activate_code($email, $info, $form, $lists, $redirect) { $GLOBALS['__codes'][] = [$email, $info, $form, $lists, $redirect]; return 'code' . count($GLOBALS['__codes']); }
}
SIB_Manager::$instance = new SIB_Manager();
class SIB_API_Manager {
    static function send_comfirm_email($to, $type, $tpl, $info, $code) { $GLOBALS['__sent'][] = [$to, $type, $tpl, $code]; }
}
class SendinblueApiClient {
    function get($path) { $c = $GLOBALS['__contact']; return $c === null ? [] : ['listIds' => $c]; }
    function getLastResponseCode() { return $GLOBALS['__contact'] === null ? 404 : 200; }
}

require __DIR__ . '/../includes/newsletter.php';

$pass = 0; $fail = 0;
function check($name, $ok) { global $pass, $fail; if ($ok) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name\n"; } }
function reset_state() { $GLOBALS['__sent'] = []; $GLOBALS['__codes'] = []; $GLOBALS['__contact'] = null; $r = new ReflectionProperty('BW_Newsletter', 'handled'); $r->setAccessible(true); $r->setValue(null, []); }

check('off by default', BW_Newsletter::enabled() === false);
$GLOBALS['__opts']['bw_newsletter_optin_enabled'] = 1;
check('enabled when set', BW_Newsletter::enabled() === true);
check('German form id', BW_Newsletter::form_id_for('de') === 4);
check('other languages use the English form', BW_Newsletter::form_id_for('en') === 5 && BW_Newsletter::form_id_for('fr') === 5);

reset_state();
check('subscribe sends the DOI mail of the German form', BW_Newsletter::subscribe('a@example.test', 'Anna', 'de') === true
    && $GLOBALS['__sent'] === [['a@example.test', 'double-optin', '94', 'code1']]
    && $GLOBALS['__codes'][0][2] === 4 && $GLOBALS['__codes'][0][3] === ['10'] && $GLOBALS['__codes'][0][1] === ['VORNAME' => 'Anna']);

reset_state();
BW_Newsletter::subscribe('b@example.test', '', 'en');
check('English form, no first name -> no attribute', $GLOBALS['__sent'][0][2] === '98' && $GLOBALS['__codes'][0][1] === []);

reset_state();
BW_Newsletter::subscribe('c@example.test', '', 'de');
check('same address only once per request', BW_Newsletter::subscribe('C@example.test', '', 'de') === false && count($GLOBALS['__sent']) === 1);

reset_state();
$GLOBALS['__contact'] = [10, 9];
check('already on the list -> no mail', BW_Newsletter::subscribe('d@example.test', '', 'de') === false && !$GLOBALS['__sent']);

reset_state();
$GLOBALS['__contact'] = [9];
check('only on the handbook list -> mail is sent', BW_Newsletter::subscribe('e@example.test', '', 'de') === true);

reset_state();
check('invalid address -> nothing', BW_Newsletter::subscribe('nope', '', 'de') === false && !$GLOBALS['__sent']);

$credit = fn($id) => in_array($id, [158, 160, 159, 1263], true);
$special = [159, 1263];
check('cart with a single class -> swap offer applies', BW_Newsletter::cart_state([158], $special, $credit) === ['has_special' => false, 'other_credit' => true]);
check('cart with the special (translated id) -> has_special', BW_Newsletter::cart_state([1263], $special, $credit) === ['has_special' => true, 'other_credit' => false]);
check('cart with a non-credit product -> nothing', BW_Newsletter::cart_state([999], $special, $credit) === ['has_special' => false, 'other_credit' => false]);
check('empty cart -> nothing', BW_Newsletter::cart_state([], $special, $credit) === ['has_special' => false, 'other_credit' => false]);

$GLOBALS['__opts']['bw_newsletter_coupon'] = 'NAMASTE40';
$_POST['billing_email'] = 'sub@example.test';
$GLOBALS['__contact'] = [10]; $GLOBALS['__transients'] = [];
check('subscriber of a newsletter list is recognised', BW_Newsletter::is_subscribed('sub@example.test') === true);
check('coupon valid for a confirmed subscriber', BW_Newsletter::validate_coupon(true, new Fake_Coupon('namaste40')) === true);
$_POST['billing_email'] = 'other@example.test'; $GLOBALS['__contact'] = [9]; // handbook list only
$denied = false; try { BW_Newsletter::validate_coupon(true, new Fake_Coupon('NAMASTE40')); } catch (\Exception $e) { $denied = $e->getMessage() === 'TEXT:newsletter.coupon_denied'; }
check('coupon refused for someone who is not subscribed', $denied);
check('other coupons are not touched', BW_Newsletter::validate_coupon(true, new Fake_Coupon('SUMMER')) === true);
$_POST['billing_email'] = ''; $GLOBALS['__contact'] = null;
$denied = false; try { BW_Newsletter::validate_coupon(true, new Fake_Coupon('NAMASTE40')); } catch (\Exception $e) { $denied = true; }
check('coupon refused while the e-mail address is unknown', $denied);
$GLOBALS['__contact'] = [10]; $GLOBALS['__transients'] = []; $_POST['billing_email'] = 'sub@example.test';
$GLOBALS['__contact'] = [10, 9]; $GLOBALS['__transients'] = [];
BW_Newsletter::is_subscribed('sub@example.test'); $GLOBALS['__contact'] = null;
check('result is cached (no second lookup)', BW_Newsletter::is_subscribed('sub@example.test') === true);

echo "\n$pass/" . ($pass + $fail) . " checks passed\n";
exit($fail ? 1 : 0);
