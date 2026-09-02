<?php
/**
 * Konto-Übersicht — Guthaben-Block
 *
 * Override: yourtheme/bw-credits-booking/overview/balance.php
 *
 * @var int    $available
 * @var string $label  "Credit verfügbar" oder "Credits verfügbar", schon aufgelöst
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;
?>
<div class="bw-credits-overview__balance">
    <span class="bw-credits-overview__count" data-bw-balance><?php echo (int) $available; ?></span>
    <span class="bw-credits-overview__label"><?php echo esc_html($label); ?></span>
</div>
