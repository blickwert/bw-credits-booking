<?php
/**
 * Regression test: persistent per-customer email language
 * (BW_Email_Language) driving BW_Emails::send() — the gettext-resolved
 * default and the WPML-translated override both use the customer's
 * saved language preference, not the page/session context. Loads the
 * real emails.php and email-language.php against a stub WordPress/
 * WooCommerce/WPML environment (locale + gettext + user-meta +
 * wpml_active_languages all simulated in-process).
 */

define('ABSPATH', true);

$GLOBALS['__options']   = [];
$GLOBALS['__usermeta']  = [];
$GLOBALS['__locale']    = 'en_US'; // the WP site's "current" locale, switch_to_locale() changes this
$GLOBALS['__gettext']   = []; // [locale][english] => translated, simulates per-locale .mo files
$GLOBALS['__wpml_map']  = []; // name => translated value (simulates WPML String Translation)
$GLOBALS['__wpml_on']   = true;
$GLOBALS['__wpml_default_lang'] = 'en';
$GLOBALS['__wpml_current_lang'] = 'en';
$GLOBALS['__wpml_languages'] = [
    'en' => ['native_name' => 'English', 'default_locale' => 'en_US'],
    'de' => ['native_name' => 'Deutsch', 'default_locale' => 'de_DE'],
];
$GLOBALS['__mails']    = [];
$GLOBALS['__can']      = true;

function add_action(...$a) {}
function add_filter(...$a) {}
function remove_filter(...$a) {}
function remove_action(...$a) {}

function has_action($name) { return false; }
function has_filter($name) {
    if (!$GLOBALS['__wpml_on']) return false;
    return in_array($name, ['wpml_translate_single_string', 'wpml_active_languages'], true);
}
function apply_filters($name, $value, ...$rest) {
    if (!$GLOBALS['__wpml_on']) return $value;
    switch ($name) {
        case 'wpml_translate_single_string':
            $key = $rest[1] ?? null;
            return $GLOBALS['__wpml_map'][$key] ?? $value;
        case 'wpml_active_languages':
            return $GLOBALS['__wpml_languages'];
        case 'wpml_default_language':
            return $GLOBALS['__wpml_default_lang'];
        case 'wpml_current_language':
            return $GLOBALS['__wpml_current_lang'];
    }
    return $value;
}
function do_action(...$a) {}

function get_option($k, $d = false) { return $GLOBALS['__options'][$k] ?? $d; }
function update_option($k, $v) { $GLOBALS['__options'][$k] = $v; return true; }

function get_user_meta($user_id, $key, $single = false) {
    return $GLOBALS['__usermeta'][$user_id][$key] ?? '';
}
function update_user_meta($user_id, $key, $value) {
    $GLOBALS['__usermeta'][$user_id][$key] = $value;
    return true;
}

function get_locale() { return $GLOBALS['__locale']; }
function switch_to_locale($locale) { $GLOBALS['__locale'] = $locale; return true; }
function restore_previous_locale() { $GLOBALS['__locale'] = 'en_US'; return true; }

// The plugin text domain: after load_textdomain($d, '…-de_DE.mo') translations come from THAT locale, whatever
// the WordPress locale is; load_plugin_textdomain() (WordPress's own choice) follows the WP locale again.
$GLOBALS['__td_locale'] = null;
function unload_textdomain($d) { $GLOBALS['__td_locale'] = null; return true; }
function load_textdomain($d, $mofile) { $GLOBALS['__td_locale'] = preg_match('/-([a-z]{2}_[A-Z]{2})\.mo$/', $mofile, $m) ? $m[1] : 'en_US'; return true; }
function plugin_dir_path($f) { return rtrim(dirname($f), '/') . '/'; }
function __($text, $domain = null) {
    $locale = $GLOBALS['__td_locale'] ?? $GLOBALS['__locale'];
    return $GLOBALS['__gettext'][$locale][$text] ?? $text;
}
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
function esc_html__($t, $d = null) { return __($t, $d); }
function esc_html_e($t, $d = null) { echo esc_html__($t, $d); }
function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
function esc_url($t) { return $t; }
function esc_js($t) { return addslashes((string) $t); }
function wp_kses_post($t) { return $t; }
function sanitize_text_field($t) { return trim((string) $t); }
function sanitize_key($t) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $t)); }
function sanitize_email($t) { return $t; }
function wp_unslash($t) { return $t; }
function wp_strip_all_tags($t) { return trim(strip_tags((string) $t)); }
function is_email($t) { return (bool) filter_var($t, FILTER_VALIDATE_EMAIL); }
function wp_date($format, $ts) { return date($format, $ts); }
function wp_timezone() { return new DateTimeZone('UTC'); }
function current_time($type) { return date('Y-m-d H:i:s'); }
function plugin_basename($f) { return $f; }
function load_plugin_textdomain(...$a) { $GLOBALS['__td_locale'] = null; return true; }
if (!defined('BW_CREDITS_BOOKING_FILE')) define('BW_CREDITS_BOOKING_FILE', __DIR__ . '/../bw-credits-booking.php');

function current_user_can($cap) { return !empty($GLOBALS['__can']); }
function is_user_logged_in() { return !empty($GLOBALS['__logged_in']); }
function get_current_user_id() { return $GLOBALS['__current_user_id'] ?? 0; }
function wp_die($msg) { throw new Exception('wp_die: ' . $msg); }
function check_admin_referer($action) {
    if (empty($GLOBALS['__valid_nonce'])) throw new Exception('check_admin_referer failed for ' . $action);
    return true;
}
function wp_nonce_field(...$a) {}
function wp_nonce_url($url, $action) { return $url . '&_wpnonce=' . md5($action); }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
// Real add_query_arg() supports both add_query_arg($array, $url) and
// add_query_arg($key, $value, $url) — email-language.php uses the
// latter, the rest of the plugin uses the former.
function add_query_arg(...$args) {
    if (count($args) === 2) {
        [$params, $url] = $args;
    } else {
        [$key, $value, $url] = $args;
        $params = [$key => $value];
    }
    $pairs = [];
    foreach ($params as $k => $v) $pairs[] = "{$k}={$v}";
    return $url . (str_contains($url, '?') ? '&' : '?') . implode('&', $pairs);
}
function wp_safe_redirect($url) { $GLOBALS['__redirect'] = $url; throw new Exception('redirected'); }
function selected($a, $b) { if ((string) $a === (string) $b) echo ' selected="selected"'; }
function wp_get_referer() { return $GLOBALS['__referer'] ?? false; }
function home_url($path = '/') { return 'https://example.test' . $path; }
// wc_add_notice() intentionally NOT stubbed: it's unavailable on
// admin-post.php in the real environment (is_admin() is true there, so
// WooCommerce skips its frontend-only includes) — this is the exact bug
// reported from production. If email-language.php's admin-post handler
// ever calls it again, this test must fail with the same fatal error.

// Orders of a customer (newest first): $GLOBALS['__orders'][user_id] = [['wpml_language' => 'de'], …]
class Test_Order {
    public function __construct(private int $user, private array $meta) {}
    public function get_user_id() { return $this->user; }
    public function get_meta($k) { return $this->meta[$k] ?? ''; }
}
function wc_get_orders($args) {
    $uid = (int) ($args['customer_id'] ?? 0);
    return array_map(fn($m) => new Test_Order($uid, $m), array_slice($GLOBALS['__orders'][$uid] ?? [], 0, (int) ($args['limit'] ?? 10)));
}

// WordPress's MO reader: $GLOBALS['__mo'][locale][msgid] = msgstr (the compiled file of that locale)
class MO {
    private $locale = '';
    public function import_from_file($file) {
        $this->locale = preg_match('/-([a-z]{2}_[A-Z]{2})\.mo$/', $file, $m) ? $m[1] : '';
        return isset($GLOBALS['__mo'][$this->locale]);
    }
    public function translate($text) { return $GLOBALS['__mo'][$this->locale][$text] ?? $text; }
}

function get_userdata($id) {
    return $id > 0 ? (object) ['display_name' => 'Jane Doe', 'user_email' => 'jane@example.test'] : false;
}
// Taxonomies of a session ({course_type} / {course_level}): $GLOBALS['__terms'][slot_id][taxonomy] = [names…]
function taxonomy_exists($t) { return in_array($t, $GLOBALS['__taxonomies'] ?? ['course_type', 'course_level'], true); }
function get_the_terms($id, $tax) {
    $names = $GLOBALS['__terms'][$id][$tax] ?? [];
    return $names ? array_map(fn($n) => (object) ['name' => $n], $names) : false;
}
// Overridable per-test via $GLOBALS['__slot_title'] — defaults to a plain
// title so existing assertions below are unaffected; the title-escaping
// regression test further down sets this to an already-HTML-escaped
// value, matching what WordPress actually stores for a term name
// containing "&" (e.g. post_title saved as "Ground &amp; Connect").
function get_the_title($id) { return $GLOBALS['__slot_title'] ?? 'Hatha Yoga'; }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['__postmeta'][$id][$key] ?? ''; }
function get_permalink($id) { return 'https://example.test/session/' . $id; }

function wp_next_scheduled($hook) { return time() + 3600; }
function wp_schedule_event(...$a) {}
function wp_unschedule_event(...$a) {}
function get_edit_post_link($id, $ctx = 'raw') { return 'https://example.test/edit/' . $id; }

function wp_mail($to, $subject, $message) {
    $GLOBALS['__mails'][] = compact('to', 'subject', 'message');
    return true;
}

date_default_timezone_set('UTC');
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');

// Minimal $wpdb: get_results() hands back $GLOBALS['__db_rows'] and records
// the SQL; update() records which booking ids were marked.
class Test_WPDB {
    public $prefix   = 'wp_';
    public $postmeta = 'wp_postmeta';
    public $queries  = [];
    public $updates  = [];
    public function prepare($sql, ...$args) { return $sql; }
    public function get_results($sql, $output = null) { $this->queries[] = $sql; return $GLOBALS['__db_rows'] ?? []; }
    public function update($table, $data, $where, ...$f) { $this->updates[] = $where['id']; return 1; }
    public function query($sql) { $this->queries[] = $sql; return 1; }
}
$GLOBALS['wpdb'] = new Test_WPDB();

class BW_Credits_Bookings_MVP {
    const META_START_DT  = '_bw_start_dt';
    const META_CAPACITY  = 'capacity';
    const META_BOOKED_CNT = 'booked_count';
    const BOOKINGS_TABLE = 'bw_bookings';
    public static function get_available_credits($user_id) { return 3; }
    public static function my_account_url() { return 'https://example.test/my-account/'; }
    public static function cancel_note_text(int $slot_id, bool $booked = true): string { return $GLOBALS['__cancel_note'] ?? ''; }
}
class BW_Admin_Pages { const PAGE_BOOKINGS = 'bw-credits-bookings'; }
class BW_Settings {
    const CAPABILITY = 'manage_options';
    public static function get_default_capacity() { return 10; }
    public static function get_reminder_hours() { return 24; }
    const MENU_SLUG = 'bw-credits';
}

// time-zone.php / calendar-file.php
function wp_timezone_string() { return 'Europe/Madrid'; }
function timezone_identifiers_list_stub() {}
function bw_text(string $key, array $vars = []): string {
    $d = [
        'time.zone_label'          => '{city} time',
        'time.local'               => '{time} in your time zone ({zone})',
        'booking.cancel_until'     => 'You can cancel until {deadline}; your credit is refunded.',
        'booking.cancel_closed'    => 'Cancellation is no longer possible (less than {hours} hours before the start).',
    ];
    $text = $d[$key] ?? $key;
    foreach ($vars as $k => $v) $text = str_replace('{' . $k . '}', (string) $v, $text);
    return $text;
}
function register_rest_route(...$a) {}
function delete_user_meta($u, $k) { unset($GLOBALS['__usermeta'][$u][$k]); }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
function sanitize_file_name($n) { return preg_replace('/[^A-Za-z0-9._-]+/', '-', $n); }

require __DIR__ . '/../includes/time-zone.php';
require __DIR__ . '/../includes/calendar-file.php';
require __DIR__ . '/../includes/wpml-terms.php';
require __DIR__ . '/../includes/metaboxes.php';
require __DIR__ . '/../includes/emails.php';
require __DIR__ . '/../includes/email-language.php';

$pass = 0;
$fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; printf("PASS  %s\n", $label); }
    else       { $fail++; printf("FAIL  %s\n", $label); }
}

function reset_state() {
    $GLOBALS['__options']   = [];
    $GLOBALS['__usermeta']  = [];
    $GLOBALS['__locale']    = 'en_US';
    $GLOBALS['__td_locale'] = null;
    $GLOBALS['__orders']    = [];
    $GLOBALS['__gettext']   = [];
    $GLOBALS['__wpml_map']  = [];
    $GLOBALS['__wpml_on']   = true;
    $GLOBALS['__wpml_default_lang'] = 'en';
    $GLOBALS['__wpml_current_lang'] = 'en';
    $GLOBALS['__mails']     = [];
    $GLOBALS['__redirect']  = null;
    $GLOBALS['__referer']   = 'https://example.test/my-account/';
    unset($GLOBALS['__slot_title']); // back to the 'Hatha Yoga' default
    $GLOBALS['__postmeta']  = [];
    $GLOBALS['__db_rows']   = [];
    $GLOBALS['wpdb']->queries = [];
    $GLOBALS['wpdb']->updates = [];
}

function access_mails(): array {
    return array_values(array_filter($GLOBALS['__mails'], fn($m) => str_starts_with($m['subject'], 'Your Class Link')));
}
function slot_starts_in(int $slot_id, string $offset) {
    $GLOBALS['__postmeta'][$slot_id]['_bw_start_dt'] = (new DateTime('now'))->modify($offset)->format('Y-m-d H:i:s');
}

/* ---------------------------------------------------------------
 * 1. BW_Email_Language::get_user_language()
 * --------------------------------------------------------------- */
reset_state();
check('get_user_language(): no saved meta -> falls back to current WPML language', BW_Email_Language::get_user_language(1) === 'en');

update_user_meta(1, '_bw_email_language', 'de');
check('get_user_language(): returns saved valid preference', BW_Email_Language::get_user_language(1) === 'de');

update_user_meta(1, '_bw_email_language', 'fr'); // not an active language
check('get_user_language(): invalid saved code falls back to current language', BW_Email_Language::get_user_language(1) === 'en');

$GLOBALS['__wpml_on'] = false;
check('get_user_language(): WPML inactive -> empty string', BW_Email_Language::get_user_language(1) === '');
$GLOBALS['__wpml_on'] = true;

/* ---------------------------------------------------------------
 * 2. send(): customer types use the customer's saved preference
 * --------------------------------------------------------------- */
reset_state();
$defaults = BW_Emails::defaults();
$GLOBALS['__gettext']['de_DE'][$defaults['booking']['subject']] = 'Buchungsbestätigung: {course_title}';
$GLOBALS['__gettext']['de_DE'][$defaults['booking']['body']]    = "Hallo {customer_name},\n\ndeine Buchung ist bestätigt.";

update_user_meta(1, '_bw_email_language', 'de');
$GLOBALS['__mails'] = [];
BW_Emails::send('booking', 1, 42, 'jane@example.test');
$sent = $GLOBALS['__mails'][0] ?? null;
check('send(): customer with saved "de" preference gets the German gettext default', $sent && $sent['subject'] === 'Buchungsbestätigung: Hatha Yoga');
check('send(): locale is restored to en_US after send()', get_locale() === 'en_US');

update_user_meta(1, '_bw_email_language', 'en');
$GLOBALS['__mails'] = [];
BW_Emails::send('booking', 1, 42, 'jane@example.test');
$sent = $GLOBALS['__mails'][0] ?? null;
check('send(): customer with "en" preference gets the English default (no locale switch needed)', $sent && $sent['subject'] === 'See You on the Mat Soon! 🧘‍♀️');

/* ---------------------------------------------------------------
 * 3. send(): admin_booking always uses the WPML default language,
 *    ignoring the (customer's) user_id passed to it
 * --------------------------------------------------------------- */
reset_state();
$defaults = BW_Emails::defaults();
$GLOBALS['__gettext']['de_DE'][$defaults['admin_booking']['subject']] = 'Neue Buchung: {course_title}';
$GLOBALS['__wpml_default_lang'] = 'de'; // site default is German
update_user_meta(1, '_bw_email_language', 'en'); // but this customer prefers English

$GLOBALS['__mails'] = [];
BW_Emails::send('admin_booking', 1, 42, 'studio@example.test');
$sent = $GLOBALS['__mails'][0] ?? null;
check('send(): admin copy uses the site default language, not the customer preference', $sent && $sent['subject'] === 'Neue Buchung: Hatha Yoga');

/* ---------------------------------------------------------------
 * 4. send(): saved override still goes through WPML translate(),
 *    independent of the gettext/locale-switch step
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__options']['bw_email_booking_subject'] = 'Custom subject: {course_title}';
$GLOBALS['__options']['bw_email_booking_body']    = 'Custom body';
update_user_meta(1, '_bw_email_language', 'de');
$GLOBALS['__wpml_map'] = ['subject_booking' => 'Übersetzter Betreff: {course_title}'];

$GLOBALS['__mails'] = [];
BW_Emails::send('booking', 1, 42, 'jane@example.test');
$sent = $GLOBALS['__mails'][0] ?? null;
check('send(): saved override is translated via WPML using the customer language as target', $sent && $sent['subject'] === 'Übersetzter Betreff: Hatha Yoga');

/* ---------------------------------------------------------------
 * 4b. The plugin text domain is loaded for the customer's locale,
 *     not left as WordPress had it for this request (a logged-in
 *     customer's own locale, set by WPML) — and the placeholders are
 *     resolved in that same language (no half-English/half-German mail).
 * --------------------------------------------------------------- */
reset_state();
$defaults = BW_Emails::defaults();
$GLOBALS['__gettext']['de_DE'][$defaults['booking']['subject']] = 'Buchungsbestätigung: {course_title}';
$GLOBALS['__gettext']['de_DE']['One day before your class']     = 'Einen Tag vor deiner Klasse';
update_user_meta(1, '_bw_email_language', 'en');
$GLOBALS['__locale']    = 'en_US'; // get_locale() says English ...
$GLOBALS['__td_locale'] = 'de_DE'; // ... but the German .mo is what is loaded (logged-in customer's own locale)
$GLOBALS['__mails'] = [];
BW_Emails::send('booking', 1, 42, 'jane@example.test');
$sent = $GLOBALS['__mails'][0] ?? null;
check('send(): customer preferring English gets English even if WordPress loaded German for the request', $sent && str_contains($sent['subject'], 'See You on the Mat Soon!'));
check('send(): the request\'s own translations are back after send()', ($GLOBALS['__td_locale'] ?? null) === null);

reset_state();
$GLOBALS['__gettext']['de_DE'][$defaults['booking']['subject']] = 'Buchungsbestätigung: {course_title}';
update_user_meta(1, '_bw_email_language', 'de');
$GLOBALS['__locale']    = 'de_DE'; // get_locale() says German ...
$GLOBALS['__td_locale'] = 'en_US'; // ... but the English .mo is what is loaded
$GLOBALS['__mails'] = [];
BW_Emails::send('booking', 1, 42, 'jane@example.test');
$sent = $GLOBALS['__mails'][0] ?? null;
check('send(): customer preferring German gets German even if WordPress loaded English for the request', $sent && $sent['subject'] === 'Buchungsbestätigung: Hatha Yoga');

/* ---------------------------------------------------------------
 * 4d. A saved text that only repeats the English default (saved by
 *     the Emails page / "Reset", reformatted by the editor) is
 *     untouched: it is translated through the .mo file again, a real
 *     customisation stays as it is.
 * --------------------------------------------------------------- */
reset_state();
$defaults = BW_Emails::defaults();
$GLOBALS['__gettext']['de_DE'][$defaults['booking']['subject']] = 'Wir sehen uns bald auf der Matte!';
$GLOBALS['__gettext']['de_DE'][$defaults['booking']['body']]    = 'Hallo {first_name}, deine Klasse wurde gebucht.';
update_user_meta(1, '_bw_email_language', 'de');
$GLOBALS['__options']['bw_email_booking_subject'] = $defaults['booking']['subject'];
// as the WYSIWYG editor stores it: paragraphs, entities, non-breaking spaces
$GLOBALS['__options']['bw_email_booking_body'] = '<p>' . str_replace(["\n\n", "\n"], ['</p><p>', '<br />'], str_replace(['’', ' '], ['&rsquo;', ' &nbsp;'], $defaults['booking']['body'])) . '</p>';
$GLOBALS['__mails'] = [];
BW_Emails::send('booking', 1, 42, 'jane@example.test');
$sent = $GLOBALS['__mails'][0] ?? null;
check('send(): subject saved as the English default is still translated (German .mo)', $sent && $sent['subject'] === 'Wir sehen uns bald auf der Matte!');
check('send(): body saved as the reformatted English default is still translated (German .mo)', $sent && str_contains($sent['message'], 'deine Klasse wurde gebucht'));

reset_state();
$GLOBALS['__gettext']['de_DE'][$defaults['booking']['subject']] = 'Wir sehen uns bald auf der Matte!';
update_user_meta(1, '_bw_email_language', 'de');
$GLOBALS['__options']['bw_email_booking_subject'] = 'Dein Termin: {course_title}'; // a real customisation
$GLOBALS['__mails'] = [];
BW_Emails::send('booking', 1, 42, 'jane@example.test');
$sent = $GLOBALS['__mails'][0] ?? null;
check('send(): a customised subject is kept as saved', $sent && $sent['subject'] === 'Dein Termin: Hatha Yoga');

/* ---------------------------------------------------------------
 * 4e. The default texts are read straight from the customer's .mo, not
 *     through whatever gettext translations the request has loaded
 *     (cron run, ajax booking: no page language, no browser).
 * --------------------------------------------------------------- */
reset_state();
$defaults = BW_Emails::defaults();
$GLOBALS['__mo'] = ['de_DE' => [$defaults['reminder']['subject'] => 'Erinnerung: {course_title}', $defaults['reminder']['body'] => 'Hallo {customer_name}, dein Kurs steht an.']];
$cache = new ReflectionProperty(BW_Emails::class, 'mo_cache'); // earlier cases cached "no file" for de_DE
$cache->setAccessible(true);
$cache->setValue(null, []);
update_user_meta(1, '_bw_email_language', 'de');
$GLOBALS['__locale']    = 'en_US';
$GLOBALS['__td_locale'] = 'en_US'; // the request has English loaded, gettext knows nothing German
$GLOBALS['__mails'] = [];
BW_Emails::send('reminder', 1, 42, 'jane@example.test');
$sent = $GLOBALS['__mails'][0] ?? null;
check('send(): default text comes from the customer\'s .mo, independent of the request\'s translations', $sent && $sent['subject'] === 'Erinnerung: Hatha Yoga' && str_contains($sent['message'], 'dein Kurs steht an'));

update_user_meta(1, '_bw_email_language', 'en');
$GLOBALS['__mails'] = [];
BW_Emails::send('reminder', 1, 42, 'jane@example.test');
$sent = $GLOBALS['__mails'][0] ?? null;
check('send(): English customer gets the English default (no .mo for en_US needed)', $sent && str_contains($sent['subject'], 'Reminder: Hatha Yoga'));
unset($GLOBALS['__mo']);

/* ---------------------------------------------------------------
 * 4c. Language from the order: no preference saved -> language of the
 *     latest order; after checkout the order's language is saved
 *     (account created in this checkout), a chosen language is kept.
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__orders'][9] = [['wpml_language' => 'de']];
check('get_user_language(): no preference -> language of the latest order', BW_Email_Language::get_user_language(9) === 'de');
$GLOBALS['__orders'][9] = [['wpml_language' => 'xx']];
check('get_user_language(): unknown order language -> current language', BW_Email_Language::get_user_language(9) === 'en');

reset_state();
$order = new Test_Order(5, ['wpml_language' => 'de']);
BW_Email_Language::save_language_from_order(1, [], $order);
check('save_language_from_order(): no preference yet -> order language saved', get_user_meta(5, '_bw_email_language', true) === 'de');

reset_state();
update_user_meta(5, '_bw_email_language', 'en');
BW_Email_Language::save_language_from_order(1, [], new Test_Order(5, ['wpml_language' => 'de']));
check('save_language_from_order(): a chosen language is not overwritten', get_user_meta(5, '_bw_email_language', true) === 'en');

reset_state();
update_user_meta(6, '_bw_email_language', 'en'); // guess made at registration
BW_Email_Language::remember_created_customer(6);
BW_Email_Language::save_language_from_order(1, [], new Test_Order(6, ['wpml_language' => 'de']));
check('save_language_from_order(): account created in this checkout -> order language replaces the guess', get_user_meta(6, '_bw_email_language', true) === 'de');

BW_Email_Language::save_language_from_order(1, [], new Test_Order(0, ['wpml_language' => 'de']));
check('save_language_from_order(): guest order is ignored', get_user_meta(0, '_bw_email_language', true) === '');

/* ---------------------------------------------------------------
 * 4f. Time zone: customer mails name the zone and show the customer's
 *     own time; the studio's mails stay plain. The .ics carries the
 *     session in UTC and the Zoom details only when they may be shared.
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__postmeta'][42]['_bw_start_dt'] = '2026-10-25 08:30:00'; // Madrid: UTC+1 on that day (summer time ended)
$tz = new DateTimeZone('UTC'); // wp_timezone() stub; offsets are what matters below
$ph = BW_Emails::placeholders(1, 42, true);
check('{time} for a customer names the time zone', $ph['{time}'] === '08:30 (Madrid time)');
check('{timezone} placeholder', $ph['{timezone}'] === 'Madrid time');
check('{time} for the studio (admin mail) stays plain', BW_Emails::placeholders(1, 42, false)['{time}'] === '08:30');

update_user_meta(1, BW_Time_Zone::META_TZ, 'Europe/London');
$start = new DateTime('2026-10-25 08:30:00', wp_timezone()); // the site zone is UTC in this test environment
update_user_meta(1, BW_Time_Zone::META_TZ, 'Asia/Kolkata');
check('time_with_zone(): customer zone with another offset -> own time added', BW_Time_Zone::time_with_zone($start, 1) === '08:30 (Madrid time) · 14:00 in your time zone (Asia/Kolkata)');
update_user_meta(1, BW_Time_Zone::META_TZ, 'Europe/London'); // UTC+0 on that day
check('time_with_zone(): same offset -> no second time', BW_Time_Zone::time_with_zone($start, 1) === '08:30 (Madrid time)');
update_user_meta(1, BW_Time_Zone::META_TZ, 'Asia/Kolkata');
$late = new DateTime('2026-10-25 23:30:00', wp_timezone());
check('time_with_zone(): the date is added when it differs in the customer\'s zone', str_contains(BW_Time_Zone::time_with_zone($late, 1), '26.10.2026 05:00 in your time zone (Asia/Kolkata)'));
check('valid(): known zone yes, garbage no', BW_Time_Zone::valid('Europe/Madrid') && !BW_Time_Zone::valid('Mars/Base') && !BW_Time_Zone::valid(''));

reset_state();
check('save_user_timezone(): detection saves', BW_Time_Zone::save_user_timezone(3, 'Europe/London', false) && get_user_meta(3, BW_Time_Zone::META_TZ, true) === 'Europe/London');
BW_Time_Zone::save_user_timezone(3, 'Asia/Tokyo', true);
check('save_user_timezone(): the customer\'s own choice is saved', get_user_meta(3, BW_Time_Zone::META_TZ, true) === 'Asia/Tokyo');
check('save_user_timezone(): detection never overwrites the customer\'s choice', !BW_Time_Zone::save_user_timezone(3, 'Europe/London', false) && get_user_meta(3, BW_Time_Zone::META_TZ, true) === 'Asia/Tokyo');
check('save_user_timezone(): an invalid zone is refused', !BW_Time_Zone::save_user_timezone(4, 'not/a-zone', false));

// .ics: session in UTC, Zoom details only when they may be shared
reset_state();
$GLOBALS['__postmeta'][42]['_bw_start_dt'] = (new DateTime('+5 days 08:30', wp_timezone()))->format('Y-m-d H:i:s');
$GLOBALS['__postmeta'][42]['duration'] = '50';
$GLOBALS['__postmeta'][42]['_bw_meeting_link'] = 'https://zoom.test/own';
$GLOBALS['__postmeta'][42]['_bw_access_info']  = 'Passcode 1234';
$GLOBALS['__options']['bw_access_details_hours'] = 24;
$ref = new ReflectionMethod(BW_Emails::class, 'calendar_attachment'); $ref->setAccessible(true);
$booking = $ref->invoke(null, 1, 42, 'booking', 'de_DE');
check('.ics (booking mail, class in 5 days): calendar entry without the Zoom link', $booking && str_contains($booking['content'], 'BEGIN:VEVENT') && !str_contains($booking['content'], 'zoom.test') && !str_contains($booking['content'], '1234'));
check('.ics: times are UTC', (bool) preg_match('/DTSTART:\d{8}T\d{6}Z\r\n/', $booking['content']) && (bool) preg_match('/DTEND:\d{8}T\d{6}Z\r\n/', $booking['content']));
check('.ics: file name ends in .ics', str_ends_with($booking['filename'], '.ics'));
$access = $ref->invoke(null, 1, 42, 'access', 'de_DE');
$unfolded = str_replace("\r\n ", '', $access['content']); // long lines are folded (RFC 5545)
check('.ics (access mail): Zoom link and details included', str_contains($unfolded, 'URL:https://zoom.test/own') && str_contains($unfolded, 'Passcode 1234') && str_contains($unfolded, 'LOCATION:https://zoom.test/own'));
$GLOBALS['__options']['bw_access_details_hours'] = 0; // links go out with the booking
check('.ics (booking mail, access hours 0): Zoom link included', str_contains($ref->invoke(null, 1, 42, 'booking', 'de_DE')['content'], 'zoom.test/own'));
$GLOBALS['__postmeta'][42]['_bw_start_dt'] = '';
check('.ics: a session without a start has no calendar file', $ref->invoke(null, 1, 42, 'booking', 'de_DE') === null);

// {cancel_note} in the booking mail; an old default saved as the text counts as untouched
reset_state();
$GLOBALS['__cancel_note'] = 'You can cancel until 24.10.2026 08:30 (Madrid time); your credit is refunded.';
$defaults = BW_Emails::defaults();
check('booking default mentions the cancellation rule', str_contains($defaults['booking']['body'], '{cancel_note}'));
$GLOBALS['__gettext']['de_DE'][$defaults['booking']['body']] = "Hallo {first_name},\n{cancel_note}\nBis bald.";
update_user_meta(1, '_bw_email_language', 'de');
$GLOBALS['__options']['bw_email_booking_body'] = "<p>Greetings {first_name} 🌿,</p>"; // not the old default: a real customisation
$GLOBALS['__mails'] = [];
BW_Emails::send('booking', 1, 42, 'jane@example.test');
check('send(): a customised booking text is kept (no {cancel_note} injected)', str_contains($GLOBALS['__mails'][0]['message'] ?? '', 'Greetings') && !str_contains($GLOBALS['__mails'][0]['message'] ?? '', 'You can cancel until'));

/* ---------------------------------------------------------------
 * 5. handle_save_from_dashboard(): valid vs invalid code. Does NOT
 *    call wc_add_notice() (unstubbed on purpose, see above) — if it
 *    did, this would fatal with "Call to undefined function" exactly
 *    like the reported production bug.
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__logged_in'] = true;
$GLOBALS['__current_user_id'] = 7;
$GLOBALS['__valid_nonce'] = true;
$_POST[BW_Email_Language::META_KEY] = 'de';
try { BW_Email_Language::handle_save_from_dashboard(); } catch (Exception $e) {}
check('handle_save_from_dashboard(): valid code is saved', get_user_meta(7, '_bw_email_language', true) === 'de');
check('handle_save_from_dashboard(): redirects with an "ok:" notice, no wc_add_notice() call', str_contains($GLOBALS['__redirect'] ?? '', 'ok%3A'));
check('handle_save_from_dashboard(): redirects back to the referer', str_starts_with($GLOBALS['__redirect'] ?? '', $GLOBALS['__referer']));

$_POST[BW_Email_Language::META_KEY] = 'xx';
$GLOBALS['__redirect'] = null;
try { BW_Email_Language::handle_save_from_dashboard(); } catch (Exception $e) {}
check('handle_save_from_dashboard(): invalid code is rejected, old value kept', get_user_meta(7, '_bw_email_language', true) === 'de');
check('handle_save_from_dashboard(): redirects with an "err:" notice for an invalid code', str_contains($GLOBALS['__redirect'] ?? '', 'err%3A'));

/* reset all: every subject/body back to the default, flags untouched */
reset_state();
$GLOBALS['__valid_nonce'] = true;
$GLOBALS['__can'] = true;
$GLOBALS['__options']['bw_email_booking_body'] = 'custom';
$GLOBALS['__options']['bw_email_access_subject'] = 'custom';
$GLOBALS['__options']['bw_email_booking_enabled'] = '0';
try { BW_Emails::handle_reset_all_emails(); } catch (Exception $e) {}
check('handle_reset_all_emails(): booking body reset to default', $GLOBALS['__options']['bw_email_booking_body'] === BW_Emails::defaults()['booking']['body']);
check('handle_reset_all_emails(): access subject reset to default', $GLOBALS['__options']['bw_email_access_subject'] === BW_Emails::defaults()['access']['subject']);
check('handle_reset_all_emails(): active flag untouched', ($GLOBALS['__options']['bw_email_booking_enabled'] ?? null) === '0');
$GLOBALS['__can'] = false;
$threw = false;
try { BW_Emails::handle_reset_all_emails(); } catch (Exception $e) { $threw = str_starts_with($e->getMessage(), 'wp_die'); }
check('handle_reset_all_emails(): requires the capability', $threw);
$GLOBALS['__can'] = true;

/* admin mails: participant count + link to the participant list */
reset_state();
$GLOBALS['__postmeta'][42]['booked_count'] = 3;
$GLOBALS['__postmeta'][42]['capacity'] = 8;
$ph = BW_Emails::placeholders(1, 42);
check('placeholders(): {booked_count} / {capacity}', $ph['{booked_count}'] === '3' && $ph['{capacity}'] === '8');
check('placeholders(): {admin_link} points to the participant list of the session', str_contains($ph['{admin_link}'], 'page=bw-credits-bookings') && str_ends_with($ph['{admin_link}'], 'slot_id=42'));
unset($GLOBALS['__postmeta'][42]['capacity']);
check('placeholders(): {capacity} falls back to the default capacity', BW_Emails::placeholders(1, 42)['{capacity}'] === '10');
check('admin mails are on by default', BW_Emails::is_enabled('admin_booking') && BW_Emails::is_enabled('admin_cancellation'));

$GLOBALS['__options']['admin_email'] = 'admin@example.test';
$GLOBALS['__mails'] = [];
BW_Emails::on_booking_cancelled(900, 1, 42);
$subjects = array_column($GLOBALS['__mails'], 'subject');
check('on_booking_cancelled(): customer cancellation mail and admin notification both go out', count($GLOBALS['__mails']) === 2);
check('on_booking_cancelled(): admin subject carries the count', in_array('Cancellation: Hatha Yoga (3/10)', $subjects, true));
$admin = array_values(array_filter($GLOBALS['__mails'], fn($m) => str_contains($m['subject'], '(3/10)')))[0] ?? null;
check('on_booking_cancelled(): admin body has the participant list link', $admin && str_contains($admin['message'], 'slot_id=42'));

/* ---------------------------------------------------------------
 * 6. save_language_on_registration(): captures the active WPML
 *    language at signup as the initial preference
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__wpml_current_lang'] = 'de';
BW_Email_Language::save_language_on_registration(99);
check('save_language_on_registration(): stores the language active at signup', get_user_meta(99, '_bw_email_language', true) === 'de');

$GLOBALS['__wpml_on'] = false;
BW_Email_Language::save_language_on_registration(100);
check('save_language_on_registration(): no-op without WPML', get_user_meta(100, '_bw_email_language', true) === '');

/* ---------------------------------------------------------------
 * 7. send(): {course_title} with an "&" in the term name is not
 *    double-escaped. WordPress stores post_title HTML-entity-escaped
 *    once (e.g. "Ground &amp; Connect" for a term named "Ground & Connect")
 *    — get_the_title() returns it exactly as stored, un-decoded.
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__slot_title'] = 'Ground &amp; Connect';
// The default texts no longer contain {course_title}; the escaping path is the same for a saved override.
$GLOBALS['__options']['bw_email_booking_subject'] = 'Booking confirmation: {course_title}';
$GLOBALS['__options']['bw_email_booking_body']    = 'Hi, you booked {course_title}.';
$GLOBALS['__mails'] = [];
BW_Emails::send('booking', 1, 42, 'jane@example.test');
$sent = $GLOBALS['__mails'][0] ?? null;

check(
    'send(): subject shows a single "&" (plain text, not escaped at all) — not "&amp;"',
    $sent && $sent['subject'] === 'Booking confirmation: Ground & Connect'
);
check(
    'send(): body shows "&amp;" exactly once (correct HTML), not "&amp;amp;"',
    $sent && str_contains($sent['message'], 'Ground &amp; Connect') && !str_contains($sent['message'], '&amp;amp;')
);

/* ---------------------------------------------------------------
 * 8. Online Access: session value wins, else the Settings default
 * --------------------------------------------------------------- */
reset_state();
check('get_meeting_link(): nothing set anywhere -> empty', BW_Metaboxes::get_meeting_link(42) === '');

$GLOBALS['__options']['bw_default_meeting_link'] = 'https://zoom.test/default';
$GLOBALS['__options']['bw_default_access_info']  = 'Default PIN 1234';
check('get_meeting_link(): falls back to the default', BW_Metaboxes::get_meeting_link(42) === 'https://zoom.test/default');
check('get_access_info(): falls back to the default', BW_Metaboxes::get_access_info(42) === 'Default PIN 1234');

$GLOBALS['__postmeta'][42]['_bw_meeting_link'] = 'https://zoom.test/own';
$GLOBALS['__postmeta'][42]['_bw_access_info']  = 'Own PIN';
check('get_meeting_link(): the session\'s own link wins', BW_Metaboxes::get_meeting_link(42) === 'https://zoom.test/own');
check('get_access_info(): the session\'s own details win', BW_Metaboxes::get_access_info(42) === 'Own PIN');

unset($GLOBALS['__postmeta'][42]['_bw_meeting_link'], $GLOBALS['__postmeta'][42]['_bw_access_info']);
$ph = BW_Emails::placeholders(1, 42);
check('placeholders(): {meeting_link}/{access_details} use the defaults', $ph['{meeting_link}'] === 'https://zoom.test/default' && $ph['{access_details}'] === 'Default PIN 1234');

$GLOBALS['__terms'][42] = ['course_type' => ['Hatha', 'Yin'], 'course_level' => ['All levels']];
$GLOBALS['__options']['woocommerce_email_from_address'] = 'hello@example.test';
$ph = BW_Emails::placeholders(1, 42);
check('placeholders(): {course_type} lists the session\'s terms', $ph['{course_type}'] === 'Hatha, Yin');
check('placeholders(): {course_level} lists the session\'s terms', $ph['{course_level}'] === 'All levels');
check('placeholders(): {first_name} falls back to the display name', $ph['{first_name}'] === 'Jane Doe');
check('placeholders(): {contact_email} uses the WooCommerce from address', $ph['{contact_email}'] === 'hello@example.test');
$GLOBALS['__terms'] = [];
$ph = BW_Emails::placeholders(1, 42);
check('placeholders(): session without terms -> empty {course_type}', $ph['{course_type}'] === '');
unset($GLOBALS['__options']['woocommerce_email_from_address']);
foreach ([0 => 'Before your class', 24 => 'One day before your class', 48 => 'Two days before your class', 72 => '3 days before your class', 1 => 'One hour before your class', 5 => '5 hours before your class'] as $h => $expected) {
    $GLOBALS['__options']['bw_access_details_hours'] = $h;
    check("placeholders(): {access_timing} for $h h", BW_Emails::placeholders(1, 42)['{access_timing}'] === $expected);
}
unset($GLOBALS['__options']['bw_access_details_hours']);

/* ---------------------------------------------------------------
 * 9. on_booking_created(): hours = 0 keeps today's immediate send
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__options']['bw_default_meeting_link'] = 'https://zoom.test/default';
slot_starts_in(42, '+5 days');
BW_Emails::on_booking_created(500, 1, 42);
check('on_booking_created(): hours=0 + default link -> access details sent right away', count(access_mails()) === 1);
check('on_booking_created(): ... and the booking is marked as sent', $GLOBALS['wpdb']->updates === [500]);

reset_state();
slot_starts_in(42, '+5 days');
BW_Emails::on_booking_created(500, 1, 42);
check('on_booking_created(): no link anywhere -> nothing sent', count(access_mails()) === 0);

/* ---------------------------------------------------------------
 * 10. on_booking_created(): hours > 0 waits, unless already in the window
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__options']['bw_access_details_hours'] = 24;
$GLOBALS['__options']['bw_default_meeting_link'] = 'https://zoom.test/default';
slot_starts_in(42, '+5 days');
BW_Emails::on_booking_created(500, 1, 42);
check('on_booking_created(): hours=24, session in 5 days -> not sent yet', count(access_mails()) === 0 && $GLOBALS['wpdb']->updates === []);

reset_state();
$GLOBALS['__options']['bw_access_details_hours'] = 24;
$GLOBALS['__options']['bw_default_meeting_link'] = 'https://zoom.test/default';
slot_starts_in(42, '+2 hours');
BW_Emails::on_booking_created(500, 1, 42);
check('on_booking_created(): hours=24, late booking 2h before -> sent right away', count(access_mails()) === 1);

/* ---------------------------------------------------------------
 * 11. on_meeting_link_added() respects the window; manual resend doesn't
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__options']['bw_access_details_hours'] = 24;
$GLOBALS['__postmeta'][42]['_bw_meeting_link'] = 'https://zoom.test/own';
slot_starts_in(42, '+5 days');
$GLOBALS['__db_rows'] = [['id' => 501, 'user_id' => 1]];
BW_Emails::on_meeting_link_added(42);
check('on_meeting_link_added(): hours=24, session in 5 days -> waits for the cron', count(access_mails()) === 0);

BW_Emails::send_access_for_slot(42);
check('send_access_for_slot() (used by "Resend"): always immediate, regardless of hours', count(access_mails()) === 1 && $GLOBALS['wpdb']->updates === [501]);

reset_state();
$GLOBALS['__options']['bw_access_details_hours'] = 24;
$GLOBALS['__postmeta'][42]['_bw_meeting_link'] = 'https://zoom.test/own';
slot_starts_in(42, '+3 hours');
$GLOBALS['__db_rows'] = [['id' => 501, 'user_id' => 1]];
BW_Emails::on_meeting_link_added(42);
check('on_meeting_link_added(): link entered inside the window -> sent right away', count(access_mails()) === 1);

reset_state();
$GLOBALS['__postmeta'][42]['_bw_meeting_link'] = 'https://zoom.test/own';
slot_starts_in(42, '+5 days');
$GLOBALS['__db_rows'] = [['id' => 501, 'user_id' => 1]];
BW_Emails::on_meeting_link_added(42);
check('on_meeting_link_added(): hours=0 -> sent right away (unchanged behavior)', count(access_mails()) === 1);

/* ---------------------------------------------------------------
 * 12. run_access_details() cron
 * --------------------------------------------------------------- */
reset_state();
$GLOBALS['__db_rows'] = [['id' => 600, 'user_id' => 1, 'slot_id' => 42]];
BW_Emails::run_access_details();
check('run_access_details(): hours=0 -> no-op (no query, nothing sent)', $GLOBALS['wpdb']->queries === [] && count(access_mails()) === 0);

reset_state();
$GLOBALS['__options']['bw_access_details_hours'] = 24;
$GLOBALS['__options']['bw_default_meeting_link'] = 'https://zoom.test/default';
$GLOBALS['__db_rows'] = [['id' => 600, 'user_id' => 1, 'slot_id' => 42], ['id' => 601, 'user_id' => 1, 'slot_id' => 43]];
BW_Emails::run_access_details();
check('run_access_details(): sends + marks every due booking (default link covers both)', count(access_mails()) === 2 && $GLOBALS['wpdb']->updates === [600, 601]);
check('run_access_details(): with a default link, no own-link filter in SQL', !str_contains($GLOBALS['wpdb']->queries[0] ?? '', 'lm.meta_value'));
check('run_access_details(): only bookings not yet sent are queried', str_contains($GLOBALS['wpdb']->queries[0] ?? '', 'access_sent_at IS NULL'));

reset_state();
$GLOBALS['__options']['bw_access_details_hours'] = 24;
$GLOBALS['__postmeta'][42]['_bw_meeting_link'] = 'https://zoom.test/own';
$GLOBALS['__db_rows'] = [['id' => 600, 'user_id' => 1, 'slot_id' => 42], ['id' => 601, 'user_id' => 1, 'slot_id' => 43]];
BW_Emails::run_access_details();
check('run_access_details(): without a default, SQL requires the session\'s own link', str_contains($GLOBALS['wpdb']->queries[0] ?? '', "lm.meta_value <> ''"));
check('run_access_details(): a session without any link is skipped', $GLOBALS['wpdb']->updates === [600]);

reset_state();
$GLOBALS['__options']['bw_access_details_hours'] = 24;
$GLOBALS['__options']['bw_email_access_enabled'] = 0;
$GLOBALS['__options']['bw_default_meeting_link'] = 'https://zoom.test/default';
$GLOBALS['__db_rows'] = [['id' => 600, 'user_id' => 1, 'slot_id' => 42]];
BW_Emails::run_access_details();
check('run_access_details(): access email disabled -> no query', $GLOBALS['wpdb']->queries === []);

printf("\n%d/%d checks passed\n", $pass, $pass + $fail);
exit($fail > 0 ? 1 : 0);
