<?php
/**
 * Buchen/Stornieren — Button oder Hinweis
 *
 * Zwei Varianten in einer Datei: 'action' der Buchen/Stornieren-Button,
 * 'note' ein Hinweistext statt Button (ausgebucht, kein Guthaben, Stornofrist
 * abgelaufen, nicht eingeloggt).
 *
 * Override: yourtheme/bw-credits-booking/course_booking/course_booking.php
 *
 * @var string $variant  'action' | 'note'
 *
 * Bei $variant === 'action':
 * @var string   $action        'book' | 'cancel'
 * @var int      $slot_id
 * @var int|null $booking_id    nur bei action=cancel gesetzt
 * @var string   $label         aktuell sichtbare Beschriftung
 * @var string   $label_book    für den JS-Wechsel zurück auf Buchen
 * @var string   $label_cancel  für den JS-Wechsel auf Stornieren
 * @var string   $class
 *
 * Bei $variant === 'note':
 * @var string $text          wird escaped ausgegeben
 * @var string $modifier      zusätzliche CSS-Klasse, z. B. "bw-is-full", leer möglich
 * @var string $suffix_html   bereits fertiges HTML (z. B. ein Link), unescaped angehängt
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
