<?php
/**
 * Hinweis statt Buchen/Stornieren-Button
 *
 * Erscheint z. B. bei ausgebuchtem Termin, fehlendem Guthaben oder wenn die
 * Stornofrist abgelaufen ist.
 *
 * Override: yourtheme/bw-credits-booking/booking/note.php
 *
 * @var string $text          wird escaped ausgegeben
 * @var string $modifier      zusätzliche CSS-Klasse, z. B. "bw-is-full", leer möglich
 * @var string $suffix_html   bereits fertiges HTML (z. B. ein Link), unescaped angehängt
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;
?>
<p class="bw-slot-note<?php echo $modifier !== '' ? ' ' . esc_attr($modifier) : ''; ?>">
    <?php echo esc_html($text) . $suffix_html; ?>
</p>
