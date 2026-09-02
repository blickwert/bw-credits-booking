<?php
if (!defined('ABSPATH')) exit;

/**
 * [bw_credits_course_list] — Terminliste mit optionalen Filtern.
 *
 * Reine Logik hier — das Markup liegt in templates/course_list/course_list.php,
 * überschreibbar im Theme unter bw-credits-booking/course_list/. Wortlaut
 * kommt über bw_text(), nicht aus dieser Datei oder dem Template.
 */

class BW_Course_List {

    /** Taxonomien für Anzeige und Filter — fehlende werden übersprungen. */
    private static function taxonomies(): array {
        return [
            'course_type'  => bw_text('course_list.filter.type'),
            'course_level' => bw_text('course_list.filter.level'),
            'course_lang'  => bw_text('course_list.filter.lang'),
        ];
    }

    public static function render($atts) {
        $atts = shortcode_atts([
            'limit'        => 20,
            'type'         => '',
            'level'        => '',
            'lang'         => '',
            'days'         => 0,
            'show_filter'  => 'false',
            'show_action'  => 'true',
            'availability' => 'true',
            'group_by_day' => 'true',
            'empty'        => '',   // leer = Text aus dem Katalog
        ], $atts, 'bw_credits_course_list');

        $show_filter = filter_var($atts['show_filter'], FILTER_VALIDATE_BOOLEAN);
        $selected    = self::selected_terms($atts, $show_filter);

        $slots = self::query_slots($atts, $selected);

        $items = [];
        foreach ($slots as $slot) {
            $items[] = ['slot' => $slot, 'ts' => self::slot_timestamp($slot->ID)];
        }

        do_action('bw_before_course_list', $atts);

        ob_start();
        bw_get_template('course_list/course_list.php', [
            'items'         => $items,
            'empty_message' => $atts['empty'] !== '' ? $atts['empty'] : bw_text('course_list.empty'),
            'taxonomies'    => self::taxonomies(),
            'group_by_day'  => filter_var($atts['group_by_day'], FILTER_VALIDATE_BOOLEAN),
            'show_action'   => filter_var($atts['show_action'], FILTER_VALIDATE_BOOLEAN),
            'show_avail'    => filter_var($atts['availability'], FILTER_VALIDATE_BOOLEAN),
            'show_filter'   => $show_filter,
            'filter'        => $show_filter ? self::build_filter_data($selected) : [],
        ]);
        $html = ob_get_clean();

        do_action('bw_after_course_list', $atts);

        return $html;
    }

    /* ---------------------------------------------------------
     * Auswahl: Shortcode-Attribute, bei aktivem Filter vom Formular
     * überschrieben
     * --------------------------------------------------------- */

    private static function selected_terms(array $atts, bool $show_filter): array {
        $map = [
            'course_type'  => $atts['type'],
            'course_level' => $atts['level'],
            'course_lang'  => $atts['lang'],
        ];

        if ($show_filter) {
            foreach ($map as $taxonomy => $default) {
                $param = 'bw_' . str_replace('course_', '', $taxonomy);
                if (isset($_GET[$param])) {
                    $map[$taxonomy] = sanitize_title(wp_unslash($_GET[$param]));
                }
            }
        }

        return array_filter($map, static function ($v) { return $v !== ''; });
    }

    /* ---------------------------------------------------------
     * Abfrage
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

        // Erlaubt Sortierung, Ausschlüsse oder zusätzliche Filter ohne
        // Template-Kopie — z. B. um bereits gebuchte Termine auszublenden
        $args = apply_filters('bw_course_list_query_args', $args, $atts, $selected);

        return get_posts($args);
    }

    /* ---------------------------------------------------------
     * Filterformular — Daten fürs Template, kein Markup hier
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
            // Nur die Filter entfernen — Seiten-Parameter wie page_id bleiben
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
}
