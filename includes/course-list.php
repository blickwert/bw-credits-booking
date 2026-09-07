<?php
if (!defined('ABSPATH')) exit;

/**
 * [bw_credits_course_list] — session list with optional filters.
 *
 * Pure logic here — the markup lives in
 * templates/course_list/course_list.php, overridable in the theme under
 * bw-credits-booking/course_list/. Wording comes via bw_text(), not from
 * this file or the template.
 */

class BW_Course_List {

    /** Taxonomies for filtering — missing ones are skipped. */
    private static function taxonomies(): array {
        return [
            'course_type'  => bw_text('course_list.filter.type'),
            'course_level' => bw_text('course_list.filter.level'),
            'course_lang'  => bw_text('course_list.filter.lang'),
        ];
    }

    /**
     * Taxonomies shown in each row's meta line. course_type is left out —
     * it's already part of the auto-generated session title.
     */
    private static function display_taxonomies(): array {
        return [
            'course_level' => bw_text('course_list.filter.level'),
            'course_lang'  => bw_text('course_list.filter.lang'),
        ];
    }

    const ATTS_DEFAULTS = [
        'limit'        => 20,
        'type'         => '',
        'level'        => '',
        'lang'         => '',
        'days'         => 0,
        'show_filter'  => 'true',
        'show_action'  => 'true',
        'availability' => 'true',
        'empty'        => '',   // empty = text from the catalogue
    ];

    public static function render($atts) {
        $atts = shortcode_atts(self::ATTS_DEFAULTS, $atts, 'bw_credits_course_list');

        $show_filter = filter_var($atts['show_filter'], FILTER_VALIDATE_BOOLEAN);
        $selected    = self::selected_terms($atts, $show_filter);

        BW_Credits_Bookings_MVP::ensure_assets();

        ob_start();
        bw_get_template('course_list/course_list.php', [
            'results_html' => self::render_results($atts, $selected),
            'atts_json'    => wp_json_encode([
                'limit'        => (int) $atts['limit'],
                'days'         => (int) $atts['days'],
                'type'         => $atts['type'],
                'level'        => $atts['level'],
                'lang'         => $atts['lang'],
                'show_action'  => $atts['show_action'],
                'availability' => $atts['availability'],
                'empty'        => $atts['empty'],
            ]),
            'show_filter'  => $show_filter,
            'filter'       => $show_filter ? self::build_filter_data($selected) : [],
        ]);
        return ob_get_clean();
    }

    /**
     * Queries the sessions and renders just the results fragment (empty
     * message or the `<ul>`) — no filter form. Used by render() for the
     * initial page load, and by the REST callback for every AJAX filter
     * refresh, so both paths run the exact same query + markup.
     */
    public static function render_results(array $atts, array $selected): string {
        do_action('bw_before_course_list', $atts);

        $slots = self::query_slots($atts, $selected);

        $items = [];
        foreach ($slots as $slot) {
            $items[] = ['slot' => $slot, 'ts' => self::slot_timestamp($slot->ID)];
        }

        ob_start();
        bw_get_template('course_list/course_list-results.php', [
            'items'         => $items,
            'empty_message' => $atts['empty'] !== '' ? $atts['empty'] : bw_text('course_list.empty'),
            'taxonomies'    => self::display_taxonomies(),
            'show_action'   => filter_var($atts['show_action'], FILTER_VALIDATE_BOOLEAN),
            'show_avail'    => filter_var($atts['availability'], FILTER_VALIDATE_BOOLEAN),
        ]);
        $html = ob_get_clean();

        do_action('bw_after_course_list', $atts);

        return $html;
    }

    /* ---------------------------------------------------------
     * Selection: shortcode attributes, overridden by the filter (GET on
     * the initial page load, the AJAX request's params on a refresh)
     * --------------------------------------------------------- */

    private static function selected_terms(array $atts, bool $show_filter): array {
        $overrides = [];

        if ($show_filter) {
            foreach (['bw_type', 'bw_level', 'bw_lang'] as $param) {
                if (isset($_GET[$param])) {
                    $overrides[$param] = wp_unslash($_GET[$param]);
                }
            }
        }

        return self::merge_selected($atts, $overrides);
    }

    /**
     * Applies taxonomy overrides (bw_type/bw_level/bw_lang, from $_GET or
     * a REST request) onto the shortcode's own type/level/lang attributes
     * — a taxonomy is only overridden when its param is explicitly present
     * in $overrides, so a taxonomy without a filter field (no available
     * terms) keeps falling back to the shortcode attribute.
     */
    private static function merge_selected(array $atts, array $overrides): array {
        $map = [
            'course_type'  => $atts['type'],
            'course_level' => $atts['level'],
            'course_lang'  => $atts['lang'],
        ];

        foreach ($map as $taxonomy => $default) {
            $param = 'bw_' . str_replace('course_', '', $taxonomy);
            if (array_key_exists($param, $overrides)) {
                $map[$taxonomy] = sanitize_title((string) $overrides[$param]);
            }
        }

        return array_filter($map, static function ($v) { return $v !== ''; });
    }

    /* ---------------------------------------------------------
     * Query
     * --------------------------------------------------------- */

    private static function query_slots(array $atts, array $selected): array {
        $meta_key = BW_Credits_Bookings_MVP::META_START_DT;
        $now      = current_time('mysql');

        $meta_query = [[
            'key'     => $meta_key,
            'value'   => $now,
            'compare' => '>=',
            'type'    => 'DATETIME',
        ]];

        $days = (int) $atts['days'];
        if ($days > 0) {
            $until = (new DateTime('now', wp_timezone()))
                ->modify('+' . $days . ' days')
                ->format('Y-m-d H:i:s');

            $meta_query[] = [
                'key'     => $meta_key,
                'value'   => $until,
                'compare' => '<=',
                'type'    => 'DATETIME',
            ];
        }

        $tax_query = [];
        foreach ($selected as $taxonomy => $slug) {
            if (!taxonomy_exists($taxonomy)) continue;

            $tax_query[] = [
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $slug,
            ];
        }

        $args = [
            'post_type'      => BW_Settings::get_slot_post_type(),
            'post_status'    => 'publish',
            'posts_per_page' => max(1, min(200, (int) $atts['limit'])),
            'meta_key'       => $meta_key,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => $meta_query,
            'no_found_rows'  => true,
        ];

        if ($tax_query) {
            $args['tax_query'] = $tax_query;
        }

        // Allows sorting, exclusions, or additional filters without a
        // template copy — e.g. to hide sessions already booked
        $args = apply_filters('bw_course_list_query_args', $args, $atts, $selected);

        return get_posts($args);
    }

    /* ---------------------------------------------------------
     * Filter form — data for the template, no markup here
     * --------------------------------------------------------- */

    private static function build_filter_data(array $selected): array {
        $available = [];

        foreach (self::taxonomies() as $taxonomy => $label) {
            if (!taxonomy_exists($taxonomy)) continue;

            $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);
            if (is_wp_error($terms) || empty($terms)) continue;

            $available[$taxonomy] = ['label' => $label, 'terms' => $terms];
        }

        $hidden = [];
        foreach ($_GET as $key => $value) {
            $key = sanitize_key($key);
            if (strpos($key, 'bw_') === 0 || is_array($value)) continue;
            $hidden[$key] = (string) wp_unslash($value);
        }

        $reset_url = $selected
            // Only remove the filters — page parameters like page_id stay
            ? (string) remove_query_arg(['bw_type', 'bw_level', 'bw_lang'])
            : '';

        return [
            'available' => $available,
            'selected'  => $selected,
            'hidden'    => $hidden,
            'reset_url' => $reset_url,
        ];
    }

    private static function slot_timestamp(int $slot_id): ?int {
        $raw = get_post_meta($slot_id, BW_Credits_Bookings_MVP::META_START_DT, true);
        if (!$raw) return null;

        try {
            return (new DateTime($raw, wp_timezone()))->getTimestamp();
        } catch (Exception $e) {
            return null;
        }
    }

    /* ---------------------------------------------------------
     * AJAX filter — public and read-only (permission_callback has no
     * login requirement). WP still validates the X-WP-Nonce header for a
     * logged-in cookie session regardless of permission_callback, so the
     * JS sends one anyway — just not required for logged-out visitors.
     * --------------------------------------------------------- */

    public static function register_rest_route() {
        register_rest_route('bw-credits/v1', '/course-list', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => [__CLASS__, 'rest_course_list'],
        ]);
    }

    public static function rest_course_list(WP_REST_Request $req) {
        $atts = shortcode_atts(self::ATTS_DEFAULTS, $req->get_params(), 'bw_credits_course_list');

        $overrides = [];
        foreach (['bw_type', 'bw_level', 'bw_lang'] as $param) {
            if ($req->get_param($param) !== null) {
                $overrides[$param] = $req->get_param($param);
            }
        }

        $selected = self::merge_selected($atts, $overrides);

        return ['html' => self::render_results($atts, $selected)];
    }
}

add_action('rest_api_init', ['BW_Course_List', 'register_rest_route']);
