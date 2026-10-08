<?php
/**
 * Regression test: BW_Course_Info — post content generated from the session's
 * terms and meta (course-info.php). Text written by hand is kept, access data
 * never lands in the content.
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
function apply_filters($n, $v, ...$a) {
    if ($n === 'wpml_object_id' && isset($GLOBALS['__wpml_map'][$v])) return $GLOBALS['__wpml_map'][$v];
    return $v;
}
function has_filter($n) { return $n === 'wpml_object_id' && !empty($GLOBALS['__wpml_map']); }
function is_wp_error($x) { return false; }
function taxonomy_exists($t) { return true; }
function get_the_terms($id, $tax) { return $GLOBALS['__terms'][$id][$tax] ?? false; }
function get_term_meta($id, $key, $single = false) { return $GLOBALS['__termmeta'][$id][$key] ?? ''; }
function get_term($id, $tax) { return $GLOBALS['__term_objs'][$id] ?? (object) ['term_id' => $id, 'taxonomy' => $tax]; }
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

function get_post_status($id) { return $GLOBALS['__status'][$id] ?? 'publish'; }
function clean_post_cache($id) { $GLOBALS['__cache_cleaned'][] = $id; }
function wp_is_post_revision($id) { return false; }
function wp_is_post_autosave($id) { return false; }
class BW_Test_WPDB {
    public $posts = 'wp_posts';
    public function update($table, $data, $where) { $GLOBALS['__content'][$where['ID']] = $data['post_content']; $GLOBALS['__writes'] = ($GLOBALS['__writes'] ?? 0) + 1; return 1; }
}
$GLOBALS['wpdb'] = new BW_Test_WPDB();

require __DIR__ . '/../includes/wpml-terms.php';
require __DIR__ . '/../includes/course-info.php';

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; printf("PASS  %s\n", $label); } else { $fail++; printf("FAIL  %s\n", $label); }
}

$GLOBALS['__terms'][7] = [
    'course_type'  => [(object) ['taxonomy' => 'course_type', 'term_id' => 31, 'name' => 'Hatha Yoga - Ground &amp; Connect', 'description' => "A calm practice.\r\nFor everyone."]],
    'course_level' => [(object) ['taxonomy' => 'course_level', 'term_id' => 30, 'name' => 'Soulful Beginning', 'description' => 'Gentle start.']],
    'course_lang'  => [(object) ['taxonomy' => 'course_lang', 'term_id' => 45, 'name' => 'English', 'description' => '']],
];
$GLOBALS['__postmeta'][7] = [
    'start_datetime' => '2026-09-12 08:30:00', 'duration' => '50',
    '_bw_meeting_link' => 'https://zoom.example/j/123', '_bw_access_info' => 'Passcode: secret',
];

check('empty content is generated', BW_Course_Info::sync_content(7) === true);
$c = $GLOBALS['__content'][7];
check('content names type, level and language', str_contains($c, '<p>Hatha Yoga - Ground &amp; Connect – Soulful Beginning (English)</p>'));
check('content has date and duration', str_contains($c, '<p>12.09.2026 08:30 · 50 min.</p>'));
check('content has the type and level descriptions', str_contains($c, 'A calm practice.') && str_contains($c, 'Gentle start.'));
check('access data never lands in the content', !str_contains($c, 'zoom') && !str_contains($c, 'secret') && !str_contains($c, 'Passcode'));
check('hash of the generated content is stored', $GLOBALS['__postmeta'][7]['_bw_content_auto'] === md5($c));
check('page cache of the session is cleared', in_array(7, $GLOBALS['__cache_cleaned'], true));

$GLOBALS['__writes'] = 0;
check('unchanged data: no second write', BW_Course_Info::sync_content(7) === false && $GLOBALS['__writes'] === 0);

$GLOBALS['__postmeta'][7]['duration'] = '60';
check('changed data: generated content follows', BW_Course_Info::sync_content(7) === true && str_contains($GLOBALS['__content'][7], '60 min.'));

check('detail: generated content is not printed as detail text', BW_Course_Info::render(['course_id' => 7, 'field' => 'detail']) === '');

$GLOBALS['__content'][7] = 'Written by the teacher.';
$before = $GLOBALS['__content'][7];
check('text written by hand is never replaced', BW_Course_Info::sync_content(7) === false && $GLOBALS['__content'][7] === $before);
check('detail: text written by hand is printed', BW_Course_Info::render(['course_id' => 7, 'field' => 'detail']) === '<p>Written by the teacher.</p>');

$GLOBALS['__terms'][8] = [];
check('no data: nothing is written', BW_Course_Info::sync_content(8) === false && !isset($GLOBALS['__content'][8]));

$GLOBALS['__terms'][9] = ['course_type' => [(object) ['term_id' => 55, 'name' => 'Type only', 'description' => '']]];
check('only a type: short content', BW_Course_Info::sync_content(9) === true && $GLOBALS['__content'][9] === '<p>Type only</p>');

$GLOBALS['__status'][10] = 'auto-draft'; $GLOBALS['__terms'][10] = $GLOBALS['__terms'][9];
check('auto-drafts are skipped', BW_Course_Info::sync_content(10) === false);

/* term edited → sessions of that term follow */
$GLOBALS['__content'] = []; $GLOBALS['__postmeta'][7]['_bw_content_auto'] = ''; $GLOBALS['__all_slots'] = [7, 9];
BW_Course_Info::on_term_edited(31, 0, 'course_type');
check('on_term_edited(): sessions of the term get their content', !empty($GLOBALS['__content'][7]));
$GLOBALS['__content'] = [];
BW_Course_Info::on_term_edited(31, 0, 'category');
check('on_term_edited(): other taxonomies are ignored', empty($GLOBALS['__content']));

$GLOBALS['__content'] = []; $GLOBALS['__postmeta'][7]['_bw_content_auto'] = '';
BW_Course_Info::backfill_content_once();
check('backfill_content_once(): fills existing sessions and marks itself done', !empty($GLOBALS['__content'][7]) && !empty($GLOBALS['__options']['bw_content_backfill_done']));
$GLOBALS['__content'] = [];
BW_Course_Info::backfill_content_once();
check('backfill_content_once(): runs only once', empty($GLOBALS['__content']));

BW_Course_Info::on_save_post(7);
check('on_save_post(): generates for saves outside the ACF form', !empty($GLOBALS['__content'][7]));

printf("\n%d/%d checks passed\n", $pass, $pass + $fail);
exit($fail ? 1 : 0);
