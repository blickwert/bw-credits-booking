<?php
if (!defined('ABSPATH')) exit;

/* =========================================================
 * ACF: booked_count – readonly + disabled im Admin
 * ========================================================= */

add_filter('acf/prepare_field/name=booked_count', function ($field) {
    $field['readonly']     = 1;
    $field['disabled']     = 1;
    $field['instructions'] = ($field['instructions'] ?? '') . ' (Systemfeld – wird automatisch berechnet)';
    return $field;
});

/* =========================================================
 * WooCommerce: Credit Amount + Valid Days Produktfelder
 * ========================================================= */

add_action('woocommerce_product_options_general_product_data', function () {
    echo '<div class="options_group">';

    woocommerce_wp_text_input([
        'id'                => '_bw_credit_amount',
        'label'             => 'Credit Amount',
        'description'       => 'Wie viele Credits dieses Produkt auflädt (z.B. 12 für 10er Block).',
        'type'              => 'number',
        'custom_attributes' => ['min' => '0', 'step' => '1'],
        'desc_tip'          => true,
    ]);

    woocommerce_wp_text_input([
        'id'                => '_bw_credit_valid_days',
        'label'             => 'Valid Days',
        'description'       => 'Gültigkeit in Tagen (0 oder leer = unlimitiert).',
        'type'              => 'number',
        'custom_attributes' => ['min' => '0', 'step' => '1'],
        'desc_tip'          => true,
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
});

/* =========================================================
 * course_slot: Auto-Titel beim Speichern
 * post_title = "23.2.26 17:00 – Hatha Yoga – German"
 * ========================================================= */

add_action('acf/save_post', function ($post_id) {
    if (get_post_type($post_id) !== 'course_slot') return;

    static $running = [];
    if (!empty($running[$post_id])) return;
    $running[$post_id] = true;

    $start_datetime = get_field('start_datetime', $post_id);
    if (!$start_datetime) { $running[$post_id] = false; return; }

    $timestamp = strtotime($start_datetime);
    if (!$timestamp) { $running[$post_id] = false; return; }

    $type_terms  = get_the_terms($post_id, 'course_type');
    $lang_terms  = get_the_terms($post_id, 'course_lang');
    $course_type = (!empty($type_terms) && !is_wp_error($type_terms)) ? $type_terms[0]->name : '';
    $course_lang = (!empty($lang_terms) && !is_wp_error($lang_terms)) ? $lang_terms[0]->name : '';

    wp_update_post([
        'ID'         => $post_id,
        'post_title' => sprintf('%s – %s – %s', date('j.n.y H:i', $timestamp), $course_type, $course_lang),
        'post_name'  => 'course-slot-' . $post_id,
    ]);

    $running[$post_id] = false;
}, 20);

/* =========================================================
 * course_slot: Admin-Columns definieren
 * ========================================================= */

add_filter('manage_edit-course_slot_columns', function ($columns) {
    $new = [
        'cb'                => $columns['cb'] ?? '',
        'title'             => __('Title'),
        'bw_start_datetime' => __('Start'),
        'bw_course_level'   => __('Level'),
        'bw_course_type'    => __('Type'),
        'bw_course_lang'    => __('Language'),
    ];
    foreach ($columns as $key => $label) {
        if (!isset($new[$key]) && $key !== 'cb' && $key !== 'title') {
            $new[$key] = $label;
        }
    }
    return $new;
}, 20);

add_action('manage_course_slot_posts_custom_column', function ($column, $post_id) {
    if ($column === 'bw_start_datetime') {
        $v  = get_field('start_datetime', $post_id);
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
 * course_slot: Columns sortierbar machen
 * ========================================================= */

add_filter('manage_edit-course_slot_sortable_columns', function ($sortable) {
    $sortable['title']             = 'title';
    $sortable['bw_start_datetime'] = 'bw_start_datetime';
    $sortable['bw_course_level']   = 'bw_course_level';
    $sortable['bw_course_type']    = 'bw_course_type';
    $sortable['bw_course_lang']    = 'bw_course_lang';
    return $sortable;
});

// ACF meta sort für start_datetime
add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'course_slot') return;
    if ($query->get('orderby') === 'bw_start_datetime') {
        $query->set('meta_key', 'start_datetime');
        $query->set('orderby', 'meta_value');
    }
});

// Taxonomy-Sort via SQL JOIN (term name)
add_filter('posts_clauses', function ($clauses, $query) {
    if (!is_admin() || !$query->is_main_query()) return $clauses;
    if ($query->get('post_type') !== 'course_slot') return $clauses;

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
