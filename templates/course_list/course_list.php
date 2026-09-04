<?php
/**
 * [bw_credits_course_list] — session list
 *
 * Frame, optional filter form, day grouping, and the individual session
 * rows all in one file.
 *
 * Override: yourtheme/bw-credits-booking/course_list/course_list.php
 *
 * @var array  $items         [['slot' => WP_Post, 'ts' => int|null], …], empty = $empty_message
 * @var string $empty_message
 * @var array  $taxonomies    taxonomy => label, for the meta line per session
 * @var bool   $group_by_day
 * @var bool   $show_action
 * @var bool   $show_avail
 * @var bool   $show_filter
 * @var array  $filter        only relevant when $show_filter — see below
 *
 * $filter, when $show_filter is true:
 *   'available' taxonomy => ['label' => string, 'terms' => WP_Term[]]
 *   'selected'  taxonomy => the selected term slug
 *   'hidden'    query parameter => value, kept as hidden fields
 *   'reset_url' empty if no filter is active
 *
 * @version 0.15.0
 */
if (!defined('ABSPATH')) exit;
?>
<div class="bw-course-slots">
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
    <?php endif; ?>

    <?php if (empty($items)) : ?>
        <p class="bw-course-slots-empty"><?php echo esc_html($empty_message); ?></p>
    <?php else :
        $current_day = '';
    ?>
        <ul class="bw-course-slot-list">
            <?php foreach ($items as $item) :
                $slot = $item['slot'];
                $ts   = $item['ts'];

                if ($group_by_day && $ts) {
                    $day = wp_date('Y-m-d', $ts);
                    if ($day !== $current_day) :
                        $current_day = $day;
                        ?>
                        <li class="bw-course-slot-day"><?php echo esc_html(wp_date('l, j. F', $ts)); ?></li>
                        <?php
                    endif;
                }

                $terms = [];
                foreach (array_keys($taxonomies) as $taxonomy) {
                    $name = bw_cs_first_term($slot->ID, $taxonomy);
                    if ($name !== '') $terms[] = $name;
                }

                do_action('bw_before_slot_item', $slot);
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
                        // Direct calls instead of do_shortcode — saves parsing per row
                        if ($show_avail) {
                            echo BW_Credits_Bookings_MVP::sc_availability(['slot_id' => $slot->ID]);
                        }
                        if ($show_action) {
                            echo BW_Credits_Bookings_MVP::sc_slot_action(['slot_id' => $slot->ID]);
                        }
                        ?>
                    </div>
                </li>
                <?php do_action('bw_after_slot_item', $slot); ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
