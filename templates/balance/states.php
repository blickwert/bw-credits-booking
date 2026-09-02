<?php
/**
 * Guthaben — Aufforderung zum Nachkaufen (mode="empty_only")
 *
 * Beide Zustände stehen im Markup, umgeschaltet über data-bw-state, damit
 * der Hinweis ohne Reload erscheint sobald der letzte Credit verbraucht wird.
 *
 * Override: yourtheme/bw-credits-booking/balance/states.php
 *
 * @var string $state           'has' | 'empty'
 * @var string $label
 * @var string $number          fertiges <span data-bw-balance>…</span>-Markup
 * @var string $empty_text
 * @var string $empty_link_html bereits fertiges HTML (Link) oder leer, unescaped angehängt
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;
?>
<span class="bw-balance-state" data-bw-balance-wrap data-bw-state="<?php echo esc_attr($state); ?>">
    <span data-bw-has-wrap><?php echo esc_html($label); ?> <strong><?php echo $number; ?></strong></span>
    <span data-bw-empty-wrap class="bw-balance-empty"><?php echo esc_html($empty_text) . $empty_link_html; ?></span>
</span>
