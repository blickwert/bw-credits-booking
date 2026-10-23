<?php
/**
 * Regression test: BW_Lifecycle — a removed session cancels and refunds its upcoming bookings (customers are
 * informed through BW_Emails, the studio gets one mail), a deleted account cancels its upcoming bookings and keeps
 * its credits, bookings of sessions that already took place stay untouched. Loads the real includes/lifecycle.php
 * against stubs of WordPress and the plugin core.
 */

define('ABSPATH', true);
define('ARRAY_A', 'ARRAY_A');

$GLOBALS['__mails']      = [];   // [to, subject, message]
$GLOBALS['__cancelled']  = [];   // booking ids, with the mode that was active
$GLOBALS['__bookings']   = [];   // slot id => booking rows
$GLOBALS['__starts']     = [];   // slot id => DateTime|null
$GLOBALS['__user_rows']  = [];   // rows returned for the "upcoming bookings of a user" query
$GLOBALS['__credits']    = 0;
$GLOBALS['__posts']      = [];   // post id => post_type

function add_action(...$a) {}
function wp_timezone() { return new DateTimeZone('UTC'); }
function __($t, $d = null) { return $t; }
function esc_html__($t, $d = null) { return htmlspecialchars($t); }
function esc_html($t) { return htmlspecialchars($t); }
function is_wp_error($v) { return $v instanceof WP_Error; }
function is_email($e) { return (bool) preg_match('/^[^@\s]+@[^@\s]+\.[a-z]+$/i', $e); }
function wp_mail($to, $subject, $message) { $GLOBALS['__mails'][] = [$to, $subject, $message]; return true; }
function get_post($id) { return isset($GLOBALS['__posts'][$id]) ? (object) ['post_type' => $GLOBALS['__posts'][$id]] : null; }
function get_the_title($id) { return 'Hatha Yoga'; }
function get_userdata($id) { return $id === 17 ? (object) ['display_name' => 'Anna', 'user_email' => 'anna@example.test'] : false; }
class WP_Error {}
class BW_Settings { static function get_slot_post_type() { return 'course_slot'; } }
class BW_Emails { static function admin_recipient() { return 'studio@example.test'; } }
class Fake_Wpdb {
    public $prefix = 'wp_';
    function prepare($q, ...$a) { return $q; }
    function get_results($q, $out = null) { return $GLOBALS['__user_rows']; }
}
$GLOBALS['wpdb'] = new Fake_Wpdb();
class BW_Credits_Bookings_MVP {
    const BOOKINGS_TABLE = 'bw_bwallet_bookings';
    static function get_slot_bookings($slot) { return $GLOBALS['__bookings'][$slot] ?? []; }
    static function get_slot_start_datetime($slot) { return $GLOBALS['__starts'][$slot] ?? null; }
    static function booking_user_label($b) { return trim((string) ($b['display_name'] ?? '')) ?: 'Deleted user #' . (int) $b['user_id']; }
    static function get_available_credits($user) { return $GLOBALS['__credits']; }
    static function admin_cancel_booking($id) { $GLOBALS['__cancelled'][] = [$id, BW_Lifecycle::$mode]; return true; }
}

require __DIR__ . '/../includes/lifecycle.php';

$pass = 0; $fail = 0;
function check($name, $ok) { global $pass, $fail; if ($ok) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name\n"; } }
function reset_state() { $GLOBALS['__mails'] = []; $GLOBALS['__cancelled'] = []; $GLOBALS['__bookings'] = []; $GLOBALS['__starts'] = []; $GLOBALS['__user_rows'] = []; $GLOBALS['__credits'] = 0; }

$now    = new DateTime('now', new DateTimeZone('UTC'));
$future = (clone $now)->modify('+2 days');
$past   = (clone $now)->modify('-2 days');

check('is_cancellable: booked and upcoming', BW_Lifecycle::is_cancellable(['is_active' => 1, 'status' => 'booked'], $future, $now));
check('is_cancellable: no start date counts as upcoming', BW_Lifecycle::is_cancellable(['is_active' => 1, 'status' => 'booked'], null, $now));
check('is_cancellable: a class that took place is left alone', !BW_Lifecycle::is_cancellable(['is_active' => 1, 'status' => 'booked'], $past, $now));
check('is_cancellable: cancelled / no-show bookings are left alone', !BW_Lifecycle::is_cancellable(['is_active' => null, 'status' => 'cancelled'], $future, $now) && !BW_Lifecycle::is_cancellable(['is_active' => 1, 'status' => 'no_show'], $future, $now));

// Session removed
reset_state();
$GLOBALS['__posts'][42] = 'course_slot'; $GLOBALS['__starts'][42] = $future;
$GLOBALS['__bookings'][42] = [
    ['id' => 1, 'user_id' => 17, 'status' => 'booked', 'is_active' => 1, 'display_name' => 'Anna', 'user_email' => 'anna@example.test'],
    ['id' => 2, 'user_id' => 18, 'status' => 'cancelled', 'is_active' => null, 'display_name' => 'Ben', 'user_email' => 'ben@example.test'],
    ['id' => 3, 'user_id' => 19, 'status' => 'booked', 'is_active' => 1, 'display_name' => '', 'user_email' => ''],
];
BW_Lifecycle::on_session_removed(42);
check('session removed: only the active bookings are cancelled, in session mode', $GLOBALS['__cancelled'] === [[1, 'session'], [3, 'session']]);
check('session removed: the mode is reset afterwards', BW_Lifecycle::$mode === '');
check('session removed: one summary mail to the studio, naming the customers', count($GLOBALS['__mails']) === 1 && $GLOBALS['__mails'][0][0] === 'studio@example.test'
    && str_contains($GLOBALS['__mails'][0][2], 'Anna (anna@example.test)') && str_contains($GLOBALS['__mails'][0][2], 'Deleted user #19'));

reset_state();
$GLOBALS['__posts'][43] = 'course_slot'; $GLOBALS['__starts'][43] = $past;
$GLOBALS['__bookings'][43] = [['id' => 5, 'user_id' => 17, 'status' => 'booked', 'is_active' => 1, 'display_name' => 'Anna', 'user_email' => 'a@example.test']];
BW_Lifecycle::on_session_removed(43);
check('session removed: a class that already took place is not touched, no mail', !$GLOBALS['__cancelled'] && !$GLOBALS['__mails']);

reset_state();
$GLOBALS['__posts'][44] = 'post';
BW_Lifecycle::on_session_removed(44);
check('other post types are ignored', !$GLOBALS['__cancelled'] && !$GLOBALS['__mails']);

// Account deleted
reset_state();
$GLOBALS['__posts'][45] = 'course_slot'; $GLOBALS['__starts'][45] = $future;
$GLOBALS['__user_rows'] = [['id' => 7, 'slot_id' => 45, 'status' => 'booked', 'is_active' => 1]];
$GLOBALS['__credits'] = 2;
BW_Lifecycle::on_user_delete(17);
check('account deleted: upcoming booking cancelled in user mode (no customer mail)', $GLOBALS['__cancelled'] === [[7, 'user']]);
check('account deleted: one summary mail with the credits left', count($GLOBALS['__mails']) === 1 && str_contains($GLOBALS['__mails'][0][2], 'Unused credits left: 2'));

reset_state();
BW_Lifecycle::on_user_delete(17);
check('account deleted: nothing to report -> no mail', !$GLOBALS['__mails']);
check('account deleted: unknown user -> nothing', (function () { BW_Lifecycle::on_user_delete(999); return !$GLOBALS['__mails']; })());

// Warning on the delete screen
reset_state();
$GLOBALS['__user_rows'] = [['id' => 7, 'slot_id' => 45, 'status' => 'booked', 'is_active' => 1]];
$GLOBALS['__starts'][45] = $future; $GLOBALS['__credits'] = 1;
ob_start(); BW_Lifecycle::delete_user_notice(null, [17]); $out = ob_get_clean();
check('delete screen: warns about bookings and credits', str_contains($out, 'Anna') && str_contains($out, '1 upcoming booking(s)') && str_contains($out, '1 unused credit(s)'));
$GLOBALS['__user_rows'] = []; $GLOBALS['__credits'] = 0;
ob_start(); BW_Lifecycle::delete_user_notice(null, [17]); $out = ob_get_clean();
check('delete screen: silent without bookings and credits', $out === '');

echo "\n$pass/" . ($pass + $fail) . " checks passed\n";
exit($fail ? 1 : 0);
