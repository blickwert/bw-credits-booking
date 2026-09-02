<?php
/**
 * Terminliste — Rahmen und Tagesgruppierung
 *
 * Bindet für jeden Termin course-list/item.php ein.
 *
 * Override: yourtheme/bw-credits-booking/course-list/list.php
 *
 * @var array $items          [['slot' => WP_Post, 'ts' => int|null], …]
 * @var array $taxonomies     taxonomy => Label, für die Meta-Zeile je Termin
 * @var bool  $group_by_day
 * @var bool  $show_action
 * @var bool  $show_avail
 * @version 0.13.0
 */
if (!defined('ABSPATH')) exit;

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

        bw_get_template('course-list/item.php', [
            'slot'        => $slot,
            'ts'          => $ts,
            'terms'       => $terms,
            'show_action' => $show_action,
            'show_avail'  => $show_avail,
        ]);
    endforeach; ?>
</ul>
