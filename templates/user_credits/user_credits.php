<?php
/**
 * [bw_credits_user_credits] — Guthaben im Detail
 *
 * Mehrere Credits gleicher Herkunft und gleichen Ablaufdatums stehen
 * gebündelt in einer Zeile, nicht einzeln.
 *
 * Override: yourtheme/bw-credits-booking/user_credits/user_credits.php
 *
 * @var array  $items    leer = $empty_message wird gezeigt
 * @var string $empty_message
 *
 * Je Eintrag in $items:
 *   'group'        ['count','source','expires_at','status']
 *   'is_soon'      läuft innerhalb der Frist aus den Einstellungen ab
 *   'is_gone'      verbraucht/abgelaufen — nicht mehr verfügbar
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
