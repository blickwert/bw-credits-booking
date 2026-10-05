<?php
/**
 * Regression test: BW_Course_Info — [bw_credits_course_info] values and the
 * featured image taken from the course type. Loads the real
 * includes/course-info.php against a stub WordPress environment.
 */

define('ABSPATH', true);

$GLOBALS['__postmeta'] = [];
$GLOBALS['__termmeta'] = [];
$GLOBALS['__terms']    = [];   // [slot_id][taxonomy] = [term objects]
$GLOBALS['__thumb']    = [];   // slot_id => attachment id
$GLOBALS['__content']  = [];
$GLOBALS['__options']  = [];

function add_action(...$a) {}
function shortcode_atts($pairs, $atts, $sc = '') {
    $atts = (array) $atts; $out = [];
    foreach ($pairs as $k => $d) $out[$k] = array_key_exists($k, $atts) ? $atts[$k] : $d;
    return $out;
}
function sanitize_key($t) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $t)); }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
function __($t, $d = null) { return $t; }
function wp_kses_post($t) { return $t; }
function wpautop($t) { return '<p>' . str_replace(["\r\n", "\n"], '<br />', trim($t)) . '</p>'; }
function wp_date($f, $ts) { return gmdate($f, $ts); }
function apply_filters($n, $v, ...$a) { return $v; }
function is_wp_error($x) { return false; }
function taxonomy_exists($t) { return true; }
function get_the_terms($id, $tax) { return $GLOBALS['__terms'][$id][$tax] ?? false; }
function get_term_meta($id, $key, $single = false) { return $GLOBALS['__termmeta'][$id][$key] ?? ''; }
function get_term($id, $tax) { return (object) ['term_id' => $id]; }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['__postmeta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $v) { $GLOBALS['__postmeta'][$id][$key] = $v; }
function get_post_field($f, $id) { return $GLOBALS['__content'][$id] ?? ''; }
function get_post_type($id) { return 'course_slot'; }
function get_post_thumbnail_id($id) { return $GLOBALS['__thumb'][$id] ?? 0; }
function set_post_thumbnail($id, $img) { $GLOBALS['__thumb'][$id] = $img; return true; }
function get_option($k, $d = false) { return $GLOBALS['__options'][$k] ?? $d; }
function update_option($k, $v) { $GLOBALS['__options'][$k] = $v; return true; }
function get_posts($args) { return $GLOBALS['__all_slots'] ?? []; }

class BW_Settings { public static function get_slot_post_type() { return 'course_slot'; } }
class BW_Credits_Bookings_MVP {
    public static function ensure_assets() { $GLOBALS['__assets'] = true; }
    public static function resolve_course_id(int $id): int { return $id > 0 ? $id : 0; }
    public static function get_slot_start_datetime(int $id) {
        $raw = $GLOBALS['__postmeta'][$id]['start_datetime'] ?? '';
        return $raw ? new DateTime($raw, new DateTimeZone('UTC')) : null;
    }
}

require __DIR__ . '/../includes/course-info.php';

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; printf("PASS  %s\n", $label); } else { $fail++; printf("FAIL  %s\n", $label); }
}
function sc($field, $id = 7) { return BW_Course_Info::render(['course_id' => $id, 'field' => $field]); }

$GLOBALS['__terms'][7] = [
    'course_type'  => [(object) ['term_id' => 31, 'name' => 'Hatha Yoga - Ground &amp; Connect', 'description' => "A calm practice.\r\nFor everyone."]],
    'course_level' => [(object) ['term_id' => 30, 'name' => 'Soulful Beginning', 'description' => '']],
    'course_lang'  => [(object) ['term_id' => 45, 'name' => 'English', 'description' => '']],
];
$GLOBALS['__postmeta'][7] = ['start_datetime' => '2026-09-12 08:30:00', 'duration' => '50'];

check('type: name, entity decoded and escaped once', sc('type') === 'Hatha Yoga - Ground &amp; Connect');
check('type_description: line breaks become paragraph markup', sc('type_description') === '<p>A calm practice.<br />For everyone.</p>');
check('level: name', sc('level') === 'Soulful Beginning');
check('level_description: empty description prints nothing', sc('level_description') === '');
check('language: name', sc('language') === 'English');
check('start / date / time', sc('start') === '12.09.2026 08:30' && sc('date') === '12.09.2026' && sc('time') === '08:30');
check('duration: minutes', sc('duration') === '50 min.');
check('duration: missing value prints nothing', sc('duration', 8) === '');
$cal = sc('calendar');
check('calendar: leaf markup with weekday, day, month and time', str_contains($cal, 'bw-course-slot-date__dow">Sat<') && str_contains($cal, '__day">12<') && str_contains($cal, '__month">Sep<') && str_contains($cal, '__time">08:30<'));
check('calendar: enqueues the plugin styles', !empty($GLOBALS['__assets']));
check('calendar: no start prints a dash placeholder', str_contains(sc('calendar', 8), '—'));
check('detail: empty content prints nothing', sc('detail') === '');
$GLOBALS['__content'][7] = 'More about the class';
check('detail: content is printed', sc('detail') === '<p>More about the class</p>');
check('unknown field prints nothing', sc('nope') === '');
check('no session (course_id 0 outside a session) prints nothing', BW_Course_Info::render(['field' => 'type']) === '');

/* featured image from the type */
$GLOBALS['__termmeta'][31]['img'] = 230;
BW_Course_Info::sync_slot(7);
check('sync_slot(): empty featured image gets the type image', ($GLOBALS['__thumb'][7] ?? 0) === 230 && $GLOBALS['__postmeta'][7]['_bw_thumb_auto'] === 230);

$GLOBALS['__termmeta'][31]['img'] = 231;
BW_Course_Info::sync_slot(7);
check('sync_slot(): follows a changed type image while it is still the automatic one', $GLOBALS['__thumb'][7] === 231);

$GLOBALS['__thumb'][7] = 999; // chosen by hand
$GLOBALS['__termmeta'][31]['img'] = 232;
BW_Course_Info::sync_slot(7);
check('sync_slot(): a featured image chosen by hand is kept', $GLOBALS['__thumb'][7] === 999);

$GLOBALS['__terms'][9] = ['course_type' => [(object) ['term_id' => 55, 'name' => 'No image', 'description' => '']]];
BW_Course_Info::sync_slot(9);
check('sync_slot(): type without image sets nothing', !isset($GLOBALS['__thumb'][9]));

$GLOBALS['__all_slots'] = [7, 9];
$GLOBALS['__thumb'] = [];
BW_Course_Info::backfill_once();
check('backfill_once(): fills existing sessions and marks itself done', ($GLOBALS['__thumb'][7] ?? 0) === 232 && !empty($GLOBALS['__options']['bw_thumb_backfill_done']));
$GLOBALS['__thumb'] = [];
BW_Course_Info::backfill_once();
check('backfill_once(): runs only once', empty($GLOBALS['__thumb']));

BW_Course_Info::on_acf_save('term_31');
check('on_acf_save(term_…): updates the sessions of that type', ($GLOBALS['__thumb'][7] ?? 0) === 232);

printf("\n%d/%d checks passed\n", $pass, $pass + $fail);
exit($fail ? 1 : 0);
