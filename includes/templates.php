<?php
if (!defined('ABSPATH')) exit;

/**
 * Template loader following the WooCommerce convention.
 *
 * Search order: child theme → parent theme → plugin. Separates the
 * structure (here) from the wording (includes/text.php, since v0.12.0) —
 * a theme override thus only sets the layout, never the wording.
 */

class BW_Templates {

    /**
     * Known templates: relative path => description.
     * A single source for the loader and the BW Credits → Templates status page.
     */
    public static function registry(): array {
        return [
            'course_list/course_list.php'               => __('Session list — [bw_credits_course_list]', 'bw-credits-booking'),
            'course_availability/course_availability.php' => __('Available spots — [bw_credits_course_availability]', 'bw-credits-booking'),
            'course_access/course_access.php'           => __('Access details — [bw_credits_course_access]', 'bw-credits-booking'),
            'course_booking/course_booking.php'         => __('Book/Cancel — [bw_credits_course_booking]', 'bw-credits-booking'),
            'user_balance/user_balance.php'             => __('Credit balance — [bw_credits_user_balance]', 'bw-credits-booking'),
            'user_credits/user_credits.php'             => __('Credit balance in detail — [bw_credits_user_credits]', 'bw-credits-booking'),
            'user_bookings/user_bookings.php'           => __('Booking list — [bw_credits_user_bookings]', 'bw-credits-booking'),
            'view_overview/view_overview.php'           => __('Account overview — [bw_credits_view_overview]', 'bw-credits-booking'),
        ];
    }

    public static function plugin_path(string $name): string {
        return plugin_dir_path(BW_CREDITS_BOOKING_FILE) . 'templates/' . $name;
    }

    /** Version from a template file's @version header, if present. */
    public static function file_version(string $path): ?string {
        if (!is_readable($path)) return null;

        // Enough to reach the end of the docblock — templates are short enough
        $head = file_get_contents($path, false, null, 0, 4096);
        if ($head === false) return null;

        return preg_match('/@version\s+([0-9][0-9.]*)/', $head, $m) ? $m[1] : null;
    }
}

/**
 * Path to a template — theme override if present, otherwise the
 * plugin's own copy under templates/.
 */
function bw_locate_template(string $name): string {
    $found = locate_template(['bw-credits-booking/' . $name]);

    if (!$found) {
        $found = BW_Templates::plugin_path($name);
    }

    return apply_filters('bw_locate_template', $found, $name);
}

/**
 * Includes a template. $args is extracted into local variables, exactly
 * like WooCommerce does — the templates document their expected
 * variables in an @var block.
 */
function bw_get_template(string $name, array $args = []): void {
    $file = bw_locate_template($name);
    if (!is_readable($file)) return;

    extract($args, EXTR_SKIP);
    include $file;
}
