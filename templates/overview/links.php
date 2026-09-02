<?php
/**
 * Konto-Übersicht — Einstiegslinks
 *
 * Override: yourtheme/bw-credits-booking/overview/links.php
 *
 * @var array $links  [['url' => string, 'label' => string], …]
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;
if (empty($links)) return;
?>
<ul class="bw-credits-overview__links">
    <?php foreach ($links as $link) : ?>
        <li><a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a></li>
    <?php endforeach; ?>
</ul>
