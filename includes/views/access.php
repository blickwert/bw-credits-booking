<?php
if (!defined('ABSPATH')) exit;

/**
 * [bw_credits_course_access] — Zugangsdaten zum Online-Kurs.
 *
 * Sichtbar ausschließlich für eingeloggte Nutzer mit aktiver Buchung für
 * genau diesen Termin. Ohne Buchung wird nichts ausgegeben — auch kein
 * Hinweis, der verraten würde dass es überhaupt Zugangsdaten gibt.
 */

class BW_View_Access {

    public static function render($atts) {
        $atts = shortcode_atts([
            'course_id' => 0,
            'title'     => '',   // leer = Text aus dem Katalog
        ], $atts, 'bw_credits_course_access');

        if (!is_user_logged_in()) return '';

        $slot_id = BW_Credits_Bookings_MVP::resolve_course_id((int) $atts['course_id']);
        if ($slot_id <= 0) return '';

        $booking = BW_Credits_Bookings_MVP::get_active_booking(get_current_user_id(), $slot_id);
        if (!$booking || $booking['status'] !== 'booked') return '';

        $link = (string) get_post_meta($slot_id, BW_Metaboxes::META_MEETING_LINK, true);
        $info = (string) get_post_meta($slot_id, BW_Metaboxes::META_ACCESS_INFO, true);

        if ($link === '' && $info === '') return '';

        $title = $atts['title'] !== '' ? $atts['title'] : bw_text('access.title');

        BW_Credits_Bookings_MVP::ensure_assets();

        ob_start();
        ?>
        <div class="bw-access">
            <?php if ($title !== '') : ?>
                <h3 class="bw-access__title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>

            <?php if ($link !== '') : ?>
                <p class="bw-access__link">
                    <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html(bw_text('access.link')); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if ($info !== '') : ?>
                <div class="bw-access__info"><?php echo nl2br(esc_html($info)); ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
