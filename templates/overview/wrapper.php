<?php
/**
 * Konto-Übersicht — Rahmen
 *
 * Bindet je nach $atts die Teil-Templates balance.php, next.php und
 * links.php ein.
 *
 * Override: yourtheme/bw-credits-booking/overview/wrapper.php
 *
 * @var array $atts  aufgelöste Shortcode-Attribute (show_balance, show_next, show_links, list_url)
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;
?>
<div class="bw-credits-overview">
    <?php
    if (filter_var($atts['show_balance'], FILTER_VALIDATE_BOOLEAN)) {
        BW_View_Overview::render_balance(get_current_user_id());
    }

    if (filter_var($atts['show_next'], FILTER_VALIDATE_BOOLEAN)) {
        BW_View_Overview::render_next(get_current_user_id());
    }

    if (filter_var($atts['show_links'], FILTER_VALIDATE_BOOLEAN)) {
        BW_View_Overview::render_links($atts['list_url']);
    }
    ?>
</div>
