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

    public static function defaults(): array {
        return [
            'booking' => [
                'subject' => 'Buchungsbestätigung: {kurs_titel}',
                'body'    => "Hallo {kundenname},\n\n"
                           . "deine Buchung ist bestätigt:\n\n"
                           . "{kurs_titel}\n{datum} um {uhrzeit}\n\n"
                           . "Verbleibende Credits: {credits_verbleibend}\n\n"
                           . "Details zum Kurs: {kurs_link}\n"
                           . "Deine Buchungen verwaltest du hier: {konto_link}\n\n"
                           . "Bis bald!",
            ],
            'cancellation' => [
                'subject' => 'Stornierung: {kurs_titel}',
                'body'    => "Hallo {kundenname},\n\n"
                           . "deine Buchung wurde storniert:\n\n"
                           . "{kurs_titel}\n{datum} um {uhrzeit}\n\n"
                           . "Verbleibende Credits: {credits_verbleibend}\n\n"
                           . "Deine Buchungen verwaltest du hier: {konto_link}",
            ],
            'reminder' => [
                'subject' => 'Erinnerung: {kurs_titel} am {datum}',
                'body'    => "Hallo {kundenname},\n\n"
                           . "dein Kurs steht an:\n\n"
                           . "{kurs_titel}\n{datum} um {uhrzeit}\n\n"
                           . "Details zum Kurs: {kurs_link}\n"
                           . "Deine Buchungen verwaltest du hier: {konto_link}\n\n"
                           . "Wir freuen uns auf dich!",
            ],
            'access' => [
                'subject' => 'Zugangsdaten: {kurs_titel} am {datum}',
                'body'    => "Hallo {kundenname},\n\n"
                           . "hier sind die Zugangsdaten für deinen Online-Kurs:\n\n"
                           . "{kurs_titel}\n{datum} um {uhrzeit}\n\n"
                           . "Link: {meeting_link}\n\n"
                           . "{zugangsdaten}",
            ],
            'admin_booking' => [
                'subject' => 'Neue Buchung: {kurs_titel}',
                'body'    => "{kundenname} hat gebucht:\n\n"
                           . "{kurs_titel}\n{datum} um {uhrzeit}",
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
            '{kundenname}'          => $user ? $user->display_name : '',
            '{kurs_titel}'          => get_the_title($slot_id) ?: '',
            '{datum}'               => $start ? wp_date('d.m.Y', $start->getTimestamp()) : '',
            '{uhrzeit}'             => $start ? wp_date('H:i', $start->getTimestamp()) : '',
            '{credits_verbleibend}' => (string) BW_Credits_Bookings_MVP::get_available_credits($user_id),
            '{meeting_link}'        => $link,
            '{zugangsdaten}'        => (string) get_post_meta($slot_id, BW_Metaboxes::META_ACCESS_INFO, true),
            '{kurs_link}'           => $slot_id > 0 ? (string) get_permalink($slot_id) : '',
            '{konto_link}'          => BW_Credits_Bookings_MVP::my_account_url(),
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

        $lang         = self::slot_language($slot_id);
        $subject_tpl  = self::translate('subject_' . $key, self::get_subject($key), $lang);
        $body_tpl     = self::translate('body_' . $key, self::get_body($key), $lang);
        $placeholders = self::placeholders($user_id, $slot_id);

        $subject = strtr($subject_tpl, $placeholders);

        // Escape values before they land in HTML — the link is made clickable afterwards
        $escaped = array_map('esc_html', $placeholders);
        $body    = nl2br(strtr(esc_html($body_tpl), $escaped));

        // Every URL-shaped placeholder value becomes clickable — applies
        // to meeting_link, kurs_link, and konto_link alike
        foreach ($placeholders as $value) {
            if ($value === '' || !filter_var($value, FILTER_VALIDATE_URL)) continue;

            $body = str_replace(
                esc_html($value),
                '<a href="' . esc_url($value) . '">' . esc_html($value) . '</a>',
                $body
            );
        }

        $html = '<html><body style="font-family:sans-serif;line-height:1.5">' . $body . '</body></html>';

        add_filter('wp_mail_content_type', [__CLASS__, 'content_type_html']);
        $sent = wp_mail($to, $subject, $html);
        remove_filter('wp_mail_content_type', [__CLASS__, 'content_type_html']);

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

    /** The session's language determines the email's language. */
    private static function slot_language(int $slot_id): ?string {
        if (!has_filter('wpml_post_language_details')) return null;

        $details = apply_filters('wpml_post_language_details', null, $slot_id);
        return is_array($details) && !empty($details['language_code'])
            ? (string) $details['language_code']
            : null;
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
                'sanitize_callback' => 'sanitize_textarea_field',
            ]);
        }
    }

    public static function render_page() {
        if (!current_user_can(BW_Settings::CAPABILITY)) return;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Email Texts', 'bw-credits-booking'); ?></h1>

            <p>
                <?php esc_html_e('Available placeholders:', 'bw-credits-booking'); ?>
                <code>{kundenname}</code> <code>{kurs_titel}</code> <code>{datum}</code>
                <code>{uhrzeit}</code> <code>{credits_verbleibend}</code>
                <code>{meeting_link}</code> <code>{zugangsdaten}</code>
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
                    <h2><?php echo esc_html($label); ?></h2>
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
                                <textarea rows="8" class="large-text code"
                                          name="<?php echo esc_attr(self::opt_body($key)); ?>"><?php
                                    echo esc_textarea(self::get_body($key));
                                ?></textarea>
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
