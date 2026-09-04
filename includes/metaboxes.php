<?php
if (!defined('ABSPATH')) exit;

/**
 * Metaboxen am Kurstermin: Kapazität, Teilnehmerliste, Online-Zugang.
 *
 * Die Felder werden plugin-eigen gespeichert (kein ACF nötig). Falls
 * dieselben Meta-Keys noch in einer ACF-Feldgruppe liegen, sollten sie
 * dort entfernt werden — sonst erscheinen die Felder doppelt.
 */

class BW_Metaboxes {

    const META_MEETING_LINK = '_bw_meeting_link';
    const META_ACCESS_INFO  = '_bw_access_info';

    const NONCE_SAVE = 'bw_slot_meta_save';

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'register']);
        add_action('save_post',      [__CLASS__, 'save'], 10, 2);

        // Nonce unabhängig von den Metaboxen ausgeben — sonst geht das
        // Speichern verloren sobald eine Box über Ansicht anpassen ausgeblendet ist
        add_action('edit_form_after_title', [__CLASS__, 'print_nonce']);

        add_action('admin_post_bw_admin_cancel_booking', [__CLASS__, 'handle_cancel']);
        add_action('admin_post_bw_toggle_no_show',       [__CLASS__, 'handle_no_show']);
        add_action('admin_post_bw_export_participants',  [__CLASS__, 'handle_export']);

        add_action('admin_notices', [__CLASS__, 'show_notice']);
    }

    private static function post_type(): string {
        return BW_Settings::get_slot_post_type();
    }

    /* ---------------------------------------------------------
     * Registrierung
     * --------------------------------------------------------- */

    public static function register() {
        $pt = self::post_type();

        add_meta_box('bw_slot_capacity', __('Capacity', 'bw-credits-booking'), [__CLASS__, 'render_capacity'], $pt, 'side', 'high');
        add_meta_box('bw_slot_access', __('Online Access', 'bw-credits-booking'), [__CLASS__, 'render_access'], $pt, 'normal', 'high');
        add_meta_box('bw_slot_participants', __('Participants', 'bw-credits-booking'), [__CLASS__, 'render_participants'], $pt, 'normal', 'high');
    }

    /* ---------------------------------------------------------
     * Metabox: Kapazität
     * --------------------------------------------------------- */

    public static function print_nonce($post) {
        if (!($post instanceof WP_Post) || $post->post_type !== self::post_type()) return;
        wp_nonce_field(self::NONCE_SAVE, 'bw_slot_meta_nonce');
    }

    public static function render_capacity(WP_Post $post) {
        $capacity_raw = get_post_meta($post->ID, BW_Credits_Bookings_MVP::META_CAPACITY, true);
        $booked       = (int) get_post_meta($post->ID, BW_Credits_Bookings_MVP::META_BOOKED_CNT, true);
        $default      = BW_Settings::get_default_capacity();
        $effective    = ($capacity_raw === '' || $capacity_raw === null) ? $default : (int) $capacity_raw;
        ?>
        <p>
            <label for="bw_capacity"><strong><?php esc_html_e('Maximum participants', 'bw-credits-booking'); ?></strong></label><br>
            <input type="number" min="0" step="1" id="bw_capacity" name="bw_capacity"
                   value="<?php echo esc_attr($capacity_raw); ?>" class="widefat"
                   placeholder="<?php echo esc_attr($default); ?>">
            <span class="description">
                <?php echo esc_html(sprintf(
                    /* translators: %s: the default capacity from settings */
                    __('Leave empty to use the default (%s) from settings.', 'bw-credits-booking'),
                    $default
                )); ?>
            </span>
        </p>

        <p>
            <strong><?php esc_html_e('Booked:', 'bw-credits-booking'); ?></strong>
            <?php echo esc_html($booked . ' / ' . $effective); ?>
            <?php if ($effective > 0 && $booked > $effective) : ?>
                <br><span style="color:#b32d2e"><strong><?php esc_html_e('Overbooked', 'bw-credits-booking'); ?></strong> – <?php esc_html_e('capacity is below the number of existing bookings.', 'bw-credits-booking'); ?></span>
            <?php endif; ?>
            <br><span class="description"><?php esc_html_e('Calculated automatically and cannot be edited directly.', 'bw-credits-booking'); ?></span>
        </p>
        <?php
    }

    /* ---------------------------------------------------------
     * Metabox: Online-Zugang
     * --------------------------------------------------------- */

    public static function render_access(WP_Post $post) {
        $link = get_post_meta($post->ID, self::META_MEETING_LINK, true);
        $info = get_post_meta($post->ID, self::META_ACCESS_INFO, true);
        ?>
        <p>
            <label for="bw_meeting_link"><strong><?php esc_html_e('Meeting link', 'bw-credits-booking'); ?></strong></label><br>
            <input type="url" id="bw_meeting_link" name="bw_meeting_link"
                   value="<?php echo esc_attr($link); ?>" class="widefat"
                   placeholder="https://zoom.us/j/...">
        </p>

        <p>
            <label for="bw_access_info"><strong><?php esc_html_e('Access details / notes', 'bw-credits-booking'); ?></strong></label><br>
            <textarea id="bw_access_info" name="bw_access_info" rows="4" class="widefat"
                      placeholder="<?php echo esc_attr__('Meeting ID, password, dial-in numbers …', 'bw-credits-booking'); ?>"><?php echo esc_textarea($info); ?></textarea>
        </p>

        <p class="description">
            <?php esc_html_e('As soon as a link is saved here for the first time, the access details are sent to all participants automatically. Anyone who books afterwards receives them directly with the booking confirmation.', 'bw-credits-booking'); ?>
        </p>

        <?php if ($link) : ?>
            <p>
                <a class="button"
                   href="<?php echo esc_url(wp_nonce_url(
                       admin_url('admin-post.php?action=bw_resend_access&slot_id=' . $post->ID),
                       'bw_resend_access_' . $post->ID
                   )); ?>"
                   onclick="return confirm('<?php echo esc_js(__('Resend access details to all participants?', 'bw-credits-booking')); ?>');">
                    <?php esc_html_e('Resend access details', 'bw-credits-booking'); ?>
                </a>
                <span class="description"><?php esc_html_e('Only necessary if the link has changed since.', 'bw-credits-booking'); ?></span>
            </p>
        <?php endif; ?>
        <?php
    }

    /* ---------------------------------------------------------
     * Metabox: Teilnehmer
     * --------------------------------------------------------- */

    public static function render_participants(WP_Post $post) {
        $bookings = BW_Credits_Bookings_MVP::get_slot_bookings($post->ID);
        $labels   = BW_Credits_Bookings_MVP::status_labels();

        if (empty($bookings)) {
            echo '<p>' . esc_html__('No bookings for this session yet.', 'bw-credits-booking') . '</p>';
            return;
        }

        $export_url = wp_nonce_url(
            admin_url('admin-post.php?action=bw_export_participants&slot_id=' . $post->ID),
            'bw_export_participants_' . $post->ID
        );
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:3em"><?php esc_html_e('No.', 'bw-credits-booking'); ?></th>
                    <th><?php esc_html_e('Name', 'bw-credits-booking'); ?></th>
                    <th><?php esc_html_e('Email', 'bw-credits-booking'); ?></th>
                    <th><?php esc_html_e('Booked on', 'bw-credits-booking'); ?></th>
                    <th><?php esc_html_e('Status', 'bw-credits-booking'); ?></th>
                    <th style="width:16em"><?php esc_html_e('Actions', 'bw-credits-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php $nr = 0; foreach ($bookings as $b) :
                $nr++;
                $active  = ((int) $b['is_active'] === 1);
                $status  = (string) $b['status'];
                $label   = $labels[$status] ?? $status;
                // LEFT JOIN: Nutzerdaten fehlen wenn das Konto gelöscht wurde
                $name    = $b['display_name'] ?: ('User #' . (int) $b['user_id']);
                $email   = (string) ($b['user_email'] ?? '');
                $created = mysql2date('d.m.Y H:i', $b['created_at']);
            ?>
                <tr<?php echo $active ? '' : ' style="opacity:.55"'; ?>>
                    <td><?php echo esc_html($nr); ?></td>
                    <td>
                        <a href="<?php echo esc_url(get_edit_user_link((int) $b['user_id'])); ?>">
                            <?php echo esc_html($name); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($email ?: '—'); ?></td>
                    <td><?php echo esc_html($created); ?></td>
                    <td><?php echo esc_html($label); ?></td>
                    <td>
                        <?php if ($active) : ?>
                            <a class="button button-small"
                               href="<?php echo esc_url(self::action_url('bw_admin_cancel_booking', (int) $b['id'], $post->ID)); ?>"
                               onclick="return confirm('<?php echo esc_js(__('Cancel booking? A consumed credit will be refunded.', 'bw-credits-booking')); ?>');">
                                <?php esc_html_e('Cancel', 'bw-credits-booking'); ?>
                            </a>
                            <?php if ($status === 'booked') : ?>
                                <a class="button button-small"
                                   href="<?php echo esc_url(self::action_url('bw_toggle_no_show', (int) $b['id'], $post->ID, ['no_show' => 1])); ?>">
                                    <?php esc_html_e('No-show', 'bw-credits-booking'); ?>
                                </a>
                            <?php elseif ($status === 'no_show') : ?>
                                <a class="button button-small"
                                   href="<?php echo esc_url(self::action_url('bw_toggle_no_show', (int) $b['id'], $post->ID, ['no_show' => 0])); ?>">
                                    <?php esc_html_e('Reset', 'bw-credits-booking'); ?>
                                </a>
                            <?php endif; ?>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:1em">
            <a class="button" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Attendance list as CSV', 'bw-credits-booking'); ?></a>
        </p>
        <?php
    }

    private static function action_url(string $action, int $booking_id, int $slot_id, array $extra = []): string {
        $args = array_merge([
            'action'     => $action,
            'booking_id' => $booking_id,
            'slot_id'    => $slot_id,
        ], $extra);

        return wp_nonce_url(
            add_query_arg($args, admin_url('admin-post.php')),
            $action . '_' . $booking_id
        );
    }

    /* ---------------------------------------------------------
     * Speichern
     * --------------------------------------------------------- */

    public static function save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!isset($post->post_type) || $post->post_type !== self::post_type()) return;

        if (!isset($_POST['bw_slot_meta_nonce']) ||
            !wp_verify_nonce(sanitize_key($_POST['bw_slot_meta_nonce']), self::NONCE_SAVE)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) return;

        // Leer = Standardwert aus den Einstellungen verwenden
        if (isset($_POST['bw_capacity'])) {
            $raw = trim((string) wp_unslash($_POST['bw_capacity']));
            if ($raw === '') {
                delete_post_meta($post_id, BW_Credits_Bookings_MVP::META_CAPACITY);
            } else {
                update_post_meta($post_id, BW_Credits_Bookings_MVP::META_CAPACITY, max(0, (int) $raw));
            }
        }

        $link_before = (string) get_post_meta($post_id, self::META_MEETING_LINK, true);
        $link_after  = $link_before;

        if (isset($_POST['bw_meeting_link'])) {
            $link_after = esc_url_raw(trim((string) wp_unslash($_POST['bw_meeting_link'])));
            if ($link_after === '') {
                delete_post_meta($post_id, self::META_MEETING_LINK);
            } else {
                update_post_meta($post_id, self::META_MEETING_LINK, $link_after);
            }
        }

        if (isset($_POST['bw_access_info'])) {
            $info = sanitize_textarea_field(wp_unslash($_POST['bw_access_info']));
            if ($info === '') {
                delete_post_meta($post_id, self::META_ACCESS_INFO);
            } else {
                update_post_meta($post_id, self::META_ACCESS_INFO, $info);
            }
        }

        // Erst jetzt feuern — die Zugangsdaten-Mail braucht auch das Hinweisfeld.
        // Nur beim Übergang leer → gesetzt, sonst würde jedes Speichern erneut versenden.
        if ($link_before === '' && $link_after !== '') {
            do_action('bw_meeting_link_added', $post_id);
        }
    }

    /* ---------------------------------------------------------
     * Aktionen aus der Teilnehmerliste
     * --------------------------------------------------------- */

    private static function verify_action(string $action): array {
        if (!current_user_can(BW_Settings::CAPABILITY)) {
            wp_die(__('Not authorized.', 'bw-credits-booking'));
        }

        $booking_id = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
        $slot_id    = isset($_GET['slot_id']) ? (int) $_GET['slot_id'] : 0;

        check_admin_referer($action . '_' . $booking_id);

        return [$booking_id, $slot_id];
    }

    private static function redirect_back(int $slot_id, string $notice) {
        $back = get_edit_post_link($slot_id, 'raw') ?: admin_url('edit.php?post_type=' . self::post_type());
        wp_safe_redirect(add_query_arg('bw_notice', rawurlencode($notice), $back));
        exit;
    }

    public static function handle_cancel() {
        [$booking_id, $slot_id] = self::verify_action('bw_admin_cancel_booking');

        $res = BW_Credits_Bookings_MVP::admin_cancel_booking($booking_id);
        self::redirect_back(
            $slot_id,
            is_wp_error($res) ? 'err:' . $res->get_error_message() : 'ok:' . __('Booking cancelled.', 'bw-credits-booking')
        );
    }

    public static function handle_no_show() {
        [$booking_id, $slot_id] = self::verify_action('bw_toggle_no_show');

        $no_show = !empty($_GET['no_show']);
        $res     = BW_Credits_Bookings_MVP::set_no_show($booking_id, $no_show);

        self::redirect_back(
            $slot_id,
            is_wp_error($res)
                ? 'err:' . $res->get_error_message()
                : 'ok:' . ($no_show
                    ? __('Marked as no-show.', 'bw-credits-booking')
                    : __('No-show mark removed.', 'bw-credits-booking'))
        );
    }

    public static function handle_export() {
        if (!current_user_can(BW_Settings::CAPABILITY)) {
            wp_die(__('Not authorized.', 'bw-credits-booking'));
        }

        $slot_id = isset($_GET['slot_id']) ? (int) $_GET['slot_id'] : 0;
        check_admin_referer('bw_export_participants_' . $slot_id);

        $bookings = BW_Credits_Bookings_MVP::get_slot_bookings($slot_id);
        $labels   = BW_Credits_Bookings_MVP::status_labels();
        $title    = get_the_title($slot_id) ?: ('slot-' . $slot_id);
        $filename = sanitize_file_name('participants-' . $title . '.csv');

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM für Excel
        fputcsv($out, [
            __('No.', 'bw-credits-booking'),
            __('Name', 'bw-credits-booking'),
            __('Email', 'bw-credits-booking'),
            __('Booked on', 'bw-credits-booking'),
            __('Status', 'bw-credits-booking'),
        ]);

        $nr = 0;
        foreach ($bookings as $b) {
            $nr++;
            fputcsv($out, array_map([__CLASS__, 'csv_cell'], [
                $nr,
                $b['display_name'] ?: ('User #' . (int) $b['user_id']),
                (string) ($b['user_email'] ?? ''),
                mysql2date('d.m.Y H:i', $b['created_at']),
                $labels[$b['status']] ?? $b['status'],
            ]));
        }

        fclose($out);
        exit;
    }

    /**
     * Anzeigenamen stammen vom Nutzer. Tabellenprogramme werten Zellen die mit
     * = + - @ beginnen als Formel aus — deshalb vorne ein Apostroph setzen.
     */
    private static function csv_cell($value): string {
        $value = (string) $value;
        if ($value !== '' && strpos("=+-@\t\r", $value[0]) !== false) {
            return "'" . $value;
        }
        return $value;
    }

    /* ---------------------------------------------------------
     * Rückmeldung nach einer Aktion
     * --------------------------------------------------------- */

    public static function show_notice() {
        if (empty($_GET['bw_notice'])) return;

        $raw = sanitize_text_field(wp_unslash($_GET['bw_notice']));
        if (strpos($raw, 'err:') === 0) {
            $class = 'notice-error';
            $msg   = substr($raw, 4);
        } else {
            $class = 'notice-success';
            $msg   = substr($raw, 3);
        }

        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr($class),
            esc_html($msg)
        );
    }
}

BW_Metaboxes::init();
