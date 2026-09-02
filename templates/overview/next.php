<?php
/**
 * Konto-Übersicht — nächster Termin
 *
 * Override: yourtheme/bw-credits-booking/overview/next.php
 *
 * @var array|null $next  ['ts' => int, 'slot_id' => int] oder null wenn nichts gebucht
 * @var string     $empty_text
 * @version 0.14.0
 */
if (!defined('ABSPATH')) exit;

if ($next === null) : ?>
    <p class="bw-credits-overview__next bw-credits-overview__next--none"><?php echo esc_html($empty_text); ?></p>
<?php else : ?>
    <div class="bw-credits-overview__next">
        <span class="bw-credits-overview__next-label"><?php echo esc_html(bw_text('overview.next.label')); ?></span>
        <a class="bw-credits-overview__next-title" href="<?php echo esc_url(get_permalink($next['slot_id'])); ?>">
            <?php echo esc_html(get_the_title($next['slot_id'])); ?>
        </a>
        <span class="bw-credits-overview__next-time">
            <?php echo esc_html(wp_date('l, j. F, H:i', $next['ts'])); ?>
        </span>

        <?php echo BW_View_Access::render(['course_id' => $next['slot_id'], 'title' => '']); ?>
    </div>
<?php endif;
