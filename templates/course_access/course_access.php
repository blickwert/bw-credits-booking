<?php
/**
 * Access details box
 *
 * Override: yourtheme/bw-credits-booking/course_access/course_access.php
 *
 * @var string $title  empty = no heading
 * @var string $link   empty = no meeting link present
 * @var string $info   empty = no additional info present
 * @var string $link_label
 * @version 0.15.0
 */
if (!defined('ABSPATH')) exit;
?>
<div class="bw-access">
    <?php if ($title !== '') : ?>
        <h3 class="bw-access__title"><?php echo esc_html($title); ?></h3>
    <?php endif; ?>

    <?php if ($link !== '') : ?>
        <p class="bw-access__link">
            <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener">
                <?php echo esc_html($link_label); ?>
            </a>
        </p>
    <?php endif; ?>

    <?php if ($info !== '') : ?>
        <div class="bw-access__info"><?php echo nl2br(esc_html($info)); ?></div>
    <?php endif; ?>
</div>
