<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin-Unterseiten: Terminübersicht, Buchungen, Credits.
 */

class BW_Admin_Pages {

    const PAGE_SLOTS    = 'bw-credits-slots';
    const PAGE_BOOKINGS = 'bw-credits-bookings';
    const PAGE_CREDITS  = 'bw-credits-credits';
    const PAGE_SHORTCODES = 'bw-credits-shortcodes';
    const PAGE_TEXTS      = 'bw-credits-texts';
    const PAGE_TEMPLATES  = 'bw-credits-templates';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 20);

        add_action('admin_post_bw_add_booking',    [__CLASS__, 'handle_add_booking']);
        add_action('admin_post_bw_cancel_booking', [__CLASS__, 'handle_cancel_booking']);
        add_action('admin_post_bw_grant_credits',  [__CLASS__, 'handle_grant_credits']);
        add_action('admin_post_bw_revoke_credit',  [__CLASS__, 'handle_revoke_credit']);
        add_action('admin_post_bw_copy_template',  [__CLASS__, 'handle_copy_template']);
        add_action('admin_init',                   [__CLASS__, 'register_text_settings']);
    }

    private static function cap(): string {
        return BW_Settings::CAPABILITY;
    }

    private static function post_type(): string {
        return BW_Settings::get_slot_post_type();
    }

    public static function register_menu() {
        $parent = BW_Settings::MENU_SLUG;
        $cap    = self::cap();

        add_submenu_page($parent, 'Termine',   'Termine',   $cap, self::PAGE_SLOTS,    [__CLASS__, 'render_slots']);
        add_submenu_page($parent, 'Buchungen', 'Buchungen', $cap, self::PAGE_BOOKINGS, [__CLASS__, 'render_bookings']);
        add_submenu_page($parent, 'Credits',   'Credits',   $cap, self::PAGE_CREDITS,  [__CLASS__, 'render_credits']);
        add_submenu_page($parent, 'Shortcodes', 'Shortcodes', $cap, self::PAGE_SHORTCODES, [__CLASS__, 'render_shortcodes']);
        add_submenu_page($parent, 'Texte', 'Texte', $cap, self::PAGE_TEXTS, [__CLASS__, 'render_texts']);
        add_submenu_page($parent, 'Templates', 'Templates', $cap, self::PAGE_TEMPLATES, [__CLASS__, 'render_templates']);
    }

    /* =========================================================
     * Gemeinsame Helfer
     * ========================================================= */

    private static function guard() {
        if (!current_user_can(self::cap())) {
            wp_die('Keine Berechtigung.');
        }
    }

    private static function page_url(string $page, array $args = []): string {
        return add_query_arg(array_merge(['page' => $page], $args), admin_url('admin.php'));
    }

    private static function redirect(string $page, array $args, string $notice) {
        wp_safe_redirect(self::page_url($page, array_merge($args, ['bw_notice' => rawurlencode($notice)])));
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

    /** Termine für Auswahlfelder — kommende zuerst. */
    private static function slot_options(): array {
        $posts = get_posts([
            'post_type'      => self::post_type(),
            'post_status'    => 'publish',
            'numberposts'    => 300,
            'meta_key'       => BW_Credits_Bookings_MVP::META_START_DT,
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
        ]);

        $out = [];
        foreach ($posts as $p) {
            $out[$p->ID] = $p->post_title ?: ('#' . $p->ID);
        }
        return $out;
    }

    private static function slot_start_label(int $slot_id): string {
        $raw = get_post_meta($slot_id, BW_Credits_Bookings_MVP::META_START_DT, true);
        $ts  = $raw ? strtotime($raw) : 0;
        return $ts ? wp_date('d.m.Y H:i', $ts) : '—';
    }

    /* =========================================================
     * Seite: Termine
     * ========================================================= */

    public static function render_slots() {
        self::guard();

        $filter = isset($_GET['when']) ? sanitize_key($_GET['when']) : 'upcoming';
        $now    = current_time('mysql');

        $meta_query = [];
        if ($filter === 'upcoming') {
            $meta_query[] = ['key' => BW_Credits_Bookings_MVP::META_START_DT, 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME'];
        } elseif ($filter === 'past') {
            $meta_query[] = ['key' => BW_Credits_Bookings_MVP::META_START_DT, 'value' => $now, 'compare' => '<', 'type' => 'DATETIME'];
        }

        $slots = get_posts([
            'post_type'   => self::post_type(),
            'post_status' => 'publish',
            'numberposts' => 100,
            'meta_key'    => BW_Credits_Bookings_MVP::META_START_DT,
            'orderby'     => 'meta_value',
            'order'       => $filter === 'past' ? 'DESC' : 'ASC',
            'meta_query'  => $meta_query,
        ]);

        echo '<div class="wrap"><h1>Termine</h1>';
        self::notice();

        $tabs = ['upcoming' => 'Kommende', 'past' => 'Vergangene', 'all' => 'Alle'];
        echo '<ul class="subsubsub">';
        $i = 0;
        foreach ($tabs as $key => $label) {
            $i++;
            printf(
                '<li><a href="%s"%s>%s</a>%s</li>',
                esc_url(self::page_url(self::PAGE_SLOTS, ['when' => $key])),
                $filter === $key ? ' class="current"' : '',
                esc_html($label),
                $i < count($tabs) ? ' | ' : ''
            );
        }
        echo '</ul><div style="clear:both"></div>';

        if (empty($slots)) {
            echo '<p>Keine Termine gefunden.</p></div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>
                <th>Termin</th><th>Titel</th><th>Belegung</th><th>Auslastung</th><th>Aktionen</th>
              </tr></thead><tbody>';

        foreach ($slots as $slot) {
            $booked   = (int) get_post_meta($slot->ID, BW_Credits_Bookings_MVP::META_BOOKED_CNT, true);
            $cap_raw  = get_post_meta($slot->ID, BW_Credits_Bookings_MVP::META_CAPACITY, true);
            $capacity = ($cap_raw === '' || $cap_raw === null)
                        ? BW_Settings::get_default_capacity()
                        : (int) $cap_raw;

            $pct  = $capacity > 0 ? min(100, (int) round($booked / $capacity * 100)) : 0;
            $over = ($capacity > 0 && $booked > $capacity);

            $color = $over ? '#b32d2e' : ($pct >= 100 ? '#996800' : '#2271b1');
            ?>
            <tr>
                <td><?php echo esc_html(self::slot_start_label($slot->ID)); ?></td>
                <td><a href="<?php echo esc_url(get_edit_post_link($slot->ID)); ?>"><?php echo esc_html($slot->post_title ?: '#' . $slot->ID); ?></a></td>
                <td>
                    <?php echo esc_html($booked . ' / ' . $capacity); ?>
                    <?php if ($over) : ?>
                        <strong style="color:#b32d2e">(überbucht)</strong>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="background:#e0e0e0;border-radius:3px;height:14px;width:120px;overflow:hidden">
                        <div style="background:<?php echo esc_attr($color); ?>;height:100%;width:<?php echo esc_attr($pct); ?>%"></div>
                    </div>
                    <small><?php echo esc_html($pct); ?>%</small>
                </td>
                <td>
                    <a class="button button-small" href="<?php echo esc_url(self::page_url(self::PAGE_BOOKINGS, ['slot_id' => $slot->ID])); ?>">Teilnehmer</a>
                    <a class="button button-small" href="<?php echo esc_url(get_edit_post_link($slot->ID)); ?>">Bearbeiten</a>
                </td>
            </tr>
            <?php
        }

        echo '</tbody></table></div>';
    }

    /* =========================================================
     * Seite: Buchungen
     * ========================================================= */

    public static function render_bookings() {
        self::guard();

        $slot_id = isset($_GET['slot_id']) ? (int) $_GET['slot_id'] : 0;
        $status  = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $search  = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $page    = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $result = BW_Credits_Bookings_MVP::query_bookings([
            'slot_id'  => $slot_id,
            'status'   => $status,
            'search'   => $search,
            'page'     => $page,
            'per_page' => 30,
        ]);

        $labels = BW_Credits_Bookings_MVP::status_labels();

        echo '<div class="wrap"><h1>Buchungen</h1>';
        self::notice();

        /* --- Filter --- */
        ?>
        <form method="get" style="margin:1em 0">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_BOOKINGS); ?>">

            <select name="slot_id">
                <option value="">Alle Termine</option>
                <?php foreach (self::slot_options() as $id => $title) : ?>
                    <option value="<?php echo esc_attr($id); ?>" <?php selected($slot_id, $id); ?>>
                        <?php echo esc_html($title); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value="">Alle Status</option>
                <?php foreach ($labels as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Name oder E-Mail">
            <?php submit_button('Filtern', 'secondary', '', false); ?>
            <a class="button" href="<?php echo esc_url(self::page_url(self::PAGE_BOOKINGS)); ?>">Zurücksetzen</a>
        </form>
        <?php

        self::render_add_booking_form($slot_id);

        if (empty($result['rows'])) {
            echo '<p>Keine Buchungen gefunden.</p></div>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>
                <th>#</th><th>Kunde</th><th>Termin</th><th>Gebucht am</th>
                <th>Status</th><th>Credit</th><th>Aktion</th>
              </tr></thead><tbody>';

        foreach ($result['rows'] as $b) {
            $active = ((int) $b['is_active'] === 1);
            $name   = $b['display_name'] ?: ('User #' . (int) $b['user_id']);
            ?>
            <tr<?php echo $active ? '' : ' style="opacity:.55"'; ?>>
                <td><?php echo (int) $b['id']; ?></td>
                <td>
                    <a href="<?php echo esc_url(self::page_url(self::PAGE_CREDITS, ['user_id' => (int) $b['user_id']])); ?>">
                        <?php echo esc_html($name); ?>
                    </a><br>
                    <small><?php echo esc_html((string) ($b['user_email'] ?? '')); ?></small>
                </td>
                <td>
                    <a href="<?php echo esc_url(get_edit_post_link((int) $b['slot_id'])); ?>">
                        <?php echo esc_html(get_the_title((int) $b['slot_id']) ?: '#' . (int) $b['slot_id']); ?>
                    </a><br>
                    <small><?php echo esc_html(self::slot_start_label((int) $b['slot_id'])); ?></small>
                </td>
                <td><?php echo esc_html(mysql2date('d.m.Y H:i', $b['created_at'])); ?></td>
                <td><?php echo esc_html($labels[$b['status']] ?? $b['status']); ?></td>
                <td><?php echo $b['credit_id'] ? '#' . (int) $b['credit_id'] : '<em>Freiplatz</em>'; ?></td>
                <td>
                    <?php if ($active) : ?>
                        <a class="button button-small"
                           href="<?php echo esc_url(wp_nonce_url(
                               admin_url('admin-post.php?action=bw_cancel_booking&booking_id=' . (int) $b['id']),
                               'bw_cancel_booking_' . (int) $b['id']
                           )); ?>"
                           onclick="return confirm('Buchung stornieren?');">Stornieren</a>
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }

        echo '</tbody></table>';

        /* --- Blättern --- */
        $pages = (int) ceil($result['total'] / $result['per_page']);
        if ($pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([
                'base'      => add_query_arg('paged', '%#%'),
                'format'    => '',
                'current'   => $result['page'],
                'total'     => $pages,
                'prev_text' => '‹',
                'next_text' => '›',
            ]);
            echo '</div></div>';
        }

        printf('<p><em>%d Buchungen gesamt.</em></p>', (int) $result['total']);
        echo '</div>';
    }

    private static function render_add_booking_form(int $preselect_slot) {
        ?>
        <div class="card" style="max-width:none;margin-bottom:1.5em">
            <h2 style="margin-top:0">Buchung hinzufügen</h2>
            <p class="description">
                Trägt einen bestehenden Benutzer in einen Termin ein — auch wenn der Termin
                bereits begonnen hat. Als Freiplatz wird kein Credit abgezogen.
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="bw_add_booking">
                <?php wp_nonce_field('bw_add_booking'); ?>

                <p>
                    <label>Benutzer<br>
                        <?php
                        wp_dropdown_users([
                            'name'              => 'user_id',
                            'show'              => 'display_name_with_login',
                            'show_option_none'  => '— Benutzer wählen —',
                            'option_none_value' => 0,
                            'number'            => 500,
                        ]);
                        ?>
                    </label>
                </p>

                <p>
                    <label>Termin<br>
                        <select name="slot_id">
                            <option value="0">— Termin wählen —</option>
                            <?php foreach (self::slot_options() as $id => $title) : ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($preselect_slot, $id); ?>>
                                    <?php echo esc_html($title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </p>

                <p>
                    <label>
                        <input type="checkbox" name="free_spot" value="1">
                        Freiplatz — ohne Credit-Abzug
                    </label>
                </p>

                <?php submit_button('Buchung anlegen', 'primary', '', false); ?>
            </form>
        </div>
        <?php
    }

    /* =========================================================
     * Seite: Credits
     * ========================================================= */

    public static function render_credits() {
        self::guard();

        $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

        echo '<div class="wrap"><h1>Credits</h1>';
        self::notice();

        if ($user_id > 0 && get_userdata($user_id)) {
            self::render_credit_detail($user_id);
        } else {
            self::render_credit_search();
        }

        echo '</div>';
    }

    private static function render_credit_search() {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        ?>
        <form method="get" style="margin:1em 0">
            <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_CREDITS); ?>">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Name, E-Mail oder Login">
            <?php submit_button('Benutzer suchen', 'secondary', '', false); ?>
        </form>
        <?php

        if ($search === '') {
            echo '<p>Benutzer suchen um dessen Credits zu sehen und zu verwalten.</p>';
            return;
        }

        $users = get_users([
            'search'         => '*' . $search . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number'         => 50,
        ]);

        if (empty($users)) {
            echo '<p>Keine Benutzer gefunden.</p>';
            return;
        }

        echo '<table class="wp-list-table widefat striped"><thead><tr>
                <th>Name</th><th>E-Mail</th><th>Verfügbar</th><th></th>
              </tr></thead><tbody>';

        foreach ($users as $u) {
            $summary = BW_Credits_Bookings_MVP::get_credit_summary($u->ID);
            printf(
                '<tr><td>%s</td><td>%s</td><td><strong>%d</strong></td><td><a class="button button-small" href="%s">Verwalten</a></td></tr>',
                esc_html($u->display_name),
                esc_html($u->user_email),
                $summary['available'],
                esc_url(self::page_url(self::PAGE_CREDITS, ['user_id' => $u->ID]))
            );
        }

        echo '</tbody></table>';
    }

    private static function render_credit_detail(int $user_id) {
        $user    = get_userdata($user_id);
        $summary = BW_Credits_Bookings_MVP::get_credit_summary($user_id);
        $credits = BW_Credits_Bookings_MVP::get_user_credits($user_id);

        $source_labels = [
            'purchase'   => 'Kauf',
            'membership' => 'Mitgliedschaft',
            'manual'     => 'Manuell',
        ];
        $status_labels = [
            'available' => 'Verfügbar',
            'used'      => 'Verbraucht',
            'expired'   => 'Abgelaufen',
        ];
        ?>
        <p><a href="<?php echo esc_url(self::page_url(self::PAGE_CREDITS)); ?>">&larr; Zurück zur Suche</a></p>

        <h2><?php echo esc_html($user->display_name); ?> <small>(<?php echo esc_html($user->user_email); ?>)</small></h2>

        <p>
            <strong><?php echo (int) $summary['available']; ?></strong> verfügbar ·
            <?php echo (int) $summary['used']; ?> verbraucht ·
            <?php echo (int) $summary['expired']; ?> abgelaufen ·
            <?php echo (int) $summary['total']; ?> gesamt
        </p>

        <div class="card" style="max-width:none;margin:1.5em 0">
            <h3 style="margin-top:0">Credits gutschreiben</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="bw_grant_credits">
                <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>">
                <?php wp_nonce_field('bw_grant_credits_' . $user_id); ?>

                <p>
                    <label>Anzahl
                        <input type="number" name="amount" min="1" max="500" value="1" class="small-text" required>
                    </label>
                    &nbsp;
                    <label>Gültig bis
                        <input type="date" name="expires_at">
                        <span class="description">leer = unbegrenzt</span>
                    </label>
                </p>

                <?php submit_button('Gutschreiben', 'primary', '', false); ?>
            </form>
        </div>

        <?php if (empty($credits)) : ?>
            <p>Keine Credits vorhanden.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th>#</th><th>Status</th><th>Herkunft</th><th>Erstellt</th>
                    <th>Gültig bis</th><th>Buchung</th><th>Aktion</th>
                </tr></thead>
                <tbody>
                <?php foreach ($credits as $c) :
                    $is_available = ($c['status'] === 'available');
                    $expired      = $c['expires_at'] && strtotime($c['expires_at']) <= time();
                ?>
                    <tr<?php echo $is_available && !$expired ? '' : ' style="opacity:.55"'; ?>>
                        <td><?php echo (int) $c['id']; ?></td>
                        <td>
                            <?php echo esc_html($status_labels[$c['status']] ?? $c['status']); ?>
                            <?php if ($is_available && $expired) : ?>
                                <br><small style="color:#b32d2e">Frist abgelaufen</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($source_labels[$c['source']] ?? $c['source']); ?></td>
                        <td><?php echo esc_html(mysql2date('d.m.Y', $c['created_at'])); ?></td>
                        <td><?php echo $c['expires_at'] ? esc_html(mysql2date('d.m.Y', $c['expires_at'])) : '<em>unbegrenzt</em>'; ?></td>
                        <td><?php echo $c['booking_id'] ? '#' . (int) $c['booking_id'] : '—'; ?></td>
                        <td>
                            <?php if ($is_available) : ?>
                                <a class="button button-small"
                                   href="<?php echo esc_url(wp_nonce_url(
                                       admin_url('admin-post.php?action=bw_revoke_credit&credit_id=' . (int) $c['id'] . '&user_id=' . $user_id),
                                       'bw_revoke_credit_' . (int) $c['id']
                                   )); ?>"
                                   onclick="return confirm('Diesen Credit entwerten?');">Entwerten</a>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    /* =========================================================
     * Aktionen
     * ========================================================= */

    public static function handle_add_booking() {
        self::guard();
        check_admin_referer('bw_add_booking');

        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $slot_id = isset($_POST['slot_id']) ? (int) $_POST['slot_id'] : 0;
        $free    = !empty($_POST['free_spot']);

        if ($user_id <= 0 || $slot_id <= 0) {
            self::redirect(self::PAGE_BOOKINGS, [], 'err:Benutzer und Termin auswählen.');
        }

        $res = BW_Credits_Bookings_MVP::admin_book_slot($user_id, $slot_id, !$free);

        self::redirect(
            self::PAGE_BOOKINGS,
            ['slot_id' => $slot_id],
            is_wp_error($res)
                ? 'err:' . $res->get_error_message()
                : 'ok:Buchung angelegt' . ($free ? ' (Freiplatz).' : '.')
        );
    }

    public static function handle_cancel_booking() {
        self::guard();

        $booking_id = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
        check_admin_referer('bw_cancel_booking_' . $booking_id);

        $res = BW_Credits_Bookings_MVP::admin_cancel_booking($booking_id);

        self::redirect(
            self::PAGE_BOOKINGS,
            [],
            is_wp_error($res) ? 'err:' . $res->get_error_message() : 'ok:Buchung storniert.'
        );
    }

    public static function handle_grant_credits() {
        self::guard();

        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        check_admin_referer('bw_grant_credits_' . $user_id);

        $amount = isset($_POST['amount']) ? (int) $_POST['amount'] : 0;
        $date   = isset($_POST['expires_at']) ? sanitize_text_field(wp_unslash($_POST['expires_at'])) : '';

        // Gültig bis Tagesende, sonst verfällt der Credit am Stichtag um 00:00
        $expires_at = null;
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $expires_at = $date . ' 23:59:59';
        }

        $res = BW_Credits_Bookings_MVP::grant_credits($user_id, $amount, $expires_at, 'manual');

        self::redirect(
            self::PAGE_CREDITS,
            ['user_id' => $user_id],
            is_wp_error($res)
                ? 'err:' . $res->get_error_message()
                : 'ok:' . $amount . ' Credit(s) gutgeschrieben.'
        );
    }

    /* =========================================================
     * Seite: Texte
     * ========================================================= */

    public static function register_text_settings() {
        register_setting('bw_credits_texts', BW_Text::OPT_OVERRIDES, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_texts'],
        ]);
    }

    /**
     * Nur tatsächlich geänderte Texte speichern. Wer ein Feld leert oder auf
     * den Standard zurücksetzt, bekommt wieder den übersetzbaren Standard —
     * die Option bleibt so klein wie möglich.
     */
    public static function sanitize_texts($value): array {
        if (!is_array($value)) return [];

        $catalogue = BW_Text::catalogue();
        $clean     = [];

        foreach ($value as $key => $text) {
            if (!isset($catalogue[$key])) continue;

            $text = sanitize_text_field(wp_unslash($text));
            if ($text === '' || $text === $catalogue[$key][0]) continue;

            $clean[$key] = $text;
        }

        return $clean;
    }

    /* =========================================================
     * Seite: Templates
     * ========================================================= */

    public static function render_templates() {
        self::guard();

        echo '<div class="wrap"><h1>Templates</h1>';

        self::notice();

        echo '<p>Jedes Template kann im aktiven Theme unter '
           . '<code>bw-credits-booking/&lt;pfad&gt;</code> überschrieben werden — '
           . 'z.&nbsp;B. <code>yourtheme/bw-credits-booking/course_list/course_list.php</code>. '
           . '„In Theme kopieren" legt die Datei dort direkt an. Wortlaut gehört nicht in '
           . 'die Templates, der wird unter '
           . '<a href="' . esc_url(self::page_url(self::PAGE_TEXTS)) . '">Texte</a> gepflegt.</p>';

        echo '<table class="wp-list-table widefat striped"><thead><tr>'
           . '<th>Template</th><th>Beschreibung</th><th>Status</th><th>Version</th><th></th>'
           . '</tr></thead><tbody>';

        foreach (BW_Templates::registry() as $path => $description) {
            $plugin_file = BW_Templates::plugin_path($path);
            $active_file = bw_locate_template($path);
            $overridden  = ($active_file !== $plugin_file);

            $plugin_version = BW_Templates::file_version($plugin_file);
            $active_version = $overridden ? BW_Templates::file_version($active_file) : $plugin_version;

            $outdated = $overridden
                && $plugin_version !== null
                && $active_version !== null
                && version_compare($active_version, $plugin_version, '<');

            if (!$overridden) {
                $status = '<span style="color:#116611">Plugin-Standard</span>';
            } elseif ($outdated) {
                $status = '<span style="color:#b32d2e"><strong>Im Theme überschrieben — veraltet</strong></span>';
            } else {
                $status = '<span style="color:#2271b1">Im Theme überschrieben</span>';
            }
            ?>
            <tr>
                <td><code><?php echo esc_html($path); ?></code></td>
                <td><?php echo esc_html($description); ?></td>
                <td><?php echo $status; ?></td>
                <td>
                    <?php echo esc_html($active_version ?? '—'); ?>
                    <?php if ($outdated) : ?>
                        <br><small>Plugin: <?php echo esc_html($plugin_version); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$overridden) : ?>
                        <a class="button button-small" href="<?php echo esc_url(self::copy_template_url($path)); ?>">
                            In Theme kopieren
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }

        echo '</tbody></table></div>';
    }

    private static function copy_template_url(string $path): string {
        return wp_nonce_url(
            admin_url('admin-post.php?action=bw_copy_template&path=' . rawurlencode($path)),
            'bw_copy_template_' . $path
        );
    }

    /**
     * Kopiert die Plugin-Vorlage ins aktive Theme. Direkte Dateizugriffe
     * statt WP_Filesystem — die Aktion ist admin-only und schreibt nur ins
     * eigene Theme-Unterverzeichnis, keine Notwendigkeit für die
     * FTP-Credentials-Abstraktion die WP_Filesystem sonst mitbringt.
     */
    public static function handle_copy_template() {
        self::guard();

        $path = isset($_GET['path']) ? sanitize_text_field(wp_unslash($_GET['path'])) : '';
        check_admin_referer('bw_copy_template_' . $path);

        $registry = BW_Templates::registry();
        if (!isset($registry[$path])) {
            self::redirect(self::PAGE_TEMPLATES, [], 'err:Unbekanntes Template.');
        }

        $source = BW_Templates::plugin_path($path);
        $target = get_stylesheet_directory() . '/bw-credits-booking/' . $path;

        if (!is_readable($source)) {
            self::redirect(self::PAGE_TEMPLATES, [], 'err:Quelldatei nicht lesbar.');
        }

        wp_mkdir_p(dirname($target));

        if (!copy($source, $target)) {
            self::redirect(self::PAGE_TEMPLATES, [], 'err:Kopieren fehlgeschlagen — Schreibrechte im Theme prüfen.');
        }

        self::redirect(self::PAGE_TEMPLATES, [], 'ok:' . $path . ' ins Theme kopiert.');
    }

    public static function render_texts() {
        self::guard();

        $catalogue = BW_Text::catalogue();
        $overrides = BW_Text::overrides();

        $by_group = [];
        foreach ($catalogue as $key => [$default, $description, $group]) {
            $by_group[$group][$key] = [$default, $description];
        }

        echo '<div class="wrap"><h1>Texte</h1>';
        self::notice();

        printf(
            '<p>%d Texte, davon %d angepasst. Ein leeres Feld nutzt den Standardtext.</p>',
            count($catalogue),
            count($overrides)
        );

        echo '<p class="description">Platzhalter in geschweiften Klammern bleiben erhalten, '
           . 'z.&nbsp;B. <code>{free}</code> oder <code>{date}</code>.</p>';

        echo '<form method="post" action="options.php">';
        settings_fields('bw_credits_texts');

        foreach (BW_Text::GROUPS as $group => $heading) {
            if (empty($by_group[$group])) continue;

            printf('<h2>%s</h2>', esc_html__($heading, 'bw-credits-booking'));
            echo '<table class="form-table"><tbody>';

            foreach ($by_group[$group] as $key => [$default, $description]) {
                $current = $overrides[$key] ?? '';
                ?>
                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr('bwtxt-' . $key); ?>">
                            <?php echo esc_html__($description, 'bw-credits-booking'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" class="large-text"
                               id="<?php echo esc_attr('bwtxt-' . $key); ?>"
                               name="<?php echo esc_attr(BW_Text::OPT_OVERRIDES . '[' . $key . ']'); ?>"
                               value="<?php echo esc_attr($current); ?>"
                               placeholder="<?php echo esc_attr($default); ?>">
                        <p class="description">
                            <code><?php echo esc_html($key); ?></code>
                            &nbsp;·&nbsp; Standard: <em><?php echo esc_html($default); ?></em>
                        </p>
                    </td>
                </tr>
                <?php
            }

            echo '</tbody></table>';
        }

        submit_button();
        echo '</form></div>';
    }

    /* =========================================================
     * Seite: Shortcodes
     * ========================================================= */

    /** Referenz: Name => [Gruppe, Beschreibung, Parameter] */
    private static function shortcode_reference(): array {
        return [
            'bw_credits_course_list' => [
                'Kurs',
                'Terminliste, nach Tagen gruppiert, mit Verfügbarkeit und Buchen-Button.',
                'limit, days, type, level, lang, show_filter, show_action, availability, group_by_day, empty',
            ],
            'bw_credits_course_booking' => [
                'Kurs',
                'Ein Button der je nach Zustand bucht oder storniert.',
                'course_id, label_book, label_cancel, class',
            ],
            'bw_credits_course_availability' => [
                'Kurs',
                'Freie Plätze. Auch ohne Login sichtbar.',
                'course_id, format, full',
            ],
            'bw_credits_course_access' => [
                'Kurs',
                'Zugangsdaten zum Online-Kurs. Nur für Teilnehmer mit aktiver Buchung.',
                'course_id, title',
            ],
            'bw_credits_user_balance' => [
                'Kunde',
                'Verfügbares Guthaben. Mit mode="empty_only" nur sichtbar wenn der Kunde schon einmal Guthaben hatte und jetzt keines mehr hat.',
                'mode (always|empty_only), format (inline|block), label, empty_text, empty_link, logged_out',
            ],
            'bw_credits_user_credits' => [
                'Kunde',
                'Guthaben im Detail: Herkunft und Ablaufdatum.',
                'show_expired, empty',
            ],
            'bw_credits_user_bookings' => [
                'Kunde',
                'Buchungen des Kunden mit Storno-Möglichkeit.',
                'limit, show_access',
            ],
            'bw_credits_view_overview' => [
                'Ansicht',
                'Guthaben, nächster Termin und Links. Steht automatisch im Konto-Dashboard.',
                'show_balance, show_next, show_links, list_url',
            ],
        ];
    }

    public static function render_shortcodes() {
        self::guard();

        echo '<div class="wrap"><h1>Shortcodes</h1>';
        self::notice();

        echo '<p>Auf einer Termin-Einzelseite kann <code>course_id</code> entfallen — '
           . 'dann greift der aktuelle Beitrag.</p>';

        echo '<table class="wp-list-table widefat striped"><thead><tr>'
           . '<th style="width:6em">Gruppe</th><th style="width:22em">Shortcode</th>'
           . '<th>Beschreibung</th><th>Parameter</th></tr></thead><tbody>';

        foreach (self::shortcode_reference() as $tag => [$group, $description, $params]) {
            printf(
                '<tr><td>%s</td><td><code>[%s]</code></td><td>%s</td><td><small>%s</small></td></tr>',
                esc_html($group),
                esc_html($tag),
                esc_html($description),
                esc_html($params)
            );
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public static function handle_revoke_credit() {
        self::guard();

        $credit_id = isset($_GET['credit_id']) ? (int) $_GET['credit_id'] : 0;
        $user_id   = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
        check_admin_referer('bw_revoke_credit_' . $credit_id);

        $res = BW_Credits_Bookings_MVP::revoke_credit($credit_id, $user_id);

        self::redirect(
            self::PAGE_CREDITS,
            ['user_id' => $user_id],
            is_wp_error($res) ? 'err:' . $res->get_error_message() : 'ok:Credit entwertet.'
        );
    }
}

BW_Admin_Pages::init();
