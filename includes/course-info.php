<?php
if (!defined('ABSPATH')) exit;

/**
 * Course session (single page): data for the Elementor template and the
 * featured image taken from the course type.
 *
 * [bw_credits_course_info field="…"] prints one value of a session, so the
 * single template can be built from plain Elementor widgets:
 *   type, type_description, level, level_description, language,
 *   start, date, time, duration, detail, calendar
 * Without course_id it uses the current post.
 *
 * Featured image: the image (ACF "img") of the session's course type becomes
 * the session's featured image on save — and when the type's image changes.
 * A featured image chosen by hand is never replaced.
 */
class BW_Course_Info {

    const META_AUTO_THUMB = '_bw_thumb_auto';
    const OPT_BACKFILL    = 'bw_thumb_backfill_done';

    public static function init() {
        add_action('acf/save_post', [__CLASS__, 'on_acf_save'], 30);
        add_action('admin_init', [__CLASS__, 'backfill_once']);
    }

    /* ---------------------------------------------------------
     * Shortcode
     * --------------------------------------------------------- */

    public static function render($atts): string {
        $atts = shortcode_atts([
            'course_id' => 0,
            'field'     => '',
        ], $atts, 'bw_credits_course_info');

        $slot_id = BW_Credits_Bookings_MVP::resolve_course_id((int) $atts['course_id']);
        if ($slot_id <= 0) return '';

        switch (sanitize_key((string) $atts['field'])) {
            case 'type':              return esc_html(self::term_field($slot_id, 'course_type', 'name'));
            case 'type_description':  return self::rich(self::term_field($slot_id, 'course_type', 'description'));
            case 'level':             return esc_html(self::term_field($slot_id, 'course_level', 'name'));
            case 'level_description': return self::rich(self::term_field($slot_id, 'course_level', 'description'));
            case 'language':          return esc_html(self::term_field($slot_id, 'course_lang', 'name'));
            case 'start':             return esc_html(self::start($slot_id, 'd.m.Y H:i'));
            case 'date':              return esc_html(self::start($slot_id, 'd.m.Y'));
            case 'time':              return esc_html(self::start($slot_id, 'H:i'));
            case 'duration':          return esc_html(self::duration($slot_id));
            case 'detail':            return self::rich((string) get_post_field('post_content', $slot_id));
            case 'calendar':          return self::calendar($slot_id);
        }
        return '';
    }

    /** Name or description of the session's first term in a taxonomy, as raw text. */
    private static function term_field(int $slot_id, string $taxonomy, string $what): string {
        if (!taxonomy_exists($taxonomy)) return '';
        $terms = get_the_terms($slot_id, $taxonomy);
        if (empty($terms) || is_wp_error($terms)) return '';

        $term  = bw_cs_translate_term($terms[0]);
        $value = (string) ($term->$what ?? '');
        // Names are stored entity-escaped ("Ground &amp; Connect"): decode so
        // esc_html() escapes exactly once.
        return $what === 'name' ? html_entity_decode($value, ENT_QUOTES, 'UTF-8') : $value;
    }

    /** Description / free text: line breaks become paragraphs, markup is filtered. */
    private static function rich(string $text): string {
        $text = trim($text);
        return $text === '' ? '' : wp_kses_post(wpautop($text));
    }

    private static function start(int $slot_id, string $format): string {
        $start = BW_Credits_Bookings_MVP::get_slot_start_datetime($slot_id);
        return $start ? wp_date((string) apply_filters('bw_course_info_format', $format, $slot_id), $start->getTimestamp()) : '';
    }

    /**
     * The calendar-leaf date block (weekday, day, month, time) — same markup
     * and styles as in [bw_credits_course_list], so a single session looks
     * like a row of the list.
     */
    private static function calendar(int $slot_id): string {
        $start = BW_Credits_Bookings_MVP::get_slot_start_datetime($slot_id);
        BW_Credits_Bookings_MVP::ensure_assets();

        $html = '<div class="bw-course-slot-date">';
        if ($start) {
            $ts    = $start->getTimestamp();
            $html .= '<span class="bw-course-slot-date__dow">' . esc_html(wp_date('D', $ts)) . '</span>'
                   . '<span class="bw-course-slot-date__day">' . esc_html(wp_date('j', $ts)) . '</span>'
                   . '<span class="bw-course-slot-date__month">' . esc_html(wp_date('M', $ts)) . '</span>'
                   . '<span class="bw-course-slot-date__time">' . esc_html(wp_date('H:i', $ts)) . '</span>';
        } else {
            $html .= '<span class="bw-course-slot-date__day">—</span>';
        }
        return $html . '</div>';
    }

    private static function duration(int $slot_id): string {
        $minutes = (int) get_post_meta($slot_id, 'duration', true);
        if ($minutes <= 0) return '';
        /* translators: %d: duration of a session in minutes */
        return sprintf(__('%d min.', 'bw-credits-booking'), $minutes);
    }

    /* ---------------------------------------------------------
     * Featured image from the course type
     * --------------------------------------------------------- */

    public static function on_acf_save($post_id) {
        if (is_string($post_id) && strpos($post_id, 'term_') === 0) {
            self::sync_type_term((int) substr($post_id, 5));
            return;
        }
        self::sync_slot((int) $post_id);
    }

    /** Sets the slot's featured image from its type unless one was chosen by hand. */
    public static function sync_slot(int $slot_id): void {
        if ($slot_id <= 0 || get_post_type($slot_id) !== BW_Settings::get_slot_post_type()) return;

        $terms = get_the_terms($slot_id, 'course_type');
        if (empty($terms) || is_wp_error($terms)) return;

        $image = (int) get_term_meta((int) $terms[0]->term_id, 'img', true);
        if ($image <= 0) return;

        $current = (int) get_post_thumbnail_id($slot_id);
        $auto    = (int) get_post_meta($slot_id, self::META_AUTO_THUMB, true);

        // Empty, or still the image we set ourselves earlier → follow the type.
        if ($current === 0 || $current === $auto) {
            set_post_thumbnail($slot_id, $image);
            update_post_meta($slot_id, self::META_AUTO_THUMB, $image);
        }
    }

    /** The type's image changed → update all sessions of that type. */
    public static function sync_type_term(int $term_id): void {
        $term = get_term($term_id, 'course_type');
        if (!$term || is_wp_error($term)) return;

        foreach (self::slot_ids(['taxonomy' => 'course_type', 'field' => 'term_id', 'terms' => [$term_id]]) as $slot_id) {
            self::sync_slot((int) $slot_id);
        }
    }

    /** One run after installing/updating: sessions that already exist get their image too. */
    public static function backfill_once() {
        if (get_option(self::OPT_BACKFILL)) return;

        foreach (self::slot_ids() as $slot_id) {
            self::sync_slot((int) $slot_id);
        }
        update_option(self::OPT_BACKFILL, 1);
    }

    private static function slot_ids(array $tax_query = []): array {
        $args = [
            'post_type'      => BW_Settings::get_slot_post_type(),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];
        if ($tax_query) $args['tax_query'] = [$tax_query];
        return get_posts($args);
    }
}

BW_Course_Info::init();
