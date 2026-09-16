<?php
if (!defined('ABSPATH')) exit;

/**
 * Per-customer email language: a persistent preference instead of
 * deriving the language from the page the customer happens to be
 * browsing at the moment of a booking, or from the course session's
 * own WPML language — both of which produced inconsistent results for
 * emails sent later (reminders, status changes). Every booking already
 * requires a logged-in account, so a simple user-meta field covers it
 * without needing a per-booking snapshot.
 *
 * Only relevant with WPML and more than one active language — without
 * that, there's nothing to choose and the field doesn't render.
 */

class BW_Email_Language {

    const META_KEY = '_bw_email_language';

    public static function init() {
        add_action('woocommerce_account_dashboard', [__CLASS__, 'render_dashboard_field'], 25);
        add_action('admin_post_bw_save_email_language', [__CLASS__, 'handle_save_from_dashboard']);

        add_action('woocommerce_edit_account_form', [__CLASS__, 'render_field_for_current_user']);
        add_action('woocommerce_save_account_details', [__CLASS__, 'save_from_account_form']);

        // Capture the language active at registration as the initial
        // preference, so it's already set correctly from day one instead
        // of only ever taking effect once a customer proactively visits
        // their account and saves a choice.
        add_action('user_register', [__CLASS__, 'save_language_on_registration']);
    }

    /* =========================================================
     * WPML languages
     * ========================================================= */

    /** code => ['native_name' => ..., 'default_locale' => ..., ...], or [] without WPML / with only one language. */
    public static function active_languages(): array {
        if (!has_filter('wpml_active_languages')) return [];

        $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);
        return is_array($languages) && count($languages) > 1 ? $languages : [];
    }

    /** code => default_locale (e.g. 'de' => 'de_DE'), for switch_to_locale() in BW_Emails. */
    public static function locale_map(): array {
        $map = [];
        foreach (self::active_languages() as $code => $language) {
            if (!empty($language['default_locale'])) {
                $map[$code] = $language['default_locale'];
            }
        }
        return $map;
    }

    /* =========================================================
     * Preference
     * ========================================================= */

    /**
     * The customer's saved preference, or — if never set — the language
     * active right now (a sensible default for the dropdown, not
     * persisted until the customer actually saves). Empty string
     * without WPML/multiple languages, so callers can treat that as
     * "nothing to resolve".
     */
    public static function get_user_language(int $user_id): string {
        $languages = self::active_languages();
        if (!$languages) return '';

        $saved = (string) get_user_meta($user_id, self::META_KEY, true);
        if ($saved !== '' && isset($languages[$saved])) return $saved;

        $current = (string) apply_filters('wpml_current_language', null);
        return isset($languages[$current]) ? $current : '';
    }

    public static function save_user_language(int $user_id, string $code): void {
        if (isset(self::active_languages()[$code])) {
            update_user_meta($user_id, self::META_KEY, $code);
        }
    }

    public static function save_language_on_registration(int $user_id): void {
        $current = (string) apply_filters('wpml_current_language', null);
        self::save_user_language($user_id, $current);
    }

    /* =========================================================
     * Field rendering
     * ========================================================= */

    private static function render_field(int $user_id) {
        $languages = self::active_languages();
        if (!$languages) return;

        $current = self::get_user_language($user_id);
        ?>
        <p class="form-row form-row-wide">
            <label for="bw_email_language"><?php esc_html_e('Language', 'bw-credits-booking'); ?></label>
            <select name="<?php echo esc_attr(self::META_KEY); ?>" id="bw_email_language" class="woocommerce-Input woocommerce-Input--select">
                <?php foreach ($languages as $code => $language) : ?>
                    <option value="<?php echo esc_attr($code); ?>" <?php selected($current, $code); ?>>
                        <?php echo esc_html($language['native_name'] ?? $code); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description">
            <?php esc_html_e('The language your booking confirmations, reminders, and other emails are sent in.', 'bw-credits-booking'); ?>
        </p>
        <?php
    }

    /* --- Dashboard tab: own small form + admin-post handler --- */

    public static function render_dashboard_field() {
        if (!is_user_logged_in() || !self::active_languages()) return;

        $user_id = get_current_user_id();
        self::notice();
        ?>
        <h2><?php esc_html_e('Language', 'bw-credits-booking'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="bw_save_email_language">
            <?php wp_nonce_field('bw_save_email_language'); ?>
            <?php self::render_field($user_id); ?>
            <button type="submit" class="woocommerce-Button button">
                <?php esc_html_e('Save', 'bw-credits-booking'); ?>
            </button>
        </form>
        <?php
    }

    public static function handle_save_from_dashboard() {
        if (!is_user_logged_in()) {
            wp_die(__('Not authorized.', 'bw-credits-booking'));
        }
        check_admin_referer('bw_save_email_language');

        $user_id = get_current_user_id();
        $code    = isset($_POST[self::META_KEY]) ? sanitize_text_field(wp_unslash($_POST[self::META_KEY])) : '';

        if (isset(self::active_languages()[$code])) {
            self::save_user_language($user_id, $code);
            self::redirect('ok:' . __('Language saved.', 'bw-credits-booking'));
        }

        self::redirect('err:' . __('Unknown language.', 'bw-credits-booking'));
    }

    /**
     * Own redirect/notice pair instead of wc_add_notice() — this handler
     * runs via admin-post.php, where is_admin() is true, so WooCommerce
     * skips loading its frontend-only function files (including
     * wc_add_notice(), and possibly others) even though the request
     * originated from a frontend form. Same ok:/err: query-string
     * pattern already used elsewhere in this plugin (see
     * BW_Emails::redirect()/notice()). Redirects back via the referer
     * (the dashboard page the form was submitted from) rather than
     * wc_get_account_endpoint_url(), to stay free of any WooCommerce
     * frontend-only function in this code path.
     */
    private static function redirect(string $notice) {
        $back = wp_get_referer() ?: home_url('/');
        wp_safe_redirect(add_query_arg('bw_notice', rawurlencode($notice), $back));
        exit;
    }

    private static function notice() {
        if (empty($_GET['bw_notice'])) return;

        $raw     = sanitize_text_field(wp_unslash($_GET['bw_notice']));
        $is_err  = strpos($raw, 'err:') === 0;
        $message = substr($raw, 4);

        printf(
            '<div class="woocommerce-%s">%s</div>',
            $is_err ? 'error' : 'message',
            esc_html($message)
        );
    }

    /* --- Edit-account form: WooCommerce's own form/save cycle --- */

    public static function render_field_for_current_user() {
        if (!self::active_languages()) return;
        self::render_field(get_current_user_id());
    }

    public static function save_from_account_form(int $user_id) {
        $code = isset($_POST[self::META_KEY]) ? sanitize_text_field(wp_unslash($_POST[self::META_KEY])) : '';
        if ($code !== '') {
            self::save_user_language($user_id, $code);
        }
    }
}

BW_Email_Language::init();
