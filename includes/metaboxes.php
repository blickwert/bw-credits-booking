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

        add_meta_box('bw_slot_capacity', 'Kapazität', [__CLASS__, 'render_capacity'], $pt, 'side', 'high');
        add_meta_box('bw_slot_access', 'Online-Zugang', [__CLASS__, 'render_access'], $pt, 'normal', 'high');
        add_meta_box('bw_slot_participants', 'Teilnehmer', [__CLASS__, 'render_participants'], $pt, 'normal', 'high');
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
            <label for="bw_capacity"><strong>Maximale Teilnehmer</strong></label><br>
            <input type="number" min="0" step="1" id="bw_capacity" name="bw_capacity"
                   value="<?php echo esc_attr($capacity_raw); ?>" class="widefat"
                   placeholder="<?php echo esc_attr($default); ?>">
            <span class="description">
                Leer lassen für den Standardwert (<?php echo esc_html($default); ?>) aus den Einstellungen.
            </span>
        </p>

        <p>
            <strong>Belegt:</strong>
            <?php echo esc_html($booked . ' / ' . $effective); ?>
            <?php if ($effective > 0 && $booked > $effective) : ?>
                <br><span style="color:#b32d2e"><strong>Überbucht</strong> – die Kapazität liegt unter der Zahl bestehender Buchungen.</span>
            <?php endif; ?>
            <br><span class="description">Wird automatisch berechnet und kann nicht direkt bearbeitet werden.</span>
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
            <label for="bw_meeting_link"><strong>Meeting-Link</strong></label><br>
            <input type="url" id="bw_meeting_link" name="bw_meeting_link"
                   value="<?php echo esc_attr($link); ?>" class="widefat"
                   placeholder="https://zoom.us/j/...">
        </p>

        <p>
            <label for="bw_access_info"><strong>Zugangsdaten / Hinweise</strong></label><br>
            <textarea id="bw_access_info" name="bw_access_info" rows="4" class="widefat"
                      placeholder="Meeting-ID, Passwort, Einwahlnummern …"><?php echo esc_textarea($info); ?></textarea>
        </p>

        <p class="description">
            Der Versand der Zugangsdaten an die Teilnehmer wird mit dem E-Mail-System ergänzt.
        </p>
        <?php
    }

    /* ---------------------------------------------------------
     * Metabox: Teilnehmer
     * --------------------------------------------------------- */

    public static function render_participants(WP_Post $post) {
        $bookings = BW_Credits_Bookings_MVP::get_slot_bookings($post->ID);
        $labels   = BW_Credits_Bookings_MVP::status_labels();

        if (empty($bookings)) {
            echo '<p>Noch keine Buchungen für diesen Termin.</p>';
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
                    <th style="width:3em">Nr.</th>
                    <th>Name</th>
                    <th>E-Mail</th>
                    <th>Angemeldet</th>
                    <th>Status</th>
                    <th style="width:16em">Aktionen</th>
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
                               onclick="return confirm('Buchung stornieren? Ein verbrauchter Credit wird zurückgegeben.');">
                                Stornieren
                            </a>
                            <?php if ($status === 'booked') : ?>
                                <a class="button button-small"
                                   href="<?php echo esc_url(self::action_url('bw_toggle_no_show', (int) $b['id'], $post->ID, ['no_show' => 1])); ?>">
                                    Nicht erschienen
                                </a>
                            <?php elseif ($status === 'no_show') : ?>
                                <a class="button button-small"
                                   href="<?php echo esc_url(self::action_url('bw_toggle_no_show', (int) $b['id'], $post->ID, ['no_show' => 0])); ?>">
                                    Zurücksetzen
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
            <a class="button" href="<?php echo esc_url($export_url); ?>">Anwesenheitsliste als CSV</a>
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

        if (isset($_POST['bw_meeting_link'])) {
            $link = esc_url_raw(trim((string) wp_unslash($_POST['bw_meeting_link'])));
            if ($link === '') {
                delete_post_meta($post_id, self::META_MEETING_LINK);
            } else {
                update_post_meta($post_id, self::META_MEETING_LINK, $link);
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
    }

    /* ---------------------------------------------------------
     * Aktionen aus der Teilnehmerliste
     * --------------------------------------------------------- */

    private static function verify_action(string $action): array {
        if (!current_user_can(BW_Settings::CAPABILITY)) {
            wp_die('Keine Berechtigung.');
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
            is_wp_error($res) ? 'err:' . $res->get_error_message() : 'ok:Buchung storniert.'
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
                : 'ok:' . ($no_show ? 'Als nicht erschienen markiert.' : 'Markierung zurückgenommen.')
        );
    }

    public static function handle_export() {
        if (!current_user_can(BW_Settings::CAPABILITY)) {
            wp_die('Keine Berechtigung.');
        }

        $slot_id = isset($_GET['slot_id']) ? (int) $_GET['slot_id'] : 0;
        check_admin_referer('bw_export_participants_' . $slot_id);

        $bookings = BW_Credits_Bookings_MVP::get_slot_bookings($slot_id);
        $labels   = BW_Credits_Bookings_MVP::status_labels();
        $title    = get_the_title($slot_id) ?: ('slot-' . $slot_id);
        $filename = sanitize_file_name('teilnehmer-' . $title . '.csv');

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM für Excel
        fputcsv($out, ['Nr.', 'Name', 'E-Mail', 'Angemeldet', 'Status']);

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
