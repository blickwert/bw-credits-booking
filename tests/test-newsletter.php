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
function get_option($k, $d = false) { return $GLOBALS['__opts'][$k] ?? $d; }
function apply_filters($n, $v, ...$a) { return $n === 'wpml_current_language' ? $GLOBALS['__lang'] : $v; }
function get_locale() { return 'en_US'; }
function sanitize_email($e) { return trim($e); }
function is_email($e) { return (bool) preg_match('/^[^@\s]+@[^@\s]+\.[a-z]+$/i', $e); }
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

echo "\n$pass/" . ($pass + $fail) . " checks passed\n";
exit($fail ? 1 : 0);
