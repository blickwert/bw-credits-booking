<?php
/**
 * Buchungsliste — eine Buchungszeile
 *
 * Override: yourtheme/bw-credits-booking/bookings/item.php
 *
 * @var int      $slot_id
 * @var int      $booking_id
 * @var string   $status
 * @var bool     $is_active
 * @var string   $slot_title
 * @var string   $permalink     leer wenn kein Permalink verfügbar ist
 * @var string   $start_str
 * @var string   $status_label
 * @var string[] $meta_bits     Kursart/Level/Sprache, bereits gefiltert
 * @var bool     $can_cancel
 * @var bool     $show_access
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;

do_action('bw_before_bookings_item', $booking_id, $slot_id);
?>
<div class="bw-booking-item bw-status-<?php echo esc_attr($status); ?>">
    <div class="bw-booking-slot">
        <?php if ($permalink !== '') : ?>
            <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($slot_title); ?></a>
        <?php else : ?>
            <?php echo esc_html($slot_title); ?>
        <?php endif; ?>

        <?php if ($meta_bits) : ?>
            <span class="bw-booking-meta"><?php echo esc_html(implode(' · ', $meta_bits)); ?></span>
        <?php endif; ?>
    </div>

    <div class="bw-booking-time"><?php echo esc_html($start_str); ?></div>
    <div class="bw-booking-status"><?php echo esc_html($status_label); ?></div>

    <?php if ($can_cancel) : ?>
        <?php echo BW_Credits_Bookings_MVP::sc_slot_action(['course_id' => $slot_id]); ?>
    <?php endif; ?>

    <?php if ($show_access && $is_active && $status === 'booked') : ?>
        <?php echo BW_View_Access::render(['course_id' => $slot_id, 'title' => '']); ?>
    <?php endif; ?>
</div>
<?php
do_action('bw_after_bookings_item', $booking_id, $slot_id);
