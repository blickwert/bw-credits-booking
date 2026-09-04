<?php
if (!defined('ABSPATH')) exit;

/**
 * PMPro membership integration — optional, only active if PMPro is installed.
 *
 * What it does:
 *  - Membership cancelled → all unused membership credits expire immediately
 *  - Everything wrapped in function_exists() → no error if PMPro isn't active
 */

class BW_Membership_Integration {

    public static function init() {
        if (!function_exists('pmpro_hasMembershipLevel')) return;

        // Fires when the membership level changes (activation, upgrade, cancellation)
        add_action('pmpro_after_change_membership_level', [__CLASS__, 'handle_level_change'], 10, 2);
    }

    /**
     * Level 0 = membership cancelled / expired → credits expire
     */
    public static function handle_level_change($level_id, $user_id) {
        if ((int) $level_id !== 0) return;
        self::expire_membership_credits((int) $user_id);
    }

    /**
     * Sets all of a user's unused membership credits to 'expired'.
     * Purchase credits (source='purchase') are left untouched.
     */
    public static function expire_membership_credits(int $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . BW_Credits_Bookings_MVP::CREDITS_TABLE;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = 'expired'
             WHERE user_id = %d
               AND source = 'membership'
               AND status = 'available'",
            $user_id
        ));
    }

    /**
     * Checks whether the user has an active membership.
     * Returns true if PMPro isn't active (no blocking).
     */
    public static function has_active_membership(int $user_id): bool {
        if (!function_exists('pmpro_hasMembershipLevel')) return true;
        return (bool) pmpro_hasMembershipLevel(null, $user_id);
    }
}

BW_Membership_Integration::init();
