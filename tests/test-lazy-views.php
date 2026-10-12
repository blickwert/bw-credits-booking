<?php
/**
 * Regression test: BW_Lazy_Views — placeholders for user- and time-dependent
 * shortcodes, the signed payload and the REST route that fills them in.
 * Loads the real includes/lazy-views.php against a stub WordPress environment.
 */

define('ABSPATH', true);

$GLOBALS['__filters']  = [];
$GLOBALS['__switched'] = [];
$GLOBALS['__lang']     = 'de';
$GLOBALS['__calls']    = [];

function add_action(...$a) {}
function apply_filters($n, $v, ...$a) {
    if ($n === 'wpml_current_language') return $GLOBALS['__lang'];
    return array_key_exists($n, $GLOBALS['__filters']) ? $GLOBALS['__filters'][$n] : $v;
}
function has_action($n) { return $n === 'wpml_switch_language'; }
function do_action($n, ...$a) { if ($n === 'wpml_switch_language') { $GLOBALS['__switched'][] = $a[0]; $GLOBALS['__lang'] = $a[0]; } }
function is_admin() { return !empty($GLOBALS['__is_admin']); }
function wp_salt($scheme = 'auth') { return 'test-secret-' . $scheme; }
function wp_json_encode($v) { return json_encode($v, JSON_UNESCAPED_SLASHES); }
function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
function register_rest_route(...$a) { $GLOBALS['__routes'][] = $a; }

class WP_REST_Request {
    private $p;
    public function __construct($p) { $this->p = $p; }
    public function get_param($k) { return $this->p[$k] ?? null; }
}
class WP_REST_Response {
    public $data, $status, $headers = [];
    public function __construct($data = null, $status = 200) { $this->data = $data; $this->status = $status; }
    public function header($k, $v) { $this->headers[$k] = $v; }
}

class BW_Credits_Bookings_MVP {
    public static function ensure_assets() { $GLOBALS['__assets'] = true; }
    public static function resolve_course_id(int $id): int { return $id > 0 ? $id : (int) ($GLOBALS['__current_course'] ?? 0); }
    public static function sc_slot_action($atts)   { $GLOBALS['__calls'][] = ['book', $atts]; return '<button>book ' . ($atts['course_id'] ?? '') . '</button>'; }
    public static function sc_availability($atts)  { $GLOBALS['__calls'][] = ['avail', $atts]; return '<i>free</i>'; }
    public static function sc_balance($atts)       { return '<b>5</b>'; }
    public static function sc_my_bookings($atts)   { return '<ul></ul>'; }
}
class BW_View_Access   { public static function render($a) { return 'access'; } }
class BW_View_Credits  { public static function render($a) { return 'credits'; } }
class BW_View_Overview { public static function render($a) { return BW_Lazy_Views::render('bw_credits_user_balance', []); } }

require __DIR__ . '/../includes/lazy-views.php';

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; printf("PASS  %s\n", $label); } else { $fail++; printf("FAIL  %s\n", $label); }
}

/** Reads payload and signature from a placeholder like the browser does. */
function placeholder_parts(string $html): array {
    preg_match('/data-bw-view="([^"]*)" data-bw-sig="([^"]*)"/', $html, $m);
    return [html_entity_decode($m[1] ?? '', ENT_QUOTES), $m[2] ?? ''];
}
function call_views(array $items) {
    return BW_Lazy_Views::rest_views(new WP_REST_Request(['items' => $items]));
}

/* --- placeholder ---------------------------------------------------- */
$html = BW_Lazy_Views::render('bw_credits_user_balance', ['format' => 'block']);
check('shortcode: outputs a placeholder instead of the content', str_starts_with($html, '<span class="bw-lazy-view"') && !str_contains($html, '<b>5</b>'));
check('placeholder: carries aria-busy for the loading state', str_contains($html, 'aria-busy="true"'));
check('placeholder: enqueues the assets', !empty($GLOBALS['__assets']));
[$payload, $sig] = placeholder_parts($html);
check('placeholder: payload holds tag, attributes and language', json_decode($payload, true) === ['t' => 'bw_credits_user_balance', 'a' => ['format' => 'block'], 'l' => 'de']);
check('placeholder: signature matches the payload', BW_Lazy_Views::verify($payload, $sig));
check('placeholder: a changed payload no longer verifies', !BW_Lazy_Views::verify(str_replace('block', 'inline', $payload), $sig));
check('placeholder: a different signature does not verify', !BW_Lazy_Views::verify($payload, str_repeat('0', 64)) && !BW_Lazy_Views::verify($payload, ''));
check('unknown tag renders nothing', BW_Lazy_Views::render('bw_credits_nope', []) === '');

/* --- sessions: course_id is fixed at render time -------------------- */
$GLOBALS['__current_course'] = 0;
check('course view without a session prints nothing', BW_Lazy_Views::render('bw_credits_course_booking', []) === '');
$GLOBALS['__current_course'] = 42;
[$payload] = placeholder_parts(BW_Lazy_Views::render('bw_credits_course_booking', []));
check('course view: the current session ID lands in the payload', json_decode($payload, true)['a'] === ['course_id' => '42']);
[$payload] = placeholder_parts(BW_Lazy_Views::render('bw_credits_course_availability', ['slot_id' => 7]));
check('course view: old name slot_id becomes course_id', json_decode($payload, true)['a'] === ['course_id' => '7']);

/* --- attributes ----------------------------------------------------- */
$clean = BW_Lazy_Views::clean_atts(['ok' => 'x', 'n' => 5, 'arr' => ['a'], 'obj' => new stdClass(), 'bad key!' => 'x', 'long' => str_repeat('a', 900)]);
check('clean_atts(): only simple values with valid names', array_keys($clean) === ['ok', 'n', 'long'] && $clean['n'] === '5');
check('clean_atts(): long values are cut', strlen($clean['long']) === 500);

/* --- REST route ------------------------------------------------------ */
BW_Lazy_Views::register_route();
check('route: POST /bw-credits/v1/views is registered', ($GLOBALS['__routes'][0][0] ?? '') === 'bw-credits/v1' && ($GLOBALS['__routes'][0][1] ?? '') === '/views' && ($GLOBALS['__routes'][0][2]['methods'] ?? '') === 'POST');

[$p1, $s1] = placeholder_parts(BW_Lazy_Views::render('bw_credits_course_booking', ['course_id' => 42]));
[$p2, $s2] = placeholder_parts(BW_Lazy_Views::render('bw_credits_user_balance', []));
$GLOBALS['__calls'] = [];
$res = call_views([['id' => 'a', 'p' => $p1, 's' => $s1], ['id' => 'b', 'p' => $p2, 's' => $s2]]);
check('route: signed items are rendered', $res->data['views']['a'] === ['ok' => true, 'html' => '<button>book 42</button>'] && $res->data['views']['b']['html'] === '<b>5</b>');
check('route: the shortcode gets the signed attributes', ($GLOBALS['__calls'][0][1] ?? null) === ['course_id' => '42']);
check('route: response must not be cached', ($res->headers['Cache-Control'] ?? '') === 'no-store, private, max-age=0' && ($res->headers['Vary'] ?? '') === 'Cookie');

$forged = str_replace('"course_id":"42"', '"course_id":"43"', $p1);
$res = call_views([['id' => 'a', 'p' => $forged, 's' => $s1]]);
check('route: altered slot is rejected', $res->data['views']['a'] === ['ok' => false]);

$evil = wp_json_encode(['t' => 'wp_delete_post', 'a' => [], 'l' => '']);
$res = call_views([['id' => 'a', 'p' => $evil, 's' => BW_Lazy_Views::sign($evil)]]);
check('route: even a correctly signed unknown tag is not run (whitelist)', $res->data['views']['a'] === ['ok' => false]);

$res = call_views([['id' => 'a', 'p' => $p1, 's' => 'abc']]);
check('route: wrong signature is rejected', $res->data['views']['a'] === ['ok' => false]);

$cl = 'course_list|' . $p2;
$res = call_views([['id' => 'a', 'p' => $cl, 's' => BW_Lazy_Views::sign($cl)]]);
check('route: a signature for another purpose (course list) is no view', $res->data['views']['a'] === ['ok' => false]);

$res = call_views([['id' => 'bad id!', 'p' => $p1, 's' => $s1], 'not-an-array', ['id' => 'x']]);
check('route: malformed items are skipped', $res->data['views'] === []);

$big = str_repeat('a', 5000);
$res = call_views([['id' => 'a', 'p' => $big, 's' => BW_Lazy_Views::sign($big)]]);
check('route: oversized payload is rejected', $res->data['views']['a'] === ['ok' => false]);

$many = [];
for ($i = 0; $i < 31; $i++) $many[] = ['id' => 'i' . $i, 'p' => $p2, 's' => $s2];
check('route: more than 30 items → 400', call_views($many)->status === 400);
check('route: empty or wrong items → 400', call_views([])->status === 400 && BW_Lazy_Views::rest_views(new WP_REST_Request(['items' => 'x']))->status === 400);

/* --- language -------------------------------------------------------- */
$GLOBALS['__lang'] = 'en'; $GLOBALS['__switched'] = [];
$res = call_views([['id' => 'a', 'p' => $p2, 's' => $s2]]);   // payload was created on a de page
check('language: the route renders in the placeholder\'s language and switches back', $GLOBALS['__switched'] === ['de', 'en']);

$bad = wp_json_encode(['t' => 'bw_credits_user_balance', 'a' => [], 'l' => '../../x']);
$GLOBALS['__switched'] = [];
call_views([['id' => 'a', 'p' => $bad, 's' => BW_Lazy_Views::sign($bad)]]);
check('language: an invalid code is not passed on', in_array('../../x', $GLOBALS['__switched'], true) === false);

/* --- direct rendering ------------------------------------------------ */
$GLOBALS['__filters']['bw_lazy_views_enabled'] = false;
check('switched off: the shortcode renders directly', BW_Lazy_Views::render('bw_credits_user_balance', []) === '<b>5</b>');
unset($GLOBALS['__filters']['bw_lazy_views_enabled']);

$GLOBALS['__is_admin'] = true;
check('admin / editor: renders directly', BW_Lazy_Views::render('bw_credits_user_balance', []) === '<b>5</b>');
$GLOBALS['__is_admin'] = false;

$res = call_views([['id' => 'o', 'p' => wp_json_encode(['t' => 'bw_credits_view_overview', 'a' => [], 'l' => 'de']), 's' => BW_Lazy_Views::sign(wp_json_encode(['t' => 'bw_credits_view_overview', 'a' => [], 'l' => 'de']))]]);
check('route: nested views render real content, not placeholders', $res->data['views']['o']['html'] === '<b>5</b>');
check('after the route the page renders placeholders again', str_starts_with(BW_Lazy_Views::render('bw_credits_user_balance', []), '<span class="bw-lazy-view"'));

printf("\n%d/%d checks passed\n", $pass, $pass + $fail);
exit($fail ? 1 : 0);
