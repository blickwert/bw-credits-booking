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

function __($text, $domain = null) {
    return $GLOBALS['__gettext'][$GLOBALS['__locale']][$text] ?? $text;
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
function load_plugin_textdomain(...$a) {}
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

function get_userdata($id) {
    return $id > 0 ? (object) ['display_name' => 'Jane Doe', 'user_email' => 'jane@example.test'] : false;
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
    const BOOKINGS_TABLE = 'bw_bookings';
    public static function get_available_credits($user_id) { return 3; }
    public static function my_account_url() { return 'https://example.test/my-account/'; }
}
class BW_Settings {
    const CAPABILITY = 'manage_options';
    public static function get_reminder_hours() { return 24; }
    const MENU_SLUG = 'bw-credits';
}

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
    return array_values(array_filter($GLOBALS['__mails'], fn($m) => str_starts_with($m['subject'], 'Access details')));
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
check('send(): customer with "en" preference gets the English default (no locale switch needed)', $sent && $sent['subject'] === 'Booking confirmation: Hatha Yoga');

/* ---------------------------------------------------------------
 * 3. send(): admin_booking always uses the WPML default language,
 *    ignoring the (customer's) user_id passed to it
 * --------------------------------------------------------------- */
reset_state();
$defaults = BW_Emails::defaults();
$GLOBALS['__gettext']['de_DE'][$defaults['admin_booking']['subject']] = 'Neue Buchung: {course_title}';
$GLOBALS['__wpml_default_lang'] = 'de'; // site default is German
$GLOBALS['__options']['bw_email_admin_booking_enabled'] = 1; // off by default
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
