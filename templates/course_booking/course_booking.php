<?php
/**
 * Book/Cancel — button or note
 *
 * Two variants in one file: 'action' is the book/cancel button, 'note'
 * is a note text instead of a button (fully booked, no credits,
 * cancellation deadline passed, not logged in).
 *
 * Override: yourtheme/bw-credits-booking/course_booking/course_booking.php
 *
 * @var string $variant  'action' | 'note'
 *
 * When $variant === 'action':
 * @var string   $action        'book' | 'cancel'
 * @var int      $slot_id
 * @var int|null $booking_id    only set when action=cancel
 * @var string   $label         the currently visible label
 * @var string   $label_book    for the JS switch back to "book"
 * @var string   $label_cancel  for the JS switch to "cancel"
 * @var string   $class
 *
 * When $variant === 'note':
 * @var string $text          output escaped
 * @var string $modifier      additional CSS class, e.g. "bw-is-full", can be empty
 * @var string $suffix_html   already-finished HTML (e.g. a link), appended unescaped
 *
 * @version 0.15.0
 */
if (!defined('ABSPATH')) exit;

if ($variant === 'action') :
    ?>
    <div data-bw-wrap="1">
        <button type="button" class="<?php echo esc_attr($class); ?>"
                data-bw-action="<?php echo esc_attr($action); ?>" data-bw-toggle="1"
                data-slot-id="<?php echo (int) $slot_id; ?>"
                <?php if (!empty($booking_id)) : ?>data-booking-id="<?php echo (int) $booking_id; ?>"<?php endif; ?>
                data-label-book="<?php echo esc_attr($label_book); ?>"
                data-label-cancel="<?php echo esc_attr($label_cancel); ?>">
            <?php echo esc_html($label); ?>
        </button>
        <div class="bw-bwallet-msg" data-bw-msg></div>
    </div>
    <?php
else :
    ?>
    <p class="bw-slot-note<?php echo $modifier !== '' ? ' ' . esc_attr($modifier) : ''; ?>">
        <?php echo esc_html($text) . $suffix_html; ?>
    </p>
    <?php
endif;
