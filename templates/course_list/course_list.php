<?php
/**
 * [bw_credits_course_list] — session list
 *
 * Frame + optional filter form. The results themselves (empty message or
 * the session list) are rendered by course_list-results.php and, after
 * the initial load, refreshed via AJAX (assets/bwallet-frontend.js)
 * whenever the filter selection changes. The filter form stays a real
 * `method="get"` form throughout — a no-JS or fetch-failure fallback
 * still works exactly as before, just without the AJAX refresh.
 *
 * Override: yourtheme/bw-credits-booking/course_list/course_list.php
 *
 * @var string $results_html  pre-rendered HTML from course_list-results.php
 * @var string $atts_json     JSON-encoded non-filter shortcode attributes
 *                             (limit, days, type, level, lang, show_action,
 *                             availability, empty) — resent by the AJAX
 *                             filter on every request so it can rebuild
 *                             the same query the shortcode itself would
 * @var bool   $show_filter
 * @var array  $filter        only relevant when $show_filter — see below
 *
 * $filter, when $show_filter is true:
 *   'available' taxonomy => ['label' => string, 'terms' => WP_Term[]]
 *   'selected'  taxonomy => the selected term slug
 *   'hidden'    query parameter => value, kept as hidden fields
 *   'reset_url' empty if no filter is active
 *
 * @version 0.25.0
 */
if (!defined('ABSPATH')) exit;
?>
<div class="bw-course-slots" data-bw-course-list data-bw-atts="<?php echo esc_attr($atts_json); ?>">
    <?php if ($show_filter && !empty($filter['available'])) : ?>
        <form class="bw-course-filter" method="get">
            <?php foreach ($filter['hidden'] as $key => $value) : ?>
                <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
            <?php endforeach; ?>

            <?php foreach ($filter['available'] as $taxonomy => $data) :
                $param = 'bw_' . str_replace('course_', '', $taxonomy);
                $value = $filter['selected'][$taxonomy] ?? '';
            ?>
                <label class="bw-course-filter__field">
                    <span><?php echo esc_html($data['label']); ?></span>
                    <select name="<?php echo esc_attr($param); ?>">
                        <option value=""><?php echo esc_html(bw_text('course_list.filter.all')); ?></option>
                        <?php foreach ($data['terms'] as $term) : ?>
                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($value, $term->slug); ?>>
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endforeach; ?>

            <button type="submit" class="bw-bwallet-btn"><?php echo esc_html(bw_text('course_list.filter.submit')); ?></button>

            <?php if ($filter['reset_url'] !== '') : ?>
                <a class="bw-course-filter__reset" href="<?php echo esc_url($filter['reset_url']); ?>">
                    <?php echo esc_html(bw_text('course_list.filter.reset')); ?>
                </a>
            <?php endif; ?>
        </form>
        <p class="bw-bwallet-msg" data-bw-msg></p>
    <?php endif; ?>

    <div class="bw-course-slot-results" data-bw-course-list-results>
        <?php echo $results_html; ?>
    </div>
</div>
