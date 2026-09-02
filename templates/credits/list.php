<?php
/**
 * Guthaben-Details — Rahmen
 *
 * Override: yourtheme/bw-credits-booking/credits/list.php
 *
 * @var array $items  je Eintrag die Variablen aus credits/item.php
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;
?>
<ul class="bw-credits-list">
    <?php foreach ($items as $item) : ?>
        <?php bw_get_template('credits/item.php', $item); ?>
    <?php endforeach; ?>
</ul>
