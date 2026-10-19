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
    const OPT_SPECIAL = 'bw_newsletter_special_product';
    const OPT_COUPON  = 'bw_newsletter_coupon';
    const SESSION_KEY = 'bw_newsletter_auto_coupon';

    /** Addresses handled in this request (account creation and order can both fire at the checkout). */
    private static $handled = [];

    public static function init() {
        add_action('woocommerce_register_form', [__CLASS__, 'render_checkbox'], 20);
        add_action('woocommerce_after_order_notes', [__CLASS__, 'render_checkbox']);
        add_action('woocommerce_created_customer', [__CLASS__, 'on_customer_created'], 20, 2);
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'on_order_processed'], 20, 3);

        // Offers around the Beginner Special (only while the opt-in is switched on)
        add_action('woocommerce_before_cart', [__CLASS__, 'render_swap_notice']);
        add_action('woocommerce_before_checkout_form', [__CLASS__, 'render_swap_notice'], 5);
        add_action('woocommerce_checkout_update_order_review', [__CLASS__, 'sync_coupon']);
        add_action('woocommerce_single_product_summary', [__CLASS__, 'render_product_hint'], 25);
    }

    /* ---------------------------------------------------------
     * Beginner Special: offers in the cart, at the checkout, on the product page
     * --------------------------------------------------------- */

    /** The Beginner Special in the current language (WPML), 0 if it is not set up. */
    public static function special_id(): int {
        $id = (int) get_option(self::OPT_SPECIAL, 159);
        if ($id <= 0) return 0;
        if (has_filter('wpml_object_id')) {
            $translated = apply_filters('wpml_object_id', $id, 'product', true);
            if ($translated) $id = (int) $translated;
        }
        return get_post_type($id) === 'product' ? $id : 0;
    }

    /** The special and its translations. */
    private static function special_ids(): array {
        $id = (int) get_option(self::OPT_SPECIAL, 159);
        return $id > 0 ? BW_Purchase_Limit::product_ids($id) : [];
    }

    public static function coupon_code(): string {
        return trim((string) get_option(self::OPT_COUPON, 'NAMASTE40'));
    }

    /**
     * What the cart holds, from a list of its product ids: has_special, other_credit.
     *
     * @param int[]    $cart_ids   product ids in the cart
     * @param int[]    $special    ids of the special (all languages)
     * @param callable $is_credit  product id => bool
     */
    public static function cart_state(array $cart_ids, array $special, callable $is_credit): array {
        $state = ['has_special' => false, 'other_credit' => false];
        foreach ($cart_ids as $id) {
            $id = (int) $id;
            if (in_array($id, $special, true)) {
                $state['has_special'] = true;
            } elseif ($is_credit($id)) {
                $state['other_credit'] = true;
            }
        }
        return $state;
    }

    private static function current_cart_state(): array {
        if (!function_exists('WC') || !WC()->cart) return ['has_special' => false, 'other_credit' => false];
        $ids = [];
        foreach (WC()->cart->get_cart() as $item) $ids[] = (int) ($item['product_id'] ?? 0);
        return self::cart_state($ids, self::special_ids(), ['BW_Credit_Checkout', 'is_credit_product']);
    }

    /** The logged-in customer already bought the special (guests are checked at the checkout). */
    private static function already_bought_special(): bool {
        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) return false;
        $user = get_userdata($user_id);
        return BW_Purchase_Limit::has_bought(self::special_ids(), $user_id, $user ? strtolower((string) $user->user_email) : '');
    }

    /** "Swap to the Beginner Special" while another credit product is in the cart. */
    public static function render_swap_notice() {
        if (!self::enabled()) return;
        $special = self::special_id();
        if (!$special || !function_exists('wc_get_checkout_url')) return;
        $state = self::current_cart_state();
        if (!$state['other_credit'] || $state['has_special'] || self::already_bought_special()) return;

        $url = add_query_arg('add-to-cart', $special, wc_get_checkout_url());
        echo '<div class="woocommerce-info bw-newsletter-offer">';
        echo esc_html(bw_text('newsletter.swap_notice')) . ' ';
        echo '<a class="button wp-element-button" href="' . esc_url($url) . '">' . esc_html(bw_text('newsletter.swap_button')) . '</a>';
        echo '</div>';
    }

    /** Ticking the newsletter box with the special in the cart applies the code; unticking takes it off again. */
    public static function sync_coupon($post_data = '') {
        if (!self::enabled() || !function_exists('WC') || !WC()->cart) return;
        $code = self::coupon_code();
        if ($code === '') return;

        parse_str((string) $post_data, $data);
        $wants = !empty($data[self::FIELD]) && self::current_cart_state()['has_special'];
        $cart  = WC()->cart;

        if ($wants && !$cart->has_discount($code)) {
            if ($cart->apply_coupon($code) && WC()->session) WC()->session->set(self::SESSION_KEY, 1);
        } elseif (!$wants && $cart->has_discount($code) && WC()->session && WC()->session->get(self::SESSION_KEY)) {
            $cart->remove_coupon($code);
            WC()->session->set(self::SESSION_KEY, 0);
        }
    }

    /** One line under the price of the Beginner Special. */
    public static function render_product_hint() {
        if (!self::enabled() || !function_exists('get_the_ID')) return;
        $id = (int) get_the_ID();
        if (!$id || !in_array($id, self::special_ids(), true) || self::already_bought_special()) return;

        echo '<p class="bw-newsletter-hint">' . esc_html(bw_text('newsletter.product_hint')) . ' ';
        echo '<a href="' . esc_url(self::newsletter_url()) . '">' . esc_html(bw_text('newsletter.product_link')) . '</a></p>';
    }

    /** The newsletter page (slug "newsletter") in the current language. */
    public static function newsletter_url(): string {
        $page = get_page_by_path('newsletter');
        if ($page) {
            $id = (int) $page->ID;
            if (has_filter('wpml_object_id')) {
                $translated = apply_filters('wpml_object_id', $id, 'page', true);
                if ($translated) $id = (int) $translated;
            }
            $url = get_permalink($id);
            if ($url) return $url;
        }
        return home_url('/newsletter/');
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
        $special = current_filter() === 'woocommerce_after_order_notes' && self::current_cart_state()['has_special'];
        $label   = $special ? 'newsletter.optin_label_special' : 'newsletter.optin_label';
        echo '<p class="form-row bw-newsletter-optin' . ($special ? ' update_totals_on_change' : '') . '"><label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
        printf('<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="%s" value="1"> ', esc_attr(self::FIELD));
        echo '<span>' . esc_html(bw_text($label));
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
