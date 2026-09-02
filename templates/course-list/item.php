<?php
/**
 * Terminliste — einzelne Terminzeile
 *
 * Override: yourtheme/bw-credits-booking/course-list/item.php
 *
 * @var WP_Post $slot
 * @var int|null $ts          Startzeit als Unix-Timestamp, null wenn unbekannt
 * @var string[] $terms       Namen der zugeordneten Taxonomie-Terms
 * @var bool $show_action
 * @var bool $show_avail
 * @version 0.13.0
 */
if (!defined('ABSPATH')) exit;

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
do_action('bw_after_slot_item', $slot);
