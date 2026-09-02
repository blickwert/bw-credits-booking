<?php
if (!defined('ABSPATH')) exit;

/**
 * Settings-Infrastruktur + Admin-Menü
 *
 * Andere Plugin-Teile hängen ihre Unterseiten via BW_Settings::MENU_SLUG ein.
 */

class BW_Settings {

    const MENU_SLUG  = 'bw-credits';
    const CAPABILITY = 'manage_options';

    const OPT_POST_TYPE        = 'bw_slot_post_type';
    const OPT_DEFAULT_CAPACITY = 'bw_default_capacity';
    const OPT_CUTOFF_HOURS     = 'bw_booking_cancel_cutoff_hours';
    const OPT_REMINDER_HOURS   = 'bw_reminder_hours';
    const OPT_AVAILABILITY_CAP = 'bw_availability_cap';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /* ---------------------------------------------------------
     * Getter — überall im Plugin statt hartkodierter Werte nutzen
     * --------------------------------------------------------- */

    public static function get_slot_post_type(): string {
        $pt = (string) get_option(self::OPT_POST_TYPE, 'course_slot');
        return $pt !== '' ? $pt : 'course_slot';
    }

    public static function get_default_capacity(): int {
        return max(0, (int) get_option(self::OPT_DEFAULT_CAPACITY, 10));
    }

    public static function get_cancel_cutoff_hours(): int {
        return max(0, (int) get_option(self::OPT_CUTOFF_HOURS, 24));
    }

    public static function get_reminder_hours(): int {
        return max(0, (int) get_option(self::OPT_REMINDER_HOURS, 24));
    }

    /**
     * Ab wie vielen freien Plätzen "mehr als N Plätze frei" statt der
     * exakten Zahl angezeigt wird.
     */
    public static function get_availability_cap(): int {
        return max(0, (int) get_option(self::OPT_AVAILABILITY_CAP, 5));
    }

    /* ---------------------------------------------------------
     * Menü
     * --------------------------------------------------------- */

    public static function register_menu() {
        add_menu_page(
            'BW Credits',
            'BW Credits',
            self::CAPABILITY,
            self::MENU_SLUG,
            [__CLASS__, 'render_page'],
            'dashicons-tickets-alt',
            56
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Einstellungen',
            'Einstellungen',
            self::CAPABILITY,
            self::MENU_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    /* ---------------------------------------------------------
     * Settings API
     * --------------------------------------------------------- */

    public static function register_settings() {
        $group = 'bw_credits_settings';

        register_setting($group, self::OPT_POST_TYPE, [
            'type'              => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitize_post_type'],
            'default'           => 'course_slot',
        ]);

        foreach ([self::OPT_DEFAULT_CAPACITY, self::OPT_CUTOFF_HOURS, self::OPT_REMINDER_HOURS, self::OPT_AVAILABILITY_CAP] as $key) {
            register_setting($group, $key, [
                'type'              => 'integer',
                'sanitize_callback' => [__CLASS__, 'sanitize_positive_int'],
            ]);
        }

        add_settings_section('bw_general', 'Allgemein', function () {
            echo '<p>Grundeinstellungen für Kurstermine und Buchungen.</p>';
        }, self::MENU_SLUG);

        add_settings_field(self::OPT_POST_TYPE, 'Kurstermin-Inhaltstyp', [__CLASS__, 'field_post_type'], self::MENU_SLUG, 'bw_general');
        add_settings_field(self::OPT_DEFAULT_CAPACITY, 'Standard-Kapazität', [__CLASS__, 'field_default_capacity'], self::MENU_SLUG, 'bw_general');
        add_settings_field(self::OPT_CUTOFF_HOURS, 'Storno-Frist (Stunden)', [__CLASS__, 'field_cutoff_hours'], self::MENU_SLUG, 'bw_general');
        add_settings_field(self::OPT_REMINDER_HOURS, 'Erinnerung (Stunden vorher)', [__CLASS__, 'field_reminder_hours'], self::MENU_SLUG, 'bw_general');
        add_settings_field(self::OPT_AVAILABILITY_CAP, 'Verfügbarkeits-Schwelle', [__CLASS__, 'field_availability_cap'], self::MENU_SLUG, 'bw_general');
    }

    public static function sanitize_post_type($value): string {
        $value = sanitize_key((string) $value);
        return post_type_exists($value) ? $value : 'course_slot';
    }

    public static function sanitize_positive_int($value): int {
        return max(0, (int) $value);
    }

    /* ---------------------------------------------------------
     * Felder
     * --------------------------------------------------------- */

    public static function field_post_type() {
        $current = self::get_slot_post_type();
        $exclude = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation'];

        echo '<select name="' . esc_attr(self::OPT_POST_TYPE) . '">';
        foreach (get_post_types([], 'objects') as $pt) {
            if (in_array($pt->name, $exclude, true)) continue;
            printf(
                '<option value="%s"%s>%s (%s)</option>',
                esc_attr($pt->name),
                selected($current, $pt->name, false),
                esc_html($pt->labels->singular_name ?? $pt->name),
                esc_html($pt->name)
            );
        }
        echo '</select>';
        echo '<p class="description">Welcher Inhaltstyp die Kurstermine enthält. Buchungen, Teilnehmerlisten und Kapazität beziehen sich auf diesen Typ.</p>';

        if (!post_type_exists($current)) {
            echo '<p style="color:#b32d2e"><strong>Achtung:</strong> Der gespeicherte Typ <code>' . esc_html($current) . '</code> ist aktuell nicht registriert.</p>';
        }
    }

    public static function field_default_capacity() {
        printf(
            '<input type="number" min="0" step="1" name="%s" value="%d" class="small-text">',
            esc_attr(self::OPT_DEFAULT_CAPACITY),
            self::get_default_capacity()
        );
        echo '<p class="description">Wird verwendet wenn beim Termin keine eigene Kapazität eingetragen ist.</p>';
    }

    public static function field_cutoff_hours() {
        printf(
            '<input type="number" min="0" step="1" name="%s" value="%d" class="small-text">',
            esc_attr(self::OPT_CUTOFF_HOURS),
            self::get_cancel_cutoff_hours()
        );
        echo '<p class="description">Bis wie viele Stunden vor Kursbeginn Kunden selbst stornieren dürfen.</p>';
    }

    public static function field_reminder_hours() {
        printf(
            '<input type="number" min="0" step="1" name="%s" value="%d" class="small-text">',
            esc_attr(self::OPT_REMINDER_HOURS),
            self::get_reminder_hours()
        );
        echo '<p class="description">Wann die Erinnerungs-E-Mail verschickt wird. 0 = keine Erinnerung.</p>';
    }

    public static function field_availability_cap() {
        printf(
            '<input type="number" min="0" step="1" name="%s" value="%d" class="small-text">',
            esc_attr(self::OPT_AVAILABILITY_CAP),
            self::get_availability_cap()
        );
        echo '<p class="description">Ab wie vielen freien Plätzen nur noch „mehr als N Plätze frei" statt der genauen Zahl angezeigt wird. 0 = immer die genaue Zahl.</p>';
    }

    /* ---------------------------------------------------------
     * Seite
     * --------------------------------------------------------- */

    public static function render_page() {
        if (!current_user_can(self::CAPABILITY)) return;
        ?>
        <div class="wrap">
            <h1>BW Credits – Einstellungen</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('bw_credits_settings');
                do_settings_sections(self::MENU_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

BW_Settings::init();
