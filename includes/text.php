<?php
if (!defined('ABSPATH')) exit;

/**
 * Central text catalogue.
 *
 * Separates three layers that otherwise tend to get mixed together:
 *   Structure → template file (added in v0.13.0)
 *   Wording   → this catalogue, overridable in the admin
 *   Language  → gettext and WPML
 *
 * Resolution order: shortcode attribute → admin override → translated default.
 *
 * A new text only needs one entry in catalogue(). It then automatically
 * appears on the settings page, in the WPML registration, and in the
 * generated .pot file.
 */

class BW_Text {

    const DOMAIN        = 'bw-credits-booking';
    const OPT_OVERRIDES = 'bw_texts';

    /** Group => heading on the settings page */
    const GROUPS = [
        'booking'      => 'Booking and Cancelling',
        'availability' => 'Availability',
        'balance'      => 'Credit Balance',
        'credits'      => 'Credit Balance Details',
        'bookings'     => 'Booking List',
        'course_list'  => 'Session List',
        'access'       => 'Access Details',
        'overview'     => 'Account Overview',
        'error'        => 'Error Messages',
        'order_email'  => 'Order Confirmation Email',
    ];

    /**
     * Key => [default text, description, group]
     *
     * Placeholders use curly braces so a malformed admin edit can't
     * trigger an error — unlike printf-style formats.
     */
    public static function catalogue(): array {
        return [
            /* --- Booking and Cancelling --- */
            'booking.button.book' => [
                'Book course (1 credit)', 'Label of the booking button', 'booking',
            ],
            'booking.button.cancel' => [
                'Cancel booking', 'Label shown when the session is booked', 'booking',
            ],
            'booking.button.cancel_short' => [
                'Cancel', 'Short label in the booking list', 'booking',
            ],
            'booking.note.login' => [
                'Please log in to book.', 'Note for visitors who are not logged in', 'booking',
            ],
            'booking.note.past' => [
                'This session is over.', 'The session is in the past', 'booking',
            ],
            'booking.note.full' => [
                'This session is fully booked.', 'No spots left', 'booking',
            ],
            'booking.note.booked' => [
                'You are booked for this session.', 'Booked, but the cancellation deadline has passed', 'booking',
            ],
            'booking.note.no_credits' => [
                'You have no credits.', 'Credit balance used up, shown before the top-up link — also fits customers with no prior booking', 'booking',
            ],
            'booking.link.topup' => [
                'Top up now', 'Label of the link to the shop page', 'booking',
            ],

            /* --- Availability --- */
            'availability.free' => [
                '{free} spots available', 'Placeholder {free} is replaced with the number — up to the threshold from the settings', 'availability',
            ],
            'availability.more_than' => [
                'more than {n} spots available', 'Used from the settings threshold onward instead of the exact number', 'availability',
            ],
            'availability.full' => [
                'Fully booked', 'When no spots are left', 'availability',
            ],

            /* --- Credit Balance --- */
            'balance.label' => [
                'Available credits:', 'Label before the number', 'balance',
            ],
            'balance.empty' => [
                'Your credit balance is used up.', 'Note shown at zero credits', 'balance',
            ],
            'balance.count.one' => [
                'credit available', 'Singular form in the account overview', 'balance',
            ],
            'balance.count.many' => [
                'credits available', 'Plural form in the account overview', 'balance',
            ],

            /* --- Credit Balance Details --- */
            'credits.empty' => [
                'You currently have no credits.', 'When no credits are available', 'credits',
            ],
            'credits.source.purchase' => [
                'Purchase', 'Origin: bought through the shop', 'credits',
            ],
            'credits.source.membership' => [
                'Membership', 'Origin: from a membership', 'credits',
            ],
            'credits.source.manual' => [
                'Manual credit', 'Origin: manually credited', 'credits',
            ],
            'credits.expired' => [
                'expired', 'Status of an expired credit', 'credits',
            ],
            'credits.unlimited' => [
                'valid indefinitely', 'Credit without an expiry date', 'credits',
            ],
            'credits.valid_until' => [
                'valid until {date}', 'Credit with an expiry date', 'credits',
            ],

            /* --- Booking List --- */
            'bookings.empty' => [
                'No bookings yet.', 'Empty booking list', 'bookings',
            ],
            'bookings.login_required' => [
                'Please log in.', 'Booking list for visitors who are not logged in', 'bookings',
            ],
            'bookings.status.booked' => [
                'Booked', 'Status of an active booking', 'bookings',
            ],
            'bookings.status.cancelled' => [
                'Cancelled', 'Status of a cancelled booking', 'bookings',
            ],
            'bookings.status.pending' => [
                'Pending', 'Interim status while booking is in progress', 'bookings',
            ],
            'bookings.status.no_show' => [
                'No-show', 'Marked by the studio as a no-show', 'bookings',
            ],

            /* --- Session List --- */
            'course_list.empty' => [
                'No sessions are currently scheduled.', 'No sessions found', 'course_list',
            ],
            'course_list.filter.all' => [
                'All', 'First option in the filter dropdowns', 'course_list',
            ],
            'course_list.filter.submit' => [
                'Filter', 'Button in the filter form', 'course_list',
            ],
            'course_list.filter.reset' => [
                'Reset', 'Link to clear the filters', 'course_list',
            ],
            'course_list.filter.type' => [
                'Course type', 'Label of the filter for course_type', 'course_list',
            ],
            'course_list.filter.level' => [
                'Level', 'Label of the filter for course_level', 'course_list',
            ],
            'course_list.filter.lang' => [
                'Language', 'Label of the filter for course_lang', 'course_list',
            ],

            /* --- Access Details --- */
            'access.title' => [
                'Access details', 'Heading above the meeting link and notes', 'access',
            ],
            'access.link' => [
                'Join online session', 'Label of the meeting link', 'access',
            ],

            /* --- Account Overview --- */
            'overview.heading.courses' => [
                'My courses', 'Heading above the booking list in the account', 'overview',
            ],
            'overview.upcoming.label' => [
                'Upcoming courses', 'Heading above the course list in the account', 'overview',
            ],
            'overview.link.courses' => [
                'View course sessions', 'Link to the session list', 'overview',
            ],
            'overview.link.orders' => [
                'My orders', 'Link to the WooCommerce orders', 'overview',
            ],
            'overview.link.topup' => [
                'Top up credits', 'Link to the shop page', 'overview',
            ],

            /* --- Error Messages ---
             * These reach the customer directly in the message line via
             * the REST API.
             */
            'error.no_credits' => [
                "You don't have any credits left.", 'Booking attempt with no credits available', 'error',
            ],
            'error.full' => [
                'This session is fully booked.', 'Booking attempt at full capacity', 'error',
            ],
            'error.slot_past' => [
                'This session is in the past.', 'Booking attempt for a past session', 'error',
            ],
            'error.already_booked' => [
                'You have already booked this session.', 'Duplicate booking', 'error',
            ],
            'error.booking_not_found' => [
                'Booking not found.', 'Cancellation of an unknown booking', 'error',
            ],
            'error.not_active' => [
                'This booking has already been cancelled.', 'Cancellation of an already-cancelled booking', 'error',
            ],
            'error.cutoff_passed' => [
                'The cancellation deadline has passed.', 'Cancellation attempt after the deadline', 'error',
            ],
            'error.slot_invalid' => [
                'This session is not available.', 'Session is missing or not published', 'error',
            ],
            'error.capacity_missing' => [
                'No capacity is set for this session.', 'Capacity is missing or zero', 'error',
            ],
            'error.slot_time_missing' => [
                'This session is missing a start time.', 'start_datetime not set', 'error',
            ],
            'error.retry' => [
                "That didn't work. Please try again.", 'Concurrent access, retry needed', 'error',
            ],
            'error.generic' => [
                'The action could not be completed.', 'Unexpected error while booking or cancelling', 'error',
            ],

            /* --- Order Confirmation Email --- */
            'order_email.heading' => [
                'Your credit balance has been topped up', 'Heading in the WooCommerce order email when credits were added', 'order_email',
            ],
            'order_email.body' => [
                "{credits_added} credits have been added to your account. Current balance: {credits_remaining}.\n\nManage your credits and bookings here: {account_link}",
                'Text in the WooCommerce order email, right after the order summary', 'order_email',
            ],
        ];
    }

    /* ---------------------------------------------------------
     * Resolution
     * --------------------------------------------------------- */

    public static function get(string $key, array $vars = []): string {
        $entry = self::catalogue()[$key] ?? null;

        if ($entry === null) {
            // A missing key must not break the page
            return '';
        }

        $overrides = self::overrides();
        $text      = isset($overrides[$key]) && $overrides[$key] !== ''
            ? self::translate_override($key, $overrides[$key])
            // Runtime lookup happens via the text value; the .pot is
            // generated from the catalogue, see tools/make-pot.php
            : __($entry[0], self::DOMAIN);

        return $vars ? self::fill($text, $vars) : $text;
    }

    private static function fill(string $text, array $vars): string {
        $map = [];
        foreach ($vars as $name => $value) {
            $map['{' . $name . '}'] = (string) $value;
        }
        return strtr($text, $map);
    }

    public static function overrides(): array {
        $stored = get_option(self::OPT_OVERRIDES, []);
        return is_array($stored) ? $stored : [];
    }

    public static function default_for(string $key): string {
        return self::catalogue()[$key][0] ?? '';
    }

    /* ---------------------------------------------------------
     * WPML
     * --------------------------------------------------------- */

    public static function init() {
        add_action('init', [__CLASS__, 'load_textdomain']);
        add_action('init', [__CLASS__, 'register_wpml_strings'], 20);
    }

    public static function load_textdomain() {
        load_plugin_textdomain(
            self::DOMAIN,
            false,
            dirname(plugin_basename(BW_CREDITS_BOOKING_FILE)) . '/languages'
        );
    }

    /** Every catalogue entry is registered as a WPML string. */
    public static function register_wpml_strings() {
        if (!has_action('wpml_register_single_string')) return;

        $overrides = self::overrides();

        foreach (self::catalogue() as $key => $entry) {
            $value = $overrides[$key] ?? $entry[0];
            do_action('wpml_register_single_string', 'BW Credits Texte', $key, $value);
        }
    }

    private static function translate_override(string $key, string $value): string {
        if (!has_filter('wpml_translate_single_string')) return $value;

        return (string) apply_filters(
            'wpml_translate_single_string', $value, 'BW Credits Texte', $key
        );
    }
}

BW_Text::init();

/**
 * Shorthand for use in code and templates.
 *
 *   bw_text('booking.note.full')
 *   bw_text('credits.valid_until', ['date' => '31.12.2026'])
 */
function bw_text(string $key, array $vars = []): string {
    return BW_Text::get($key, $vars);
}
