<?php
if (!defined('ABSPATH')) exit;

/**
 * Optional newsletter opt-in at the account registration and at the checkout.
 *
 * Off by default (Settings › Newsletter opt-in). When on, an unchecked checkbox is shown; if it is
 * ticked, the address is subscribed through the Brevo plugin's own double opt-in: a record with an
 * activation code is stored and the DOI mail of the newsletter form (language form) is sent. The
 * contact only lands in the list once the link in that mail was clicked — the same path as the
 * newsletter form on the website, including its welcome mail with the discount code.
 */
class BW_Newsletter {

    const OPT_ENABLED = 'bw_newsletter_optin_enabled';
    const OPT_FORM_DE = 'bw_newsletter_form_de';
    const OPT_FORM_EN = 'bw_newsletter_form_en';
    const FIELD       = 'bw_newsletter_optin';

    /** Addresses handled in this request (account creation and order can both fire at the checkout). */
    private static $handled = [];

    public static function init() {
        add_action('woocommerce_register_form', [__CLASS__, 'render_checkbox'], 20);
        add_action('woocommerce_after_order_notes', [__CLASS__, 'render_checkbox']);
        add_action('woocommerce_created_customer', [__CLASS__, 'on_customer_created'], 20, 2);
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'on_order_processed'], 20, 3);
    }

    public static function enabled(): bool {
        return (int) get_option(self::OPT_ENABLED, 0) === 1;
    }

    /** Form id of the Brevo plugin's newsletter form for a language ('de' or anything else = English). */
    public static function form_id_for(string $lang): int {
        return $lang === 'de'
            ? max(0, (int) get_option(self::OPT_FORM_DE, 4))
            : max(0, (int) get_option(self::OPT_FORM_EN, 5));
    }

    public static function current_language(): string {
        $lang = apply_filters('wpml_current_language', null);
        return is_string($lang) && $lang !== '' ? $lang : substr((string) get_locale(), 0, 2);
    }

    public static function render_checkbox() {
        if (!self::enabled()) return;
        $privacy = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
        echo '<p class="form-row bw-newsletter-optin"><label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
        printf('<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="%s" value="1"> ', esc_attr(self::FIELD));
        echo '<span>' . esc_html(bw_text('newsletter.optin_label'));
        if ($privacy !== '') {
            echo ' <a href="' . esc_url($privacy) . '" target="_blank" rel="noopener">' . esc_html(bw_text('newsletter.optin_privacy')) . '</a>';
        }
        echo '</span></label></p>';
    }

    public static function posted(): bool {
        return !empty($_POST[self::FIELD]); // phpcs:ignore WordPress.Security.NonceVerification -- the WooCommerce form handlers verified the nonce
    }

    public static function on_customer_created($customer_id, $data = []) {
        if (!self::enabled() || !self::posted()) return;
        $email = is_array($data) && !empty($data['user_email']) ? (string) $data['user_email'] : '';
        $first = isset($_POST['billing_first_name']) ? sanitize_text_field(wp_unslash($_POST['billing_first_name'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        self::subscribe($email, $first, self::current_language());
    }

    public static function on_order_processed($order_id, $posted = [], $order = null) {
        if (!self::enabled() || !self::posted()) return;
        if (!$order && function_exists('wc_get_order')) $order = wc_get_order($order_id);
        if (!$order) return;
        $time = gmdate('Y-m-d H:i:s');
        $order->update_meta_data('_bw_newsletter_optin_at', $time);
        $order->save();
        self::subscribe((string) $order->get_billing_email(), (string) $order->get_billing_first_name(), self::current_language());
    }

    /** Whether the address already sits in one of the newsletter lists (then no second mail is sent). */
    private static function already_subscribed(string $email, array $lists): bool {
        if (!class_exists('SendinblueApiClient')) return false;
        try {
            $client  = new SendinblueApiClient();
            $contact = $client->get('/contacts/' . rawurlencode($email));
            if ($client->getLastResponseCode() !== 200 || !is_array($contact)) return false;
            if (!empty($contact['emailBlacklisted'])) return false;
            return (bool) array_intersect(array_map('intval', $lists), array_map('intval', (array) ($contact['listIds'] ?? [])));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Starts the double opt-in. Returns true if the confirmation mail was sent. */
    public static function subscribe(string $email, string $first_name, string $lang): bool {
        $email = sanitize_email($email);
        if (!is_email($email) || isset(self::$handled[strtolower($email)])) return false;
        self::$handled[strtolower($email)] = true;

        if (!class_exists('SIB_Forms') || !class_exists('SIB_Manager') || !class_exists('SIB_API_Manager') || empty(SIB_Manager::$instance)) return false;
        $form_id = self::form_id_for($lang);
        $form    = $form_id ? SIB_Forms::getForm($form_id) : null;
        if (!is_array($form) || empty($form) || (string) ($form['isDopt'] ?? '') !== '1') return false;

        $lists = maybe_unserialize($form['listID'] ?? []);
        $lists = is_array($lists) ? $lists : [];
        if (self::already_subscribed($email, $lists)) return false;

        $info = [];
        if ($first_name !== '') $info['VORNAME'] = $first_name;
        $code = SIB_Manager::$instance->create_activate_code($email, $info, $form_id, $lists, $form['redirectInEmail'] ?? '');
        SIB_API_Manager::send_comfirm_email($email, 'double-optin', $form['templateID'] ?? '-1', $info, $code);
        return true;
    }
}

BW_Newsletter::init();
