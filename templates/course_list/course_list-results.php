<?php
/**
 * [bw_credits_course_list] — results fragment (empty message or the
 * session list itself).
 *
 * Rendered by BW_Course_List::render_results(), both for the initial page
 * load and for every AJAX filter refresh — kept separate from
 * course_list.php so an AJAX response can return just this fragment
 * without re-rendering the filter form around it.
 *
 * Override: yourtheme/bw-credits-booking/course_list/course_list-results.php
 *
 * @var array  $items         [['slot' => WP_Post, 'ts' => int|null], …], empty = $empty_message
 * @var string $empty_message
 * @var array  $taxonomies    taxonomy => label, for the meta line per session
 * @var bool   $show_action
 * @var bool   $show_avail
 *
 * @version 0.25.0
 */
if (!defined('ABSPATH')) exit;
?>
<?php if (empty($items)) : ?>
    <p class="bw-course-slots-empty"><?php echo esc_html($empty_message); ?></p>
<?php else : ?>
    <ul class="bw-course-slot-list">
        <?php foreach ($items as $item) :
            $slot = $item['slot'];
            $ts   = $item['ts'];

            $terms = [];
            foreach (array_keys($taxonomies) as $taxonomy) {
                $name = bw_cs_first_term($slot->ID, $taxonomy);
                if ($name !== '') $terms[] = $name;
            }

            do_action('bw_before_slot_item', $slot);
        ?>
            <li class="bw-course-slot-item">
                <div class="bw-course-slot-date">
                    <?php if ($ts) : ?>
                        <span class="bw-course-slot-date__dow"><?php echo esc_html(wp_date('D', $ts)); ?></span>
                        <span class="bw-course-slot-date__day"><?php echo esc_html(wp_date('j', $ts)); ?></span>
                        <span class="bw-course-slot-date__month"><?php echo esc_html(wp_date('M', $ts)); ?></span>
                        <span class="bw-course-slot-date__time"><?php echo esc_html(wp_date('H:i', $ts)); ?></span>
                    <?php else : ?>
                        <span class="bw-course-slot-date__day">—</span>
                    <?php endif; ?>
                </div>

                <div class="bw-course-slot-main">
                    <a class="bw-course-slot-title" href="<?php echo esc_url(get_permalink($slot)); ?>">
                        <?php
                        // post_title is already HTML-entity-safe from WordPress' own save
                        // pipeline (sanitize_post_field) — esc_html() here would double-escape it.
                        echo $slot->post_title !== '' ? $slot->post_title : '#' . $slot->ID;
                        ?>
                    </a>

                    <div class="bw-course-slot-info">
                        <?php if ($terms) : ?>
                            <span class="bw-course-slot-meta"><?php echo esc_html(implode(' · ', $terms)); ?></span>
                        <?php endif; ?>

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
                </div>
            </li>
            <?php do_action('bw_after_slot_item', $slot); ?>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
