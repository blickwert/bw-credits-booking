<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin-Unterseiten: Terminübersicht, Buchungen, Credits.
 */

class BW_Admin_Pages {

    const PAGE_SLOTS    = 'bw-credits-slots';
    const PAGE_BOOKINGS = 'bw-credits-bookings';
    const PAGE_CREDITS  = 'bw-credits-credits';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 20);

        add_action('admin_post_bw_add_booking',    [__CLASS__, 'handle_add_booking']);
        add_action('admin_post_bw_cancel_booking', [__CLASS__, 'handle_cancel_booking']);
        add_action('admin_post_bw_grant_credits',  [__CLASS__, 'handle_grant_credits']);
        add_action('admin_post_bw_revoke_credit',  [__CLASS__, 'handle_revoke_credit']);
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
