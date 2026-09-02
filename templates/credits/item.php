<?php
/**
 * Guthaben-Details — eine gebündelte Credit-Gruppe
 *
 * Mehrere Credits gleicher Herkunft und gleichen Ablaufdatums stehen
 * gebündelt in einer Zeile, nicht einzeln.
 *
 * Override: yourtheme/bw-credits-booking/credits/item.php
 *
 * @var array  $group         ['count','source','expires_at','status']
 * @var bool   $is_soon       läuft innerhalb der Frist aus den Einstellungen ab
 * @var bool   $is_gone       verbraucht/abgelaufen — nicht mehr verfügbar
 * @var string $source_label
 * @var string $expiry_text
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;

do_action('bw_before_credits_item', $group);
?>
<li class="bw-credits-item<?php echo $is_gone ? ' bw-credits-item--gone' : ''; ?>">
    <span class="bw-credits-amount"><?php echo (int) $group['count']; ?></span>

    <span class="bw-credits-source"><?php echo esc_html($source_label); ?></span>

    <span class="bw-credits-expiry<?php echo ($is_soon && !$is_gone) ? ' bw-credits-expiry--soon' : ''; ?>">
        <?php echo esc_html($expiry_text); ?>
    </span>
</li>
<?php
do_action('bw_after_credits_item', $group);
