<?php
/**
 * Terminliste — Filterformular
 *
 * Override: yourtheme/bw-credits-booking/course-list/filter.php
 *
 * @var array $available  taxonomy => ['label' => string, 'terms' => WP_Term[]]
 * @var array $selected   taxonomy => gewählter Term-Slug
 * @var array $hidden     Query-Parameter => Wert, die als hidden fields erhalten bleiben
 * @var string $reset_url leer wenn kein Filter aktiv ist
 * @version 0.13.0
 */
if (!defined('ABSPATH')) exit;
?>
<form class="bw-course-filter" method="get">
    <?php foreach ($hidden as $key => $value) : ?>
        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
    <?php endforeach; ?>

    <?php foreach ($available as $taxonomy => $data) :
        $param = 'bw_' . str_replace('course_', '', $taxonomy);
        $value = $selected[$taxonomy] ?? '';
    ?>
        <label class="bw-course-filter__field">
            <span><?php echo esc_html($data['label']); ?></span>
            <select name="<?php echo esc_attr($param); ?>">
                <option value=""><?php echo esc_html(bw_text('course_list.filter.all')); ?></option>
                <?php foreach ($data['terms'] as $term) : ?>
                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($value, $term->slug); ?>>
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endforeach; ?>

    <button type="submit" class="bw-bwallet-btn"><?php echo esc_html(bw_text('course_list.filter.submit')); ?></button>

    <?php if ($reset_url !== '') : ?>
        <a class="bw-course-filter__reset" href="<?php echo esc_url($reset_url); ?>">
            <?php echo esc_html(bw_text('course_list.filter.reset')); ?>
        </a>
    <?php endif; ?>
</form>
