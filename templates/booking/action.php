<?php
/**
 * Buchen/Stornieren-Button
 *
 * Ein Button der über data-bw-toggle nach dem Klick per JS zwischen Buchen
 * und Stornieren umschaltet, ohne die Seite neu zu laden.
 *
 * Override: yourtheme/bw-credits-booking/booking/action.php
 *
 * @var string   $action        'book' | 'cancel'
 * @var int      $slot_id
 * @var int|null $booking_id    nur bei action=cancel gesetzt
 * @var string   $label         aktuell sichtbare Beschriftung
 * @var string   $label_book    für den JS-Wechsel zurück auf Buchen
 * @var string   $label_cancel  für den JS-Wechsel auf Stornieren
 * @var string   $class
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;
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
