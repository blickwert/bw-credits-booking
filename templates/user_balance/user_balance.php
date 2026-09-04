<?php
/**
 * Credit balance — simple display or a prompt to top up
 *
 * Two variants in one file: 'simple' (mode="always") outputs the number
 * or a labeled paragraph. 'states' (mode="empty_only") keeps both states
 * in the markup at once, toggled via data-bw-state, so the note appears
 * without a reload as soon as the last credit is used.
 *
 * Override: yourtheme/bw-credits-booking/user_balance/user_balance.php
 *
 * @var string $variant  'simple' | 'states'
 *
 * When $variant === 'simple':
 * @var string $format  'inline' | 'block'
 * @var string $label   only visible when format=block
 * @var string $number  finished <span data-bw-balance>…</span> markup
 *
 * When $variant === 'states':
 * @var string $state           'has' | 'empty'
 * @var string $label
 * @var string $number          finished <span data-bw-balance>…</span> markup
 * @var string $empty_text
 * @var string $empty_link_html already-finished HTML (link) or empty, appended unescaped
 *
 * @version 0.15.0
 */
if (!defined('ABSPATH')) exit;

if ($variant === 'states') :
    ?>
    <span class="bw-balance-state" data-bw-balance-wrap data-bw-state="<?php echo esc_attr($state); ?>">
        <span data-bw-has-wrap><?php echo esc_html($label); ?> <strong><?php echo $number; ?></strong></span>
        <span data-bw-empty-wrap class="bw-balance-empty"><?php echo esc_html($empty_text) . $empty_link_html; ?></span>
    </span>
    <?php
elseif ($format === 'block') :
    ?>
    <p class="bw-balance"><?php echo esc_html($label); ?> <strong><?php echo $number; ?></strong></p>
    <?php
else :
    echo $number;
endif;
