<?php
/**
 * Guthaben — einfache Anzeige oder Aufforderung zum Nachkaufen
 *
 * Zwei Varianten in einer Datei: 'simple' (mode="always") gibt die Zahl
 * oder einen beschrifteten Absatz aus. 'states' (mode="empty_only") hält
 * beide Zustände gleichzeitig im Markup, umgeschaltet über data-bw-state,
 * damit der Hinweis ohne Reload erscheint sobald der letzte Credit
 * verbraucht wird.
 *
 * Override: yourtheme/bw-credits-booking/user_balance/user_balance.php
 *
 * @var string $variant  'simple' | 'states'
 *
 * Bei $variant === 'simple':
 * @var string $format  'inline' | 'block'
 * @var string $label   nur bei format=block sichtbar
 * @var string $number  fertiges <span data-bw-balance>…</span>-Markup
 *
 * Bei $variant === 'states':
 * @var string $state           'has' | 'empty'
 * @var string $label
 * @var string $number          fertiges <span data-bw-balance>…</span>-Markup
 * @var string $empty_text
 * @var string $empty_link_html bereits fertiges HTML (Link) oder leer, unescaped angehängt
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
