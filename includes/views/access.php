<?php
if (!defined('ABSPATH')) exit;

/**
 * [bw_credits_course_access] — access details for the online session,
 * or location and notes for a session held in person.
 *
 * Visible only to logged-in users with an active booking for exactly
 * this session. Without a booking, nothing is output — not even a note
 * that would reveal that access details exist at all.
 */

class BW_View_Access {

    public static function render($atts) {
        $atts = shortcode_atts([
            'course_id' => 0,
            'title'     => '',   // empty = text from the catalogue
        ], $atts, 'bw_credits_course_access');

        if (!is_user_logged_in()) return '';

        $slot_id = BW_Credits_Bookings_MVP::resolve_course_id((int) $atts['course_id']);
        if ($slot_id <= 0) return '';

        $booking = BW_Credits_Bookings_MVP::get_active_booking(get_current_user_id(), $slot_id);
        if (!$booking || $booking['status'] !== 'booked') return '';

        if (BW_Metaboxes::is_in_person($slot_id)) {
            $link  = '';
            $info  = trim(BW_Metaboxes::get_location($slot_id) . "\n\n" . BW_Metaboxes::get_location_notes($slot_id));
            $title = $atts['title'] !== '' ? $atts['title'] : bw_text('access.location_title');
        } else {
            $link  = BW_Metaboxes::get_meeting_link($slot_id);
            $info  = BW_Metaboxes::get_access_info($slot_id);
            $title = $atts['title'] !== '' ? $atts['title'] : bw_text('access.title');
        }

        if ($link === '' && $info === '') return '';

        BW_Credits_Bookings_MVP::ensure_assets();

        ob_start();
        bw_get_template('course_access/course_access.php', [
            'title'      => $title,
            'link'       => $link,
            'info'       => $info,
            'link_label' => bw_text('access.link'),
        ]);
        return ob_get_clean();
    }
}
