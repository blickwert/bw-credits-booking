<?php
if (!defined('ABSPATH')) exit;

/**
 * Zentrale Shortcode-Registrierung.
 *
 * Schema: bw_credits_{gruppe}_{name}
 *   course — spricht über einen Termin  (Parameter course_id)
 *   user   — spricht über den eingeloggten Kunden
 *   view   — zusammengesetzte Ansicht
 *
 * Die alten Namen bleiben als Alias bestehen und werden beim Aufruf
 * vermerkt, damit im Admin sichtbar wird welche Seiten noch umzustellen sind.
 */

class BW_Shortcodes {

    const OPT_LEGACY_USAGE = 'bw_legacy_shortcode_usage';

    /** Alter Name => neuer Name */
    const ALIASES = [
        'bw_course_slots'    => 'bw_credits_course_list',
        'bw_slot_action'     => 'bw_credits_course_booking',
        'bw_availability'    => 'bw_credits_course_availability',
        'bw_my_bookings'     => 'bw_credits_user_bookings',
        'bw_balance_inline'  => 'bw_credits_user_balance',
        'bw_credits_balance' => 'bw_credits_user_balance',
        'bw_book_button'     => 'bw_credits_course_booking',
        'bw_cancel_button'   => 'bw_credits_course_booking',
    ];

    /**
     * Voreinstellungen je Alias — bildet das frühere Verhalten nach.
     * bw_credits_balance war ein Absatz, bw_balance_inline eine Zahl.
     */
    const ALIAS_DEFAULTS = [
        'bw_credits_balance' => ['format' => 'block'],
        'bw_balance_inline'  => ['format' => 'inline'],
    ];

    /** Neuer Name => Callback */
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

        foreach (array_keys(self::ALIASES) as $old) {
            add_shortcode($old, [__CLASS__, 'render_alias']);
        }

        add_action('admin_notices', [__CLASS__, 'legacy_notice']);
    }

    /* ---------------------------------------------------------
     * Alias-Auflösung — ruft das Ziel direkt auf statt über
     * do_shortcode_tag(), das in WordPress als privat markiert ist
     * --------------------------------------------------------- */

    public static function render_alias($atts, $content = null, $tag = '') {
        if (!isset(self::ALIASES[$tag])) return '';

        $atts = is_array($atts) ? $atts : [];

        // slot_id hieß früher so — course_id hat Vorrang wenn beides gesetzt ist
        if (isset($atts['slot_id']) && !isset($atts['course_id'])) {
            $atts['course_id'] = $atts['slot_id'];
        }

        if (isset(self::ALIAS_DEFAULTS[$tag])) {
            $atts += self::ALIAS_DEFAULTS[$tag];
        }

        self::record_legacy_usage($tag);

        $target   = self::ALIASES[$tag];
        $callback = self::map()[$target] ?? null;
        if (!$callback || !is_callable($callback)) return '';

        return (string) call_user_func($callback, $atts, $content, $target);
    }

    /* ---------------------------------------------------------
     * Fundstellen merken
     * --------------------------------------------------------- */

    private static function record_legacy_usage(string $tag) {
        $post_id = get_the_ID();
        if (!$post_id) return;

        $usage = self::get_legacy_usage();
        $key   = $tag . ':' . $post_id;

        if (isset($usage[$key])) return; // nur einmal je Kombination schreiben

        $usage[$key] = [
            'tag'     => $tag,
            'post_id' => (int) $post_id,
            'seen_at' => current_time('mysql'),
        ];

        update_option(self::OPT_LEGACY_USAGE, $usage, false);
    }

    public static function get_legacy_usage(): array {
        $usage = get_option(self::OPT_LEGACY_USAGE, []);
        return is_array($usage) ? $usage : [];
    }

    public static function clear_legacy_usage() {
        delete_option(self::OPT_LEGACY_USAGE);
    }

    /* ---------------------------------------------------------
     * Hinweis im Admin
     * --------------------------------------------------------- */

    public static function legacy_notice() {
        if (!current_user_can(BW_Settings::CAPABILITY)) return;

        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'bw-credits') === false) return;

        $usage = self::get_legacy_usage();
        if (empty($usage)) return;

        printf(
            '<div class="notice notice-warning"><p><strong>%d Fundstelle(n) mit alten Shortcode-Namen.</strong> '
            . '<a href="%s">Übersicht ansehen</a></p></div>',
            count($usage),
            esc_url(admin_url('admin.php?page=' . BW_Admin_Pages::PAGE_SHORTCODES))
        );
    }
}

BW_Shortcodes::init();
