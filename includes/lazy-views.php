<?php
if (!defined('ABSPATH')) exit;

/**
 * Lazy views — user- and time-dependent shortcodes load via JS.
 *
 * Problem: shortcodes like free spots, book/cancel button, credit balance or
 * the booking list depend on the visitor (login, credits) and on the moment
 * (free spots). A page cache (WP Super Cache, WP Rocket, CDN, …) would
 * freeze that state for everyone.
 *
 * Solution: when the page renders, a shortcode only outputs an empty
 * placeholder <span class="bw-lazy-view" data-bw-view="…" data-bw-sig="…">.
 * assets/lazy-views.js collects all placeholders of a page, sends them in a
 * single POST to /bw-credits/v1/views and puts the answer in. The answer is
 * never cached; the page itself contains no personal data and stays cacheable.
 *
 * Protection against manipulation:
 *  - The placeholder carries tag + attributes (+ language) as a payload signed
 *    with an HMAC (server secret, wp_salt). The client cannot change the
 *    shortcode, slot or attributes; a payload without a matching signature
 *    is discarded (hash_equals).
 *  - Only the shortcodes in views() can be requested — no free choice of callbacks.
 *  - Nothing user-related comes from the client: the shortcodes use
 *    get_current_user_id(). WordPress itself checks the REST nonce for a
 *    logged-in session (X-WP-Nonce); without a valid one the request counts
 *    as logged out and only sees what a guest sees.
 *  - The HTML comes only from the plugin's own templates (escaped there), never
 *    from client input. The number of items and the payload size are capped.
 *  - Responses carry Cache-Control: no-store.
 *  - Booking and cancelling remain separate, server-checked routes (login +
 *    nonce + book_slot()/cancel_booking()); this class only changes how
 *    state is shown, not what is allowed.
 *
 * Switch off: add_filter('bw_lazy_views_enabled', '__return_false') or
 * define('BW_LAZY_VIEWS_DISABLED', true) — shortcodes then render directly again.
 */

class BW_Lazy_Views {

    const MAX_ITEMS   = 30;     // placeholders per request
    const MAX_PAYLOAD = 4000;   // bytes per payload
    const SIG_CONTEXT = 'bw-lazy-view|';

    /** True while the REST route renders — then the shortcode produces real HTML. */
    private static $rendering = false;

    /** Shortcode tag => callback. Only these can be requested. */
    public static function views(): array {
        return [
            'bw_credits_course_booking'      => ['BW_Credits_Bookings_MVP', 'sc_slot_action'],
            'bw_credits_course_availability' => ['BW_Credits_Bookings_MVP', 'sc_availability'],
            'bw_credits_course_access'       => ['BW_View_Access', 'render'],
            'bw_credits_user_balance'        => ['BW_Credits_Bookings_MVP', 'sc_balance'],
            'bw_credits_user_credits'        => ['BW_View_Credits', 'render'],
            'bw_credits_user_bookings'       => ['BW_Credits_Bookings_MVP', 'sc_my_bookings'],
            'bw_credits_view_overview'       => ['BW_View_Overview', 'render'],
        ];
    }

    /** Views that refer to a session: the course_id is fixed at render time. */
    private static function course_tags(): array {
        return ['bw_credits_course_booking', 'bw_credits_course_availability', 'bw_credits_course_access'];
    }

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_route']);
    }

    public static function enabled(): bool {
        if (defined('BW_LAZY_VIEWS_DISABLED') && BW_LAZY_VIEWS_DISABLED) return false;
        return (bool) apply_filters('bw_lazy_views_enabled', true);
    }

    /* ---------------------------------------------------------
     * Signing
     * --------------------------------------------------------- */

    public static function sign(string $payload): string {
        return hash_hmac('sha256', self::SIG_CONTEXT . $payload, wp_salt('nonce'));
    }

    public static function verify(string $payload, string $sig): bool {
        return $sig !== '' && hash_equals(self::sign($payload), $sig);
    }

    /** Only simple, short scalars — no arrays/objects, no oversized values. */
    public static function clean_atts($atts): array {
        $out = [];
        foreach ((array) $atts as $key => $value) {
            if (!is_scalar($value) || !preg_match('/^[a-z0-9_]{1,40}$/i', (string) $key)) continue;
            $out[(string) $key] = mb_substr((string) $value, 0, 500);
        }
        return $out;
    }

    /* ---------------------------------------------------------
     * Page rendering: placeholder
     * --------------------------------------------------------- */

    /** Real HTML instead of a placeholder: REST route, page builder editor/preview, admin. */
    private static function render_direct(): bool {
        if (self::$rendering || !self::enabled() || is_admin()) return true;

        if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance)) {
            $el = \Elementor\Plugin::$instance;
            if (($el->editor && $el->editor->is_edit_mode()) || ($el->preview && $el->preview->is_preview_mode())) {
                return true;
            }
        }
        return false;
    }

    /**
     * Shortcode wrapper: placeholder on the page, real output in the REST route.
     *
     * @param string $tag  Shortcode name from views()
     * @param mixed  $atts Attributes as passed by the shortcode
     */
    public static function render(string $tag, $atts = []): string {
        $views = self::views();
        if (!isset($views[$tag]) || !is_callable($views[$tag])) return '';

        $atts = self::clean_atts($atts);

        if (in_array($tag, self::course_tags(), true)) {
            // Fix the session now: in the REST route there is no "current post".
            $course_id = BW_Credits_Bookings_MVP::resolve_course_id((int) ($atts['course_id'] ?? 0) ?: (int) ($atts['slot_id'] ?? 0));
            if ($course_id <= 0) return '';
            unset($atts['slot_id']);
            $atts['course_id'] = (string) $course_id;
        }

        if (self::render_direct()) {
            return (string) call_user_func($views[$tag], $atts);
        }

        BW_Credits_Bookings_MVP::ensure_assets();

        $lang    = apply_filters('wpml_current_language', null);
        $payload = wp_json_encode([
            't' => $tag,
            'a' => $atts,
            'l' => is_string($lang) ? $lang : '',
        ]);

        return '<span class="bw-lazy-view" data-bw-view="' . esc_attr($payload) . '" data-bw-sig="' . esc_attr(self::sign($payload)) . '" aria-busy="true"></span>';
    }

    /** Callback factory for add_shortcode(). */
    public static function shortcode_callback(string $tag): callable {
        return static function ($atts = []) use ($tag) {
            return BW_Lazy_Views::render($tag, $atts);
        };
    }

    /* ---------------------------------------------------------
     * REST route: placeholders → HTML
     * --------------------------------------------------------- */

    public static function register_route() {
        register_rest_route('bw-credits/v1', '/views', [
            'methods'             => 'POST',
            // Public: views for guests are allowed as well. What a logged-in
            // user sees is decided by WordPress's cookie + nonce check and the
            // shortcodes themselves (get_current_user_id()).
            'permission_callback' => '__return_true',
            'callback'            => [__CLASS__, 'rest_views'],
        ]);
    }

    public static function rest_views(WP_REST_Request $req) {
        $items = $req->get_param('items');
        if (!is_array($items) || !$items || count($items) > self::MAX_ITEMS) {
            return new WP_REST_Response(['error' => 'invalid request'], 400);
        }

        $views   = self::views();
        $results = [];
        $lang0   = apply_filters('wpml_current_language', null);

        self::$rendering = true;
        try {
            foreach ($items as $item) {
                if (!is_array($item) || !isset($item['id'], $item['p'], $item['s'])) continue;

                $id      = (string) $item['id'];
                $payload = (string) $item['p'];
                if (!preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $id)) continue;
                if (strlen($payload) > self::MAX_PAYLOAD || !self::verify($payload, (string) $item['s'])) {
                    $results[$id] = ['ok' => false];
                    continue;
                }

                $data = json_decode($payload, true);
                $tag  = is_array($data) ? ($data['t'] ?? '') : '';
                if (!is_string($tag) || !isset($views[$tag]) || !is_callable($views[$tag])) {
                    $results[$id] = ['ok' => false];
                    continue;
                }

                self::switch_language((string) ($data['l'] ?? ''));
                $results[$id] = [
                    'ok'   => true,
                    'html' => (string) call_user_func($views[$tag], self::clean_atts($data['a'] ?? [])),
                ];
            }
        } finally {
            self::$rendering = false;
            if (is_string($lang0) && $lang0 !== '') self::switch_language($lang0);
        }

        $response = new WP_REST_Response(['views' => $results]);
        $response->header('Cache-Control', 'no-store, private, max-age=0');
        $response->header('Vary', 'Cookie');
        return $response;
    }

    /** Language of the page the placeholder was created on (WPML), only valid codes. */
    private static function switch_language(string $lang) {
        if ($lang === '' || !preg_match('/^[a-z]{2,3}([_\-][A-Za-z]{2,4})?$/', $lang)) return;
        if (has_action('wpml_switch_language')) {
            do_action('wpml_switch_language', $lang);
        }
    }
}

BW_Lazy_Views::init();
