<?php
/**
 * Buchungsliste — Meldung ohne Treffer
 *
 * Override: yourtheme/bw-credits-booking/bookings/empty.php
 *
 * @var string $message
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;
?>
<p class="bw-no-bookings"><?php echo esc_html($message); ?></p>
