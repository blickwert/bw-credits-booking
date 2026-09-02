<?php
/**
 * Guthaben — einfache Anzeige (mode="always")
 *
 * Override: yourtheme/bw-credits-booking/balance/simple.php
 *
 * @var string $format  'inline' | 'block'
 * @var string $label   nur bei format=block sichtbar
 * @var string $number  fertiges <span data-bw-balance>…</span>-Markup
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;

if ($format === 'block') : ?>
    <p class="bw-balance"><?php echo esc_html($label); ?> <strong><?php echo $number; ?></strong></p>
<?php else : ?>
    <?php echo $number; ?>
<?php endif;
