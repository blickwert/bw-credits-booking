<?php
/**
 * [bw_credits_view_overview] — account overview
 *
 * Credit balance, upcoming sessions (with availability and a
 * book/cancel button, reused from the session list), and entry-point
 * links, all in one block. Intended for the WooCommerce account dashboard.
 *
 * Override: yourtheme/bw-credits-booking/view_overview/view_overview.php
 *
 * @var bool   $show_balance
 * @var bool   $show_next       toggles the "Upcoming courses" block
 * @var bool   $show_links
 * @var int    $available       only relevant when $show_balance
 * @var string $balance_label   only relevant when $show_balance
 * @var int    $next_limit      number of sessions in the "Upcoming courses" block
 * @var array  $links           [['url' => string, 'label' => string], …]
 * @version 0.15.0
 */
if (!defined('ABSPATH')) exit;
?>
<div class="bw-credits-overview">
    <?php if ($show_balance) : ?>
        <div class="bw-credits-overview__balance">
            <span class="bw-credits-overview__count" data-bw-balance><?php echo (int) $available; ?></span>
            <span class="bw-credits-overview__label"><?php echo esc_html($balance_label); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($show_next) : ?>
        <div class="bw-credits-overview__next">
            <span class="bw-credits-overview__next-label"><?php echo esc_html(bw_text('overview.upcoming.label')); ?></span>
            <?php echo BW_Course_List::render(['limit' => $next_limit, 'show_filter' => 'false']); ?>
        </div>
    <?php endif; ?>

    <?php if ($show_links && !empty($links)) : ?>
        <ul class="bw-credits-overview__links">
            <?php foreach ($links as $link) : ?>
                <li><a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
