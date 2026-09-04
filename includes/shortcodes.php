<?php
if (!defined('ABSPATH')) exit;

/**
 * Central shortcode registration.
 *
 * Scheme: bw_credits_{group}_{name}
 *   course — refers to a session (course_id parameter)
 *   user   — refers to the logged-in customer
 *   view   — a composite view
 */

class BW_Shortcodes {

    /** Shortcode name => callback */
    public static function map(): array {
        return [
            // course
            'bw_credits_course_list'         => ['BW_Course_List', 'render'],
            'bw_credits_course_booking'      => ['BW_Credits_Bookings_MVP', 'sc_slot_action'],
            'bw_credits_course_availability' => ['BW_Credits_Bookings_MVP', 'sc_availability'],
            'bw_credits_course_access'       => ['BW_View_Access', 'render'],

            // user
            'bw_credits_user_balance'  => ['BW_Credits_Bookings_MVP', 'sc_balance'],
            'bw_credits_user_credits'  => ['BW_View_Credits', 'render'],
            'bw_credits_user_bookings' => ['BW_Credits_Bookings_MVP', 'sc_my_bookings'],

            // view
            'bw_credits_view_overview' => ['BW_View_Overview', 'render'],
        ];
    }

    public static function init() {
        foreach (self::map() as $tag => $callback) {
            add_shortcode($tag, $callback);
        }
    }
}

BW_Shortcodes::init();
