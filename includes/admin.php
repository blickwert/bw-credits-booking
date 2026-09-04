<?php
if (!defined('ABSPATH')) exit;

// Post type of the course sessions — configurable under BW Credits → Settings
$bw_slot_pt = BW_Settings::get_slot_post_type();

/* =========================================================
 * ACF: booked_count – readonly + disabled in the admin
 * ========================================================= */

add_filter('acf/prepare_field/name=booked_count', function ($field) {
    $field['readonly']     = 1;
    $field['disabled']     = 1;
    $field['instructions'] = ($field['instructions'] ?? '') . ' ' . __('(system field – calculated automatically)', 'bw-credits-booking');
    return $field;
});

/* =========================================================
 * WooCommerce: Credit Amount + Valid Days product fields
 * ========================================================= */

add_action('woocommerce_product_options_general_product_data', function () {
    echo '<div class="options_group">';

    woocommerce_wp_text_input([
        'id'                => '_bw_credit_amount',
        'label'             => __('Credit Amount', 'bw-credits-booking'),
        'description'       => __('How many credits this product tops up (e.g. 12 for a 10-pack).', 'bw-credits-booking'),
        'type'              => 'number',
        'custom_attributes' => ['min' => '0', 'step' => '1'],
        'desc_tip'          => true,
    ]);

    woocommerce_wp_text_input([
        'id'                => '_bw_credit_valid_days',
        'label'             => __('Valid Days', 'bw-credits-booking'),
        'description'       => __('Validity in days (0 or empty = unlimited).', 'bw-credits-booking'),
        'type'              => 'number',
        'custom_attributes' => ['min' => '0', 'step' => '1'],
        'desc_tip'          => true,
    ]);

    woocommerce_wp_select([
        'id'          => '_bw_credit_source',
        'label'       => __('Credit Source', 'bw-credits-booking'),
        'description' => __('purchase = one-time purchase. membership = membership credits (expire on cancellation).', 'bw-credits-booking'),
        'desc_tip'    => true,
        'options'     => [
            'purchase'   => __('Purchase (one-time)', 'bw-credits-booking'),
            'membership' => __('Membership (monthly)', 'bw-credits-booking'),
        ],
    ]);

    echo '</div>';
});

add_action('woocommerce_admin_process_product_object', function ($product) {
    if (isset($_POST['_bw_credit_amount'])) {
        $product->update_meta_data('_bw_credit_amount', intval($_POST['_bw_credit_amount']));
    }
    if (isset($_POST['_bw_credit_valid_days'])) {
        $product->update_meta_data('_bw_credit_valid_days', intval($_POST['_bw_credit_valid_days']));
    }
    if (isset($_POST['_bw_credit_source'])) {
        $source = in_array($_POST['_bw_credit_source'], ['purchase', 'membership'], true)
                  ? $_POST['_bw_credit_source']
                  : 'purchase';
        $product->update_meta_data('_bw_credit_source', $source);
    }
});

/* =========================================================
 * Course session: auto-title on save
 * post_title = the course type's name, e.g. "Hatha Yoga"
 *
 * No date in the title — the start time is already shown separately in
 * the session list and booking list, so it was redundant in the title.
 * ========================================================= */

add_action('acf/save_post', function ($post_id) use ($bw_slot_pt) {
    if (get_post_type($post_id) !== $bw_slot_pt) return;

    static $running = [];
    if (!empty($running[$post_id])) return;
    $running[$post_id] = true;

    $course_type = bw_cs_first_term($post_id, 'course_type');
    if ($course_type === '') { $running[$post_id] = false; return; }

    $title = apply_filters('bw_slot_title', $course_type, $post_id);

    wp_update_post([
        'ID'         => $post_id,
        'post_title' => $title,
        'post_name'  => $bw_slot_pt . '-' . $post_id,
    ]);

    $running[$post_id] = false;
}, 20);

/* =========================================================
 * Edit course sessions in the Classic Editor
 * The meta boxes (capacity, participants, online access) are designed
 * for it; in the block editor they end up in the bottom panel.
 * ========================================================= */

add_filter('use_block_editor_for_post_type', function ($use_block_editor, $post_type) use ($bw_slot_pt) {
    return $post_type === $bw_slot_pt ? false : $use_block_editor;
}, 10, 2);

/* =========================================================
 * Course session: define admin columns
 * ========================================================= */

add_filter("manage_edit-{$bw_slot_pt}_columns", function ($columns) {
    $new = [
        'cb'                => $columns['cb'] ?? '',
        'title'             => __('Title', 'bw-credits-booking'),
        'bw_start_datetime' => __('Start', 'bw-credits-booking'),
        'bw_course_level'   => __('Level', 'bw-credits-booking'),
        'bw_course_type'    => __('Type', 'bw-credits-booking'),
        'bw_course_lang'    => __('Language', 'bw-credits-booking'),
    ];
    foreach ($columns as $key => $label) {
        if (!isset($new[$key]) && $key !== 'cb' && $key !== 'title') {
            $new[$key] = $label;
        }
    }
    return $new;
}, 20);

add_action("manage_{$bw_slot_pt}_posts_custom_column", function ($column, $post_id) {
    if ($column === 'bw_start_datetime') {
        $v  = get_post_meta($post_id, 'start_datetime', true);
        $ts = $v ? strtotime($v) : 0;
        echo $ts ? esc_html(date('Y-m-d H:i', $ts)) : '—';
        return;
    }
    $tax_map = [
        'bw_course_level' => 'course_level',
        'bw_course_type'  => 'course_type',
        'bw_course_lang'  => 'course_lang',
    ];
    if (isset($tax_map[$column])) {
        echo esc_html(bw_cs_first_term($post_id, $tax_map[$column]) ?: '—');
    }
}, 10, 2);

/* =========================================================
 * Course session: make columns sortable
 * ========================================================= */

add_filter("manage_edit-{$bw_slot_pt}_sortable_columns", function ($sortable) {
    $sortable['title']             = 'title';
    $sortable['bw_start_datetime'] = 'bw_start_datetime';
    $sortable['bw_course_level']   = 'bw_course_level';
    $sortable['bw_course_type']    = 'bw_course_type';
    $sortable['bw_course_lang']    = 'bw_course_lang';
    return $sortable;
});

// Meta sort for start_datetime
add_action('pre_get_posts', function ($query) use ($bw_slot_pt) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== $bw_slot_pt) return;
    if ($query->get('orderby') === 'bw_start_datetime') {
        $query->set('meta_key', 'start_datetime');
        $query->set('orderby', 'meta_value');
    }
});

// Taxonomy-Sort via SQL JOIN (term name)
add_filter('posts_clauses', function ($clauses, $query) use ($bw_slot_pt) {
    if (!is_admin() || !$query->is_main_query()) return $clauses;
    if ($query->get('post_type') !== $bw_slot_pt) return $clauses;

    $tax_map = [
        'bw_course_level' => 'course_level',
        'bw_course_type'  => 'course_type',
        'bw_course_lang'  => 'course_lang',
    ];
    $orderby = $query->get('orderby');
    if (!isset($tax_map[$orderby])) return $clauses;

    global $wpdb;
    $taxonomy = $tax_map[$orderby];
    $order    = in_array(strtoupper($query->get('order') ?: 'ASC'), ['ASC', 'DESC'], true)
                ? strtoupper($query->get('order'))
                : 'ASC';

    $tr = 'tr_' . $taxonomy;
    $tt = 'tt_' . $taxonomy;
    $t  = 't_'  . $taxonomy;

    $clauses['join'] .= "
        LEFT JOIN {$wpdb->term_relationships} AS {$tr}
            ON ({$wpdb->posts}.ID = {$tr}.object_id)
        LEFT JOIN {$wpdb->term_taxonomy} AS {$tt}
            ON ({$tr}.term_taxonomy_id = {$tt}.term_taxonomy_id AND {$tt}.taxonomy = '" . esc_sql($taxonomy) . "')
        LEFT JOIN {$wpdb->terms} AS {$t}
            ON ({$tt}.term_id = {$t}.term_id)
    ";

    if (empty($clauses['groupby'])) {
        $clauses['groupby'] = "{$wpdb->posts}.ID";
    } elseif (!str_contains($clauses['groupby'], "{$wpdb->posts}.ID")) {
        $clauses['groupby'] .= ", {$wpdb->posts}.ID";
    }

    $clauses['orderby'] = "COALESCE({$t}.name, '') {$order}, {$wpdb->posts}.ID {$order}";
    return $clauses;
}, 10, 2);

/* =========================================================
 * Helper
 * ========================================================= */

if (!function_exists('bw_cs_first_term')) {
    function bw_cs_first_term(int $post_id, string $taxonomy): string {
        $terms = get_the_terms($post_id, $taxonomy);
        if (empty($terms) || is_wp_error($terms)) return '';
        return $terms[0]->name ?? '';
    }
}
