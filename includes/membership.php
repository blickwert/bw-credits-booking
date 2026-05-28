<?php
if (!defined('ABSPATH')) exit;

/**
 * PMPro Membership Integration — optional, nur aktiv wenn PMPro installiert ist.
 *
 * Was es macht:
 *  - Kündigung Membership → alle unbenutzten Membership-Credits verfallen sofort
 *  - Alles in function_exists() gewrapped → kein Fehler wenn PMPro nicht aktiv
 */

class BW_Membership_Integration {

    public static function init() {
        if (!function_exists('pmpro_hasMembershipLevel')) return;

        // Fires wenn Membership-Level sich ändert (Aktivierung, Upgrade, Kündigung)
        add_action('pmpro_after_change_membership_level', [__CLASS__, 'handle_level_change'], 10, 2);
    }

    /**
     * Level 0 = Membership gekündigt / abgelaufen → Credits verfallen
     */
    public static function handle_level_change($level_id, $user_id) {
        if ((int) $level_id !== 0) return;
        self::expire_membership_credits((int) $user_id);
    }

    /**
     * Alle unbenutzten Membership-Credits eines Users auf 'expired' setzen.
     * Purchase-Credits (source='purchase') bleiben unberührt.
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
     * Prüft ob User eine aktive Membership hat.
     * Gibt true zurück wenn PMPro nicht aktiv (kein Blocking).
     */
    public static function has_active_membership(int $user_id): bool {
        if (!function_exists('pmpro_hasMembershipLevel')) return true;
        return (bool) pmpro_hasMembershipLevel(null, $user_id);
    }
}

BW_Membership_Integration::init();
