<?php
if (!defined('ABSPATH')) exit;

/**
 * [bw_credits_course_list] — Terminliste mit optionalen Filtern.
 *
 * Ersetzt das frühere Snippet: nutzt den eingestellten Inhaltstyp statt
 * eines fest verdrahteten course_slot, rechnet in der WordPress-Zeitzone
 * und bindet Verfügbarkeit und Buchen-Button gleich mit ein.
 */

class BW_Course_List {

    /** Taxonomien für Anzeige und Filter — fehlende werden übersprungen. */
    private const TAXONOMIES = [
        'course_type'  => 'Kursart',
        'course_level' => 'Level',
        'course_lang'  => 'Sprache',
    ];

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
            'empty'        => 'Aktuell sind keine Termine geplant.',
        ], $atts, 'bw_credits_course_list');

        $show_filter = filter_var($atts['show_filter'], FILTER_VALIDATE_BOOLEAN);
        $selected    = self::selected_terms($atts, $show_filter);

        $slots = self::query_slots($atts, $selected);

        ob_start();
        echo '<div class="bw-course-slots">';

        if ($show_filter) {
            self::render_filter($selected);
        }

        if (empty($slots)) {
            echo '<p class="bw-course-slots-empty">' . esc_html($atts['empty']) . '</p>';
        } else {
            self::render_slots($slots, $atts);
        }

        echo '</div>';
        return ob_get_clean();
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

        return get_posts($args);
    }

    /* ---------------------------------------------------------
     * Filterformular
     * --------------------------------------------------------- */

    private static function render_filter(array $selected) {
        $available = [];

        foreach (self::TAXONOMIES as $taxonomy => $label) {
            if (!taxonomy_exists($taxonomy)) continue;

            $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);
            if (is_wp_error($terms) || empty($terms)) continue;

            $available[$taxonomy] = ['label' => $label, 'terms' => $terms];
        }

        if (empty($available)) return;
        ?>
        <form class="bw-course-filter" method="get">
            <?php
            // Bestehende Query-Parameter (z. B. Seiten-ID) erhalten
            foreach ($_GET as $key => $value) {
                $key = sanitize_key($key);
                if (strpos($key, 'bw_') === 0 || is_array($value)) continue;
                printf(
                    '<input type="hidden" name="%s" value="%s">',
                    esc_attr($key),
                    esc_attr(wp_unslash($value))
                );
            }
            ?>

            <?php foreach ($available as $taxonomy => $data) :
                $param = 'bw_' . str_replace('course_', '', $taxonomy);
                $value = $selected[$taxonomy] ?? '';
            ?>
                <label class="bw-course-filter__field">
                    <span><?php echo esc_html($data['label']); ?></span>
                    <select name="<?php echo esc_attr($param); ?>">
                        <option value="">Alle</option>
                        <?php foreach ($data['terms'] as $term) : ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($value, $term->slug); ?>>
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endforeach; ?>

            <button type="submit" class="bw-bwallet-btn">Filtern</button>
            <?php if ($selected) :
                // Nur die Filter entfernen — Seiten-Parameter wie page_id bleiben
                $reset = remove_query_arg(['bw_type', 'bw_level', 'bw_lang']);
            ?>
                <a class="bw-course-filter__reset" href="<?php echo esc_url($reset); ?>">Zurücksetzen</a>
            <?php endif; ?>
        </form>
        <?php
    }

    /* ---------------------------------------------------------
     * Ausgabe
     * --------------------------------------------------------- */

    private static function render_slots(array $slots, array $atts) {
        $group        = filter_var($atts['group_by_day'], FILTER_VALIDATE_BOOLEAN);
        $show_action  = filter_var($atts['show_action'], FILTER_VALIDATE_BOOLEAN);
        $show_avail   = filter_var($atts['availability'], FILTER_VALIDATE_BOOLEAN);
        $current_day  = '';

        echo '<ul class="bw-course-slot-list">';

        foreach ($slots as $slot) {
            $ts = self::slot_timestamp($slot->ID);

            if ($group && $ts) {
                $day = wp_date('Y-m-d', $ts);
                if ($day !== $current_day) {
                    $current_day = $day;
                    printf(
                        '<li class="bw-course-slot-day">%s</li>',
                        esc_html(wp_date('l, j. F', $ts))
                    );
                }
            }

            self::render_slot($slot, $ts, $show_action, $show_avail);
        }

        echo '</ul>';
    }

    private static function render_slot(WP_Post $slot, ?int $ts, bool $show_action, bool $show_avail) {
        $terms = [];
        foreach (array_keys(self::TAXONOMIES) as $taxonomy) {
            $name = bw_cs_first_term($slot->ID, $taxonomy);
            if ($name !== '') $terms[] = $name;
        }
        ?>
        <li class="bw-course-slot-item">
            <div class="bw-course-slot-main">
                <span class="bw-course-slot-time"><?php echo $ts ? esc_html(wp_date('H:i', $ts)) : '—'; ?></span>

                <a class="bw-course-slot-title" href="<?php echo esc_url(get_permalink($slot)); ?>">
                    <?php echo esc_html($slot->post_title ?: '#' . $slot->ID); ?>
                </a>

                <?php if ($terms) : ?>
                    <span class="bw-course-slot-meta"><?php echo esc_html(implode(' · ', $terms)); ?></span>
                <?php endif; ?>
            </div>

            <div class="bw-course-slot-side">
                <?php
                // Direkte Aufrufe statt do_shortcode — spart das Parsen je Zeile
                if ($show_avail) {
                    echo BW_Credits_Bookings_MVP::sc_availability(['slot_id' => $slot->ID]);
                }
                if ($show_action) {
                    echo BW_Credits_Bookings_MVP::sc_slot_action(['slot_id' => $slot->ID]);
                }
                ?>
            </div>
        </li>
        <?php
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
