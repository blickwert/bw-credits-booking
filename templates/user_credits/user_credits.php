<?php
/**
 * [bw_credits_user_credits] — credit balance in detail
 *
 * Multiple credits with the same origin and expiry date are grouped
 * into one row, not shown individually.
 *
 * Override: yourtheme/bw-credits-booking/user_credits/user_credits.php
 *
 * @var array  $items    empty = $empty_message is shown
 * @var string $empty_message
 *
 * Per entry in $items:
 *   'group'        ['count','source','expires_at','status']
 *   'is_soon'      expires within the threshold from the settings
 *   'is_gone'      used/expired — no longer available
 *   'source_label'
 *   'expiry_text'
 *
 * @version 0.15.0
 */
if (!defined('ABSPATH')) exit;

if (empty($items)) : ?>
    <p class="bw-credits-empty"><?php echo esc_html($empty_message); ?></p>
<?php else : ?>
    <ul class="bw-credits-list">
        <?php foreach ($items as $item) :
            $group    = $item['group'];
            $is_soon  = $item['is_soon'];
            $is_gone  = $item['is_gone'];
            do_action('bw_before_credits_item', $group);
        ?>
            <li class="bw-credits-item<?php echo $is_gone ? ' bw-credits-item--gone' : ''; ?>">
                <span class="bw-credits-amount"><?php echo (int) $group['count']; ?></span>

                <span class="bw-credits-source"><?php echo esc_html($item['source_label']); ?></span>

                <span class="bw-credits-expiry<?php echo ($is_soon && !$is_gone) ? ' bw-credits-expiry--soon' : ''; ?>">
                    <?php echo esc_html($item['expiry_text']); ?>
                </span>
            </li>
            <?php do_action('bw_after_credits_item', $group); ?>
        <?php endforeach; ?>
    </ul>
<?php endif;
