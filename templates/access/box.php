<?php
/**
 * Zugangsdaten-Box
 *
 * Override: yourtheme/bw-credits-booking/access/box.php
 *
 * @var string $title  leer = keine Überschrift
 * @var string $link   leer = kein Meeting-Link vorhanden
 * @var string $info   leer = keine Zusatzinfo vorhanden
 * @var string $link_label
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;
?>
<div class="bw-access">
    <?php if ($title !== '') : ?>
        <h3 class="bw-access__title"><?php echo esc_html($title); ?></h3>
    <?php endif; ?>

    <?php if ($link !== '') : ?>
        <p class="bw-access__link">
            <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener">
                <?php echo esc_html($link_label); ?>
            </a>
        </p>
    <?php endif; ?>

    <?php if ($info !== '') : ?>
        <div class="bw-access__info"><?php echo nl2br(esc_html($info)); ?></div>
    <?php endif; ?>
</div>
