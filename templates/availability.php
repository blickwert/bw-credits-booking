<?php
/**
 * Verfügbarkeit — freie Plätze eines Termins
 *
 * Override: yourtheme/bw-credits-booking/availability.php
 *
 * Drei Zustände stehen gleichzeitig im Markup und werden über data-bw-state
 * umgeschaltet, damit das JS nach Buchung/Storno live wechseln kann — auch
 * über die Schwelle hinweg (exakte Zahl ↔ "mehr als N").
 *
 * @var int    $slot_id
 * @var int    $free        aktuelle Anzahl freier Plätze
 * @var int    $cap         Schwelle aus den Einstellungen, 0 = immer exakt
 * @var string $state       'free' | 'many' | 'full'
 * @var string $free_before Text vor der Zahl (aus dem {frei}-Format)
 * @var string $free_after  Text nach der Zahl
 * @var string $more_text   fertig zusammengesetzter "mehr als N"-Text
 * @var string $full_text
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;
?>
<span class="bw-availability" data-bw-availability="<?php echo (int) $slot_id; ?>" data-bw-state="<?php echo esc_attr($state); ?>">
    <span data-bw-free-wrap><?php echo esc_html($free_before); ?><span data-bw-free><?php echo (int) $free; ?></span><?php echo esc_html($free_after); ?></span>
    <span data-bw-many-wrap><?php echo esc_html($more_text); ?></span>
    <span data-bw-full-wrap><?php echo esc_html($full_text); ?></span>
</span>
