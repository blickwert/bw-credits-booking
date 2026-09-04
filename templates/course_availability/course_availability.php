<?php
/**
 * Availability — a session's free spots
 *
 * Override: yourtheme/bw-credits-booking/course_availability/course_availability.php
 *
 * All three states sit in the markup at once and are toggled via
 * data-bw-state, so the JS can switch live after a booking/cancellation
 * — even across the threshold (exact number ↔ "more than N").
 *
 * @var int    $slot_id
 * @var int    $free        current number of free spots
 * @var int    $cap         threshold from the settings, 0 = always exact
 * @var string $state       'free' | 'many' | 'full'
 * @var string $free_before text before the number (from the {free} format)
 * @var string $free_after  text after the number
 * @var string $more_text   fully assembled "more than N" text
 * @var string $full_text
 * @version 0.15.0
 */
if (!defined('ABSPATH')) exit;
?>
<span class="bw-availability" data-bw-availability="<?php echo (int) $slot_id; ?>" data-bw-state="<?php echo esc_attr($state); ?>">
    <span data-bw-free-wrap><?php echo esc_html($free_before); ?><span data-bw-free><?php echo (int) $free; ?></span><?php echo esc_html($free_after); ?></span>
    <span data-bw-many-wrap><?php echo esc_html($more_text); ?></span>
    <span data-bw-full-wrap><?php echo esc_html($full_text); ?></span>
</span>
