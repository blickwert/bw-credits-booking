<?php
if (!defined('ABSPATH')) exit;

/**
 * Product feature list: 3 optional title+description pairs on the
 * WooCommerce Product edit screen, for a small marketing bullet list
 * (e.g. "Includes: mat & towel"). Gated behind a global on/off switch
 * under BW Credits → Settings — when off, neither the product edit
 * fields nor the shortcode show anything, though saved values are kept.
 *
 * No automatic frontend placement by design: pull individual fields in
 * via the [bw_product_feature] shortcode wherever wanted, or read the
 * meta directly (get_post_meta()) from a theme template.
 */

class BW_Product_Features {

    const OPT_ENABLED = 'bw_product_features_enabled';
    const SLOTS        = [1, 2, 3];

    public static function init() {
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'render_fields']);
        add_action('woocommerce_admin_process_product_object', [__CLASS__, 'save_fields']);
        add_shortcode('bw_product_feature', [__CLASS__, 'shortcode']);

        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    private static function meta_key(int $n, string $field): string {
        return '_bw_feature_' . $n . '_' . $field;
    }

    /* =========================================================
     * Global switch
     * ========================================================= */

    public static function register_settings() {
        register_setting('bw_credits_settings', self::OPT_ENABLED, [
            'type'              => 'boolean',
            'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
        ]);

        add_settings_field(
            self::OPT_ENABLED,
            __('Product feature list', 'bw-credits-booking'),
            [__CLASS__, 'field_enabled'],
            BW_Settings::MENU_SLUG,
            'bw_general'
        );
    }

    public static function is_enabled(): bool {
        return (bool) get_option(self::OPT_ENABLED, false);
    }

    public static function field_enabled() {
        ?>
        <label>
            <input type="hidden" name="<?php echo esc_attr(self::OPT_ENABLED); ?>" value="0">
            <input type="checkbox" name="<?php echo esc_attr(self::OPT_ENABLED); ?>"
                   value="1" <?php checked(self::is_enabled()); ?>>
            <?php esc_html_e('Enable the product feature list (fields below on each product, plus the [bw_product_feature] shortcode)', 'bw-credits-booking'); ?>
        </label>
        <?php
    }

    /* =========================================================
     * Product edit screen
     * ========================================================= */

    public static function render_fields() {
        if (!self::is_enabled()) return;

        echo '<div class="options_group">';
        echo '<h4 style="padding-left:12px;">' . esc_html__('Feature List', 'bw-credits-booking') . '</h4>';

        foreach (self::SLOTS as $n) {
            woocommerce_wp_text_input([
                'id'          => self::meta_key($n, 'title'),
                /* translators: %d: feature slot number (1-3) */
                'label'       => sprintf(__('Feature %d Title', 'bw-credits-booking'), $n),
            ]);

            woocommerce_wp_textarea_input([
                'id'          => self::meta_key($n, 'desc'),
                /* translators: %d: feature slot number (1-3) */
                'label'       => sprintf(__('Feature %d Description', 'bw-credits-booking'), $n),
                'rows'        => 2,
            ]);
        }

        echo '</div>';
    }

    public static function save_fields($product) {
        foreach (self::SLOTS as $n) {
            $title_key = self::meta_key($n, 'title');
            if (isset($_POST[$title_key])) {
                $product->update_meta_data($title_key, sanitize_text_field(wp_unslash($_POST[$title_key])));
            }

            $desc_key = self::meta_key($n, 'desc');
            if (isset($_POST[$desc_key])) {
                $product->update_meta_data($desc_key, sanitize_textarea_field(wp_unslash($_POST[$desc_key])));
            }
        }
    }

    /* =========================================================
     * Shortcode
     * ========================================================= */

    /** [bw_product_feature n="1" field="title"] — field: title|desc. product_id defaults to the current post. */
    public static function shortcode($atts): string {
        if (!self::is_enabled()) return '';

        $atts = shortcode_atts([
            'n'          => '',
            'field'      => '',
            'product_id' => 0,
        ], $atts, 'bw_product_feature');

        $n = (int) $atts['n'];
        if (!in_array($n, self::SLOTS, true)) return '';

        $field = (string) $atts['field'];
        if (!in_array($field, ['title', 'desc'], true)) return '';

        $product_id = (int) $atts['product_id'];
        if ($product_id <= 0) $product_id = (int) get_the_ID();
        if ($product_id <= 0) return '';

        $value = (string) get_post_meta($product_id, self::meta_key($n, $field), true);
        if ($value === '') return '';

        return $field === 'desc' ? nl2br(esc_html($value)) : esc_html($value);
    }
}

BW_Product_Features::init();
