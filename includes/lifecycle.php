<?php
if (!defined('ABSPATH')) exit;

/**
 * What happens to bookings and credits when a session or an account is removed.
 *
 * - A session goes to the trash or is deleted → its upcoming bookings are cancelled, every credit is refunded
 *   and the customers get the "session cancelled" email; the studio gets one summary mail.
 * - An account is deleted → its upcoming bookings are cancelled and refunded (the spot becomes free again, no
 *   email to the customer). The credits themselves stay on file: they carry nothing but the former user ID,
 *   so they are anonymous, keep the sales/usage figures intact and can no longer be used. The studio gets a
 *   summary mail with the credits that were left. The delete-user screen warns beforehand.
 *
 * Bookings of sessions that already took place are never touched (no refund for a class that was held).
 */
class BW_Lifecycle {

    /** '' | 'session' | 'user' — read by BW_Emails::on_booking_cancelled() while bookings are cancelled in bulk. */
    public static $mode = '';

    public static function init() {
        add_action('wp_trash_post', [__CLASS__, 'on_session_removed']);
        add_action('before_delete_post', [__CLASS__, 'on_session_removed']);
        add_action('delete_user', [__CLASS__, 'on_user_delete'], 5);
        add_action('delete_user_form', [__CLASS__, 'delete_user_notice'], 10, 2);
    }

    /** Booked and still to come (or without a start date): the bookings that get cancelled and refunded. */
    public static function is_cancellable(array $booking, ?DateTime $start, DateTime $now): bool {
        if ((int) ($booking['is_active'] ?? 0) !== 1) return false;
        if (($booking['status'] ?? '') !== 'booked') return false;
        return !$start || $start > $now;
    }

    /** Cancels (and refunds) the given bookings. Returns the number cancelled. */
    private static function cancel_all(array $booking_ids, string $mode): int {
        $done = 0;
        self::$mode = $mode;
        try {
            foreach ($booking_ids as $id) {
                if (!is_wp_error(BW_Credits_Bookings_MVP::admin_cancel_booking((int) $id))) $done++;
            }
        } finally {
            self::$mode = '';
        }
        return $done;
    }

    /* ---------------------------------------------------------
     * Session removed
     * --------------------------------------------------------- */

    public static function on_session_removed($post_id) {
        $post_id = (int) $post_id;
        $post    = get_post($post_id);
        if (!$post || $post->post_type !== BW_Settings::get_slot_post_type()) return;

        $now   = new DateTime('now', wp_timezone());
        $start = BW_Credits_Bookings_MVP::get_slot_start_datetime($post_id);

        $ids = $people = [];
        foreach (BW_Credits_Bookings_MVP::get_slot_bookings($post_id) as $b) {
            if (!self::is_cancellable($b, $start, $now)) continue;
            $ids[]    = (int) $b['id'];
            $name     = BW_Credits_Bookings_MVP::booking_user_label($b);
            $people[] = $name . (trim((string) ($b['user_email'] ?? '')) !== '' ? ' (' . $b['user_email'] . ')' : '');
        }
        if (!$ids) return;

        $done = self::cancel_all($ids, 'session');
        self::mail_admin(
            /* translators: %s: session title */
            sprintf(__('Session removed: %s', 'bw-credits-booking'), html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8')),
            sprintf(
                /* translators: 1: number cancelled, 2: number of bookings, 3: list of customers */
                __("The session was moved to the trash or deleted. %1\$d of %2\$d bookings were cancelled, the credits were refunded and the customers informed:\n\n%3\$s", 'bw-credits-booking'),
                $done,
                count($ids),
                implode("\n", $people)
            )
        );
    }

    /* ---------------------------------------------------------
     * Account deleted
     * --------------------------------------------------------- */

    /** Upcoming bookings of a user: [booking id => start]. */
    private static function upcoming_bookings(int $user_id): array {
        global $wpdb;
        $table = $wpdb->prefix . BW_Credits_Bookings_MVP::BOOKINGS_TABLE;
        $rows  = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT id, slot_id, status, is_active FROM {$table} WHERE user_id = %d AND is_active = 1 AND status = 'booked'",
            $user_id
        ), ARRAY_A);

        $now = new DateTime('now', wp_timezone());
        $out = [];
        foreach ($rows as $r) {
            $start = BW_Credits_Bookings_MVP::get_slot_start_datetime((int) $r['slot_id']);
            if (self::is_cancellable($r, $start, $now)) $out[(int) $r['id']] = $start;
        }
        return $out;
    }

    public static function on_user_delete($user_id) {
        $user_id = (int) $user_id;
        $user    = get_userdata($user_id);
        if (!$user) return;

        $upcoming  = self::upcoming_bookings($user_id);
        $cancelled = $upcoming ? self::cancel_all(array_keys($upcoming), 'user') : 0;
        $left      = BW_Credits_Bookings_MVP::get_available_credits($user_id);

        if (!$upcoming && $left < 1) return;

        self::mail_admin(
            /* translators: %s: customer name */
            sprintf(__('Account deleted: %s', 'bw-credits-booking'), $user->display_name),
            sprintf(
                /* translators: 1: name, 2: email, 3: cancelled bookings, 4: upcoming bookings, 5: unused credits */
                __("The account of %1\$s (%2\$s) was deleted.\n\nUpcoming bookings cancelled and refunded: %3\$d of %4\$d.\nUnused credits left: %5\$d. They stay on file without a name and can no longer be used.", 'bw-credits-booking'),
                $user->display_name,
                $user->user_email,
                $cancelled,
                count($upcoming),
                $left
            )
        );
    }

    /** Warning on the "Delete users" confirmation screen. */
    public static function delete_user_notice($current_user = null, $userids = []) {
        $lines = [];
        foreach ((array) $userids as $id) {
            $id    = (int) $id;
            $user  = get_userdata($id);
            if (!$user) continue;
            $count = count(self::upcoming_bookings($id));
            $left  = BW_Credits_Bookings_MVP::get_available_credits($id);
            if ($count < 1 && $left < 1) continue;
            $lines[] = sprintf(
                /* translators: 1: name, 2: upcoming bookings, 3: unused credits */
                __('%1$s: %2$d upcoming booking(s) will be cancelled and refunded, %3$d unused credit(s) stay on file without a name and can no longer be used.', 'bw-credits-booking'),
                $user->display_name,
                $count,
                $left
            );
        }
        if (!$lines) return;

        echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Credits and bookings', 'bw-credits-booking') . '</strong></p><ul>';
        foreach ($lines as $line) echo '<li>' . esc_html($line) . '</li>';
        echo '</ul></div>';
    }

    /* ---------------------------------------------------------
     * Studio mail
     * --------------------------------------------------------- */

    private static function mail_admin(string $subject, string $message) {
        $to = BW_Emails::admin_recipient();
        if (is_email($to)) wp_mail($to, $subject, $message);
    }
}

BW_Lifecycle::init();
