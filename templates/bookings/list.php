<?php
/**
 * Buchungsliste — Rahmen
 *
 * Override: yourtheme/bw-credits-booking/bookings/list.php
 *
 * @var array $items  je Eintrag die Variablen aus bookings/item.php
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;
?>
<div class="bw-my-bookings">
    <?php foreach ($items as $item) : ?>
        <?php bw_get_template('bookings/item.php', $item); ?>
    <?php endforeach; ?>
</div>
