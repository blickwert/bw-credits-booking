<?php
if (!defined('ABSPATH')) exit;

/**
 * Email notifications, including the reminder cron and access-details delivery.
 *
 * Access details are event-driven, not schedule-driven:
 *  - The instructor enters the meeting link  → all existing bookings
 *  - Someone books afterwards                → just that one booking
 * The access_sent_at flag prevents duplicate sends in both directions.
 */

class BW_Emails {

    const PAGE          = 'bw-credits-emails';
    const CRON_HOOK     = 'bw_send_reminders';
    const OPT_ADMIN_TO  = 'bw_email_admin_recipient';

    /** Email types: key => [label, description] */
    public static function types(): array {
        return [
            'booking'       => [__('Booking confirmation', 'bw-credits-booking'), __('Sent to the customer right after a successful booking.', 'bw-credits-booking')],
            'cancellation'  => [__('Cancellation confirmation', 'bw-credits-booking'), __('Sent to the customer after a cancellation.', 'bw-credits-booking')],
            'reminder'      => [__('Reminder', 'bw-credits-booking'), __('Before the session starts — timing set in Settings.', 'bw-credits-booking')],
            'access'        => [__('Access details', 'bw-credits-booking'), __('As soon as the meeting link is entered for the session, and immediately for later bookings.', 'bw-credits-booking')],
            'admin_booking' => [__('Admin copy', 'bw-credits-booking'), __('Sent to the address set below for every new booking.', 'bw-credits-booking')],
        ];
    }

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 30);
        add_action('admin_init', [__CLASS__, 'register_settings']);

        add_action('bw_booking_created',   [__CLASS__, 'on_booking_created'], 10, 3);
        add_action('bw_booking_cancelled', [__CLASS__, 'on_booking_cancelled'], 10, 3);

        // Meeting link was added → access details to all participants
        add_action('bw_meeting_link_added', [__CLASS__, 'send_access_for_slot'], 10, 1);
        add_action('admin_post_bw_resend_access', [__CLASS__, 'handle_resend_access']);
        add_action('admin_post_bw_reset_email', [__CLASS__, 'handle_reset_email']);

        add_action(self::CRON_HOOK, [__CLASS__, 'run_reminders']);
        add_action('init', [__CLASS__, 'schedule_cron']);
        add_action('init', [__CLASS__, 'register_wpml_strings'], 20);
    }

    /* =========================================================
     * Options
     * ========================================================= */

    private static function opt_enabled(string $key): string { return 'bw_email_' . $key . '_enabled'; }
    private static function opt_subject(string $key): string { return 'bw_email_' . $key . '_subject'; }
    private static function opt_body(string $key): string    { return 'bw_email_' . $key . '_body'; }

    public static function is_enabled(string $key): bool {
        return (bool) get_option(self::opt_enabled($key), $key === 'admin_booking' ? 0 : 1);
    }

    public static function get_subject(string $key): string {
        $v = (string) get_option(self::opt_subject($key), '');
        return $v !== '' ? $v : (self::defaults()[$key]['subject'] ?? '');
    }

    public static function get_body(string $key): string {
        $v = (string) get_option(self::opt_body($key), '');
        return $v !== '' ? $v : (self::defaults()[$key]['body'] ?? '');
    }

    /**
     * The text actually used when sending: a saved override as-is (still
     * translated via WPML further down in send()), or — for an untouched
     * default — the gettext translation of it, so a German-locale site
     * sends correct German out of the box without any WPML setup. Same
     * split as BW_Text::get(); get_subject()/get_body() above stay raw
     * (English) for the admin editor and the WPML source registration.
     */
    private static function subject_source(string $key): string {
        $v = (string) get_option(self::opt_subject($key), '');
        return $v !== '' ? $v : __(self::defaults()[$key]['subject'] ?? '', 'bw-credits-booking');
    }

    private static function body_source(string $key): string {
        $v = (string) get_option(self::opt_body($key), '');
        return $v !== '' ? $v : __(self::defaults()[$key]['body'] ?? '', 'bw-credits-booking');
    }

    /**
     * Source (English) defaults, one entry per email type. Resolved at
     * runtime through subject_source()/body_source() — gettext for the
     * untouched default, WPML for a saved override — the same split as
     * BW_Text::get(). register_wpml_strings() and render_page() use the
     * raw values here directly (never gettext-translated), matching how
     * BW_Text::catalogue()'s raw defaults are registered with WPML.
     */
    public static function defaults(): array {
        return [
            'booking' => [
                'subject' => 'Booking confirmation: {course_title}',
                'body'    => "Hi {customer_name},\n\n"
                           . "your booking is confirmed:\n\n"
                           . "{course_title}\n{date} at {time}\n\n"
                           . "Credits remaining: {credits_remaining}\n\n"
                           . "Session details: {course_link}\n"
                           . "Manage your bookings here: {account_link}\n\n"
                           . "See you soon!",
            ],
            'cancellation' => [
                'subject' => 'Cancellation: {course_title}',
                'body'    => "Hi {customer_name},\n\n"
                           . "your booking has been cancelled:\n\n"
                           . "{course_title}\n{date} at {time}\n\n"
                           . "Credits remaining: {credits_remaining}\n\n"
                           . "Manage your bookings here: {account_link}",
            ],
            'reminder' => [
                'subject' => 'Reminder: {course_title} on {date}',
                'body'    => "Hi {customer_name},\n\n"
                           . "your session is coming up:\n\n"
                           . "{course_title}\n{date} at {time}\n\n"
                           . "Session details: {course_link}\n"
                           . "Manage your bookings here: {account_link}\n\n"
                           . "We look forward to seeing you!",
            ],
            'access' => [
                'subject' => 'Access details: {course_title} on {date}',
                'body'    => "Hi {customer_name},\n\n"
                           . "here are the access details for your online session:\n\n"
                           . "{course_title}\n{date} at {time}\n\n"
                           . "Link: {meeting_link}\n\n"
                           . "{access_details}",
            ],
            'admin_booking' => [
                'subject' => 'New booking: {course_title}',
                'body'    => "{customer_name} booked:\n\n"
                           . "{course_title}\n{date} at {time}",
            ],
        ];
    }

    /* =========================================================
     * Placeholders
     * ========================================================= */

    public static function placeholders(int $user_id, int $slot_id): array {
        $user  = get_userdata($user_id);
        $start = self::slot_start($slot_id);
        $link  = (string) get_post_meta($slot_id, BW_Metaboxes::META_MEETING_LINK, true);

        return [
            '{customer_name}'     => $user ? $user->display_name : '',
            '{course_title}'      => get_the_title($slot_id) ?: '',
            '{date}'              => $start ? wp_date('d.m.Y', $start->getTimestamp()) : '',
            '{time}'              => $start ? wp_date('H:i', $start->getTimestamp()) : '',
            '{credits_remaining}' => (string) BW_Credits_Bookings_MVP::get_available_credits($user_id),
            '{meeting_link}'      => $link,
            '{access_details}'    => (string) get_post_meta($slot_id, BW_Metaboxes::META_ACCESS_INFO, true),
            '{course_link}'       => $slot_id > 0 ? (string) get_permalink($slot_id) : '',
            '{account_link}'      => BW_Credits_Bookings_MVP::my_account_url(),
        ];
    }

    private static function slot_start(int $slot_id): ?DateTime {
        $raw = get_post_meta($slot_id, BW_Credits_Bookings_MVP::META_START_DT, true);
        if (!$raw) return null;

        try {
            return new DateTime($raw, wp_timezone());
        } catch (Exception $e) {
            return null;
        }
    }

    /* =========================================================
     * Sending
     * ========================================================= */

    public static function send(string $key, int $user_id, int $slot_id, string $to = ''): bool {
        if (!self::is_enabled($key)) return false;

        if ($to === '') {
            $user = get_userdata($user_id);
            if (!$user || !is_email($user->user_email)) return false;
            $to = $user->user_email;
        }

        // Which language: a persistent per-customer preference, not the page
        // the customer happened to be browsing or the course session's own
        // WPML language — see BW_Email_Language. The admin copy ignores the
        // customer entirely and always goes out in the site's default
        // language, since it's read by the studio, not the customer.
        $lang = ($key === 'admin_booking')
            ? (string) apply_filters('wpml_default_language', null)
            : BW_Email_Language::get_user_language($user_id);

        // Locale-switch around the gettext resolution too (not just the WPML
        // step below) — subject_source()/body_source() resolve an untouched
        // default via __(), which otherwise depends on whatever WordPress
        // locale happens to be active for this particular request/cron run.
        $locale   = $lang !== '' ? (BW_Email_Language::locale_map()[$lang] ?? '') : '';
        $switched = false;
        if ($locale !== '' && $locale !== get_locale()) {
            switch_to_locale($locale);
            load_plugin_textdomain('bw-credits-booking', false, dirname(plugin_basename(BW_CREDITS_BOOKING_FILE)) . '/languages');
            $switched = true;
        }

        $subject_tpl = self::translate('subject_' . $key, self::subject_source($key), $lang !== '' ? $lang : null);
        $body_tpl    = self::translate('body_' . $key, self::body_source($key), $lang !== '' ? $lang : null);

        if ($switched) {
            restore_previous_locale();
        }

        $placeholders = self::placeholders($user_id, $slot_id);

        $subject = strtr($subject_tpl, $placeholders);

        // The body template is already-sanitized HTML (wp_kses_post, saved via
        // the WYSIWYG editor) — only the substituted values need escaping, not
        // the template itself. nl2br() stays for bodies saved before the
        // WYSIWYG editor existed (plain text with literal newlines).
        $escaped = array_map('esc_html', $placeholders);
        $body    = nl2br(strtr($body_tpl, $escaped));

        // Every URL-shaped placeholder value becomes clickable — applies
        // to meeting_link, course_link, and account_link alike
        foreach ($placeholders as $value) {
            if ($value === '' || !filter_var($value, FILTER_VALIDATE_URL)) continue;

            $body = str_replace(
                esc_html($value),
                '<a href="' . esc_url($value) . '">' . esc_html($value) . '</a>',
                $body
            );
        }

        $heading = wp_strip_all_tags($subject);

        // Reuse WooCommerce's own mailer wrapper for a consistent header/footer
        // (logo, colors, footer text — configured in WooCommerce → Settings →
        // Emails) instead of a bare, unbranded HTML shell.
        $mailer = null;
        if (function_exists('WC')) {
            $candidate = WC()->mailer();
            if ($candidate && method_exists($candidate, 'wrap_message')) {
                $mailer = $candidate;
            }
        }

        // Plain-text fallback, derived automatically — no separate plain-text
        // field for admins to keep in sync with the HTML body.
        $plain = html_entity_decode(
            wp_strip_all_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $body)),
            ENT_QUOTES
        );

        if ($mailer) {
            $html          = $mailer->wrap_message($heading, $body);
            $plain_wrapped = $mailer->wrap_message($heading, $plain, true);
        } else {
            $html          = '<html><body style="font-family:sans-serif;line-height:1.5">' . $body . '</body></html>';
            $plain_wrapped = $plain;
        }

        $set_alt_body = function ($phpmailer) use ($plain_wrapped) {
            $phpmailer->AltBody = $plain_wrapped;
        };

        add_filter('wp_mail_content_type', [__CLASS__, 'content_type_html']);
        add_action('phpmailer_init', $set_alt_body);
        $sent = wp_mail($to, $subject, $html);
        remove_filter('wp_mail_content_type', [__CLASS__, 'content_type_html']);
        remove_action('phpmailer_init', $set_alt_body);

        return (bool) $sent;
    }

    public static function content_type_html(): string {
        return 'text/html';
    }

    /* =========================================================
     * Booking events
     * ========================================================= */

    public static function on_booking_created($booking_id, $user_id, $slot_id) {
        self::send('booking', (int) $user_id, (int) $slot_id);

        $admin_to = (string) get_option(self::OPT_ADMIN_TO, get_option('admin_email'));
        if (is_email($admin_to)) {
            self::send('admin_booking', (int) $user_id, (int) $slot_id, $admin_to);
        }

        // Link already exists → this customer gets the access details immediately
        $link = (string) get_post_meta((int) $slot_id, BW_Metaboxes::META_MEETING_LINK, true);
        if ($link !== '' && self::send('access', (int) $user_id, (int) $slot_id)) {
            self::mark_access_sent((int) $booking_id);
        }
    }

    public static function on_booking_cancelled($booking_id, $user_id, $slot_id) {
        self::send('cancellation', (int) $user_id, (int) $slot_id);
    }

    /* =========================================================
     * Access details
     * ========================================================= */

    /**
     * Sends access details to all active bookings for a session that
     * haven't received them yet.
     */
    public static function send_access_for_slot($slot_id): int {
        global $wpdb;
        $slot_id = (int) $slot_id;

        $link = (string) get_post_meta($slot_id, BW_Metaboxes::META_MEETING_LINK, true);
        if ($link === '') return 0;

        $table = $wpdb->prefix . BW_Credits_Bookings_MVP::BOOKINGS_TABLE;

        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT id, user_id FROM {$table}
             WHERE slot_id = %d AND is_active = 1 AND status = 'booked'
               AND access_sent_at IS NULL",
            $slot_id
        ), ARRAY_A);

        $sent = 0;
        foreach ($rows as $row) {
            if (self::send('access', (int) $row['user_id'], $slot_id)) {
                self::mark_access_sent((int) $row['id']);
                $sent++;
            }
        }

        return $sent;
    }

    private static function mark_access_sent(int $booking_id) {
        global $wpdb;
        $table = $wpdb->prefix . BW_Credits_Bookings_MVP::BOOKINGS_TABLE;

        $wpdb->update(
            $table,
            ['access_sent_at' => current_time('mysql')],
            ['id' => $booking_id],
            ['%s'],
            ['%d']
        );
    }

    /** Resend: reset the flag and email everyone again. */
    public static function handle_resend_access() {
        if (!current_user_can(BW_Settings::CAPABILITY)) {
            wp_die(__('Not authorized.', 'bw-credits-booking'));
        }

        $slot_id = isset($_GET['slot_id']) ? (int) $_GET['slot_id'] : 0;
        check_admin_referer('bw_resend_access_' . $slot_id);

        global $wpdb;
        $table = $wpdb->prefix . BW_Credits_Bookings_MVP::BOOKINGS_TABLE;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET access_sent_at = NULL
             WHERE slot_id = %d AND is_active = 1 AND status = 'booked'",
            $slot_id
        ));

        $sent   = self::send_access_for_slot($slot_id);
        $back   = get_edit_post_link($slot_id, 'raw') ?: admin_url();
        $notice = $sent > 0
            ? 'ok:' . sprintf(
                /* translators: %d: number of participants the access details were sent to */
                __('Access details sent to %d participants.', 'bw-credits-booking'),
                $sent
            )
            : 'err:' . __('Nothing sent — meeting link is missing or there are no active bookings.', 'bw-credits-booking');

        wp_safe_redirect(add_query_arg('bw_notice', rawurlencode($notice), $back));
        exit;
    }

    /* =========================================================
     * Reminder cron
     * ========================================================= */

    public static function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', self::CRON_HOOK);
        }
    }

    public static function unschedule_cron() {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) wp_unschedule_event($ts, self::CRON_HOOK);
    }

    public static function run_reminders() {
        if (!self::is_enabled('reminder')) return;

        $hours = BW_Settings::get_reminder_hours();
        if ($hours <= 0) return;

        global $wpdb;
        $table = $wpdb->prefix . BW_Credits_Bookings_MVP::BOOKINGS_TABLE;

        $now   = new DateTime('now', wp_timezone());
        $until = (clone $now)->modify('+' . $hours . ' hours');

        // CAST, because start_datetime may or may not include seconds depending on its source
        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.user_id, b.slot_id
             FROM {$table} b
             INNER JOIN {$wpdb->postmeta} pm
                 ON pm.post_id = b.slot_id AND pm.meta_key = %s
             WHERE b.is_active = 1
               AND b.status = 'booked'
               AND b.reminded_at IS NULL
               AND CAST(pm.meta_value AS DATETIME) > %s
               AND CAST(pm.meta_value AS DATETIME) <= %s
             LIMIT 200",
            BW_Credits_Bookings_MVP::META_START_DT,
            $now->format('Y-m-d H:i:s'),
            $until->format('Y-m-d H:i:s')
        ), ARRAY_A);

        foreach ($rows as $row) {
            if (self::send('reminder', (int) $row['user_id'], (int) $row['slot_id'])) {
                $wpdb->update(
                    $table,
                    ['reminded_at' => current_time('mysql')],
                    ['id' => (int) $row['id']],
                    ['%s'],
                    ['%d']
                );
            }
        }
    }

    /* =========================================================
     * WPML
     * ========================================================= */

    public static function register_wpml_strings() {
        if (!has_action('wpml_register_single_string')) return;

        foreach (array_keys(self::types()) as $key) {
            do_action('wpml_register_single_string', 'BW Credits', 'subject_' . $key, self::get_subject($key));
            do_action('wpml_register_single_string', 'BW Credits', 'body_' . $key, self::get_body($key));
        }
    }

    private static function translate(string $name, string $value, ?string $lang): string {
        if (!has_filter('wpml_translate_single_string')) return $value;
        return (string) apply_filters('wpml_translate_single_string', $value, 'BW Credits', $name, $lang);
    }

    /* =========================================================
     * Settings page
     * ========================================================= */

    public static function register_menu() {
        add_submenu_page(
            BW_Settings::MENU_SLUG,
            __('Emails', 'bw-credits-booking'),
            __('Emails', 'bw-credits-booking'),
            BW_Settings::CAPABILITY,
            self::PAGE,
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings() {
        $group = 'bw_credits_emails';

        register_setting($group, self::OPT_ADMIN_TO, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_email',
        ]);

        foreach (array_keys(self::types()) as $key) {
            register_setting($group, self::opt_enabled($key), [
                'type'              => 'boolean',
                'sanitize_callback' => function ($v) { return $v ? 1 : 0; },
            ]);
            register_setting($group, self::opt_subject($key), [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ]);
            register_setting($group, self::opt_body($key), [
                'type'              => 'string',
                // wp_kses_post(), not sanitize_textarea_field() — the body is
                // now edited via a WYSIWYG editor (see render_page()) and may
                // contain real HTML (links, bold text, lists, …).
                'sanitize_callback' => 'wp_kses_post',
            ]);
        }
    }

    /* =========================================================
     * Reset to default
     * ========================================================= */

    private static function reset_url(string $key): string {
        return wp_nonce_url(
            admin_url('admin-post.php?action=bw_reset_email&key=' . rawurlencode($key)),
            'bw_reset_email_' . $key
        );
    }

    public static function handle_reset_email() {
        if (!current_user_can(BW_Settings::CAPABILITY)) {
            wp_die(__('Not authorized.', 'bw-credits-booking'));
        }

        $key = isset($_GET['key']) ? sanitize_key(wp_unslash($_GET['key'])) : '';
        check_admin_referer('bw_reset_email_' . $key);

        $defaults = self::defaults();
        $types    = self::types();
        if (!isset($defaults[$key])) {
            self::redirect('err:' . __('Unknown email type.', 'bw-credits-booking'));
        }

        update_option(self::opt_subject($key), $defaults[$key]['subject']);
        update_option(self::opt_body($key), $defaults[$key]['body']);

        self::redirect('ok:' . sprintf(
            /* translators: %s: email type label, e.g. "Booking confirmation" */
            __('%s reset to the default text.', 'bw-credits-booking'),
            $types[$key][0]
        ));
    }

    private static function redirect(string $notice) {
        wp_safe_redirect(add_query_arg(
            ['page' => self::PAGE, 'bw_notice' => rawurlencode($notice)],
            admin_url('admin.php')
        ));
        exit;
    }

    private static function notice() {
        if (empty($_GET['bw_notice'])) return;

        $raw     = sanitize_text_field(wp_unslash($_GET['bw_notice']));
        $is_err  = strpos($raw, 'err:') === 0;
        $message = substr($raw, 4);

        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            $is_err ? 'notice-error' : 'notice-success',
            esc_html($message)
        );
    }

    public static function render_page() {
        if (!current_user_can(BW_Settings::CAPABILITY)) return;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Email Texts', 'bw-credits-booking'); ?></h1>

            <?php self::notice(); ?>

            <?php if (has_action('wpml_register_single_string')) : ?>
                <div class="notice notice-info inline">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: the WPML String Translation context these email texts are registered under */
                            esc_html__('The subject and body below are the source text. To translate them into other languages, use WPML → String Translation, filtered by context %s.', 'bw-credits-booking'),
                            '<code>BW Credits</code>'
                        );
                        ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpml-string-translation%2Fmenu%2Fstring-translation.php')); ?>">
                            <?php esc_html_e('Open WPML String Translation', 'bw-credits-booking'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <p>
                <?php esc_html_e('Available placeholders:', 'bw-credits-booking'); ?>
                <code>{customer_name}</code> <code>{course_title}</code> <code>{date}</code>
                <code>{time}</code> <code>{credits_remaining}</code>
                <code>{meeting_link}</code> <code>{access_details}</code>
                <code>{course_link}</code> <code>{account_link}</code>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields('bw_credits_emails'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="bw_admin_to"><?php esc_html_e('Address for admin copies', 'bw-credits-booking'); ?></label></th>
                        <td>
                            <input type="email" id="bw_admin_to" class="regular-text"
                                   name="<?php echo esc_attr(self::OPT_ADMIN_TO); ?>"
                                   value="<?php echo esc_attr(get_option(self::OPT_ADMIN_TO, get_option('admin_email'))); ?>">
                        </td>
                    </tr>
                </table>

                <?php foreach (self::types() as $key => [$label, $description]) : ?>
                    <h2>
                        <?php echo esc_html($label); ?>
                        <a class="button button-small" style="margin-left:.5rem;font-weight:normal;vertical-align:middle;"
                           href="<?php echo esc_url(self::reset_url($key)); ?>"
                           onclick="return confirm('<?php echo esc_js(sprintf(
                               /* translators: %s: email type label, e.g. "Booking confirmation" */
                               __('Reset %s to the default text? Your saved changes will be lost.', 'bw-credits-booking'),
                               $label
                           )); ?>');">
                            <?php esc_html_e('Reset to default', 'bw-credits-booking'); ?>
                        </a>
                    </h2>
                    <p class="description"><?php echo esc_html($description); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Active', 'bw-credits-booking'); ?></th>
                            <td>
                                <label>
                                    <input type="hidden" name="<?php echo esc_attr(self::opt_enabled($key)); ?>" value="0">
                                    <input type="checkbox" name="<?php echo esc_attr(self::opt_enabled($key)); ?>"
                                           value="1" <?php checked(self::is_enabled($key)); ?>>
                                    <?php esc_html_e('Send this email', 'bw-credits-booking'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Subject', 'bw-credits-booking'); ?></th>
                            <td>
                                <input type="text" class="large-text"
                                       name="<?php echo esc_attr(self::opt_subject($key)); ?>"
                                       value="<?php echo esc_attr(self::get_subject($key)); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Body', 'bw-credits-booking'); ?></th>
                            <td>
                                <?php
                                wp_editor(self::get_body($key), 'bw_email_body_editor_' . $key, [
                                    'textarea_name' => self::opt_body($key),
                                    'textarea_rows' => 8,
                                    'media_buttons' => false,
                                    'teeny'         => true,
                                    'quicktags'     => true,
                                ]);
                                ?>
                                <p class="description">
                                    <?php esc_html_e('Avoid applying formatting (bold, links, …) to only part of a placeholder — e.g. bolding half of {course_title} can split it apart so it no longer gets replaced.', 'bw-credits-booking'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                <?php endforeach; ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

BW_Emails::init();
