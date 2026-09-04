<?php
if (!defined('ABSPATH')) exit;

/**
 * GitHub Releases Updater
 *
 * How it works:
 *  1. Commit + push the new code
 *  2. git tag v0.5.0 && git push origin v0.5.0
 *  3. On GitHub: Releases → "Draft a new release" → select the tag → enter the changelog → Publish
 *  4. WordPress shows the update automatically under "Plugins → Updates" (cache: 12h)
 */

class BW_GitHub_Updater {

    private const REPO      = 'blickwert/bw-credits-booking';
    private const API_URL   = 'https://api.github.com/repos/blickwert/bw-credits-booking/releases/latest';
    private const CACHE_KEY = 'bw_cb_github_release';
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    private string $slug;   // bw-credits-booking/bw-credits-booking.php
    private string $folder; // bw-credits-booking
    private ?array $release = null;

    public function __construct(string $plugin_file) {
        $this->slug   = plugin_basename($plugin_file);
        $this->folder = dirname($this->slug);

        // Only register the update hooks in the admin — prevents conflicts
        // with WooCommerce Helper, which processes the same transient in the admin
        if (!is_admin()) return;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api',                           [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_post_install',                 [$this, 'fix_folder'],  10, 3);
        add_action('upgrader_process_complete',             [$this, 'clear_cache'], 10, 2);
    }

    /* ---------------------------------------------------------
     * GitHub API: fetch the latest release (cached for 12h)
     * --------------------------------------------------------- */

    private function fetch_release(): ?array {
        if ($this->release !== null) return $this->release;

        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            $this->release = $cached;
            return $this->release;
        }

        $resp = wp_remote_get(self::API_URL, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/vnd.github.v3+json'],
        ]);

        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($data['tag_name'])) return null;

        set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);
        $this->release = $data;
        return $this->release;
    }

    /* ---------------------------------------------------------
     * Inject update info into the WP transient
     *
     * WooCommerce Helper iterates over the same transient and expects
     * complete objects with all standard WP fields. Plugins must be
     * listed in either response[] OR no_update[] — never in neither.
     * --------------------------------------------------------- */

    public function inject_update($transient) {
        if (!is_object($transient)) return $transient;
        if (empty($transient->checked) || !is_array($transient->checked)) return $transient;
        if (!array_key_exists($this->slug, $transient->checked)) return $transient;

        $release = $this->fetch_release();
        if (!$release) return $transient;

        $remote  = ltrim($release['tag_name'], 'v');
        $current = $transient->checked[$this->slug];

        // A complete update object with all standard WP fields
        $obj = (object) [
            'id'           => 'github.com/' . self::REPO,
            'slug'         => $this->folder,
            'plugin'       => $this->slug,
            'new_version'  => $remote,
            'url'          => 'https://github.com/' . self::REPO,
            'package'      => $release['zipball_url'],
            'icons'        => [],
            'banners'      => [],
            'banners_rtl'  => [],
            'requires'     => '6.0',
            'tested'       => '6.7',
            'requires_php' => '7.4',
        ];

        if (version_compare($remote, $current, '>')) {
            $transient->response[$this->slug] = $obj;
            unset($transient->no_update[$this->slug]);
        } else {
            // No update available — list the plugin in no_update anyway
            // so WooCommerce Helper finds the entry and doesn't bail out
            $transient->no_update[$this->slug] = $obj;
            unset($transient->response[$this->slug]);
        }

        return $transient;
    }

    /* ---------------------------------------------------------
     * Plugin info for the WP update modal ("View Details")
     * --------------------------------------------------------- */

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') return $result;
        if (($args->slug ?? '') !== $this->folder) return $result;

        $release = $this->fetch_release();
        if (!$release) return $result;

        return (object) [
            'name'          => 'BW Credits + Bookings',
            'slug'          => $this->folder,
            'version'       => ltrim($release['tag_name'], 'v'),
            'author'        => 'Blickwert',
            'homepage'      => 'https://github.com/' . self::REPO,
            'requires'      => '6.0',
            'tested'        => '6.7',
            'requires_php'  => '7.4',
            'last_updated'  => $release['published_at'] ?? '',
            'sections'      => [
                'description' => __('BW Credits + Bookings – a WooCommerce credit-based booking system for courses.', 'bw-credits-booking'),
                'changelog'   => nl2br(esc_html($release['body'] ?? __('No changelog information available.', 'bw-credits-booking'))),
            ],
            'download_link' => $release['zipball_url'],
        ];
    }

    /* ---------------------------------------------------------
     * After the update: rename the folder
     * GitHub ZIPs extract as "blickwert-bw-credits-booking-{hash}/"
     * → must be renamed to "bw-credits-booking/"
     * --------------------------------------------------------- */

    public function fix_folder($response, $hook_extra, $result) {
        global $wp_filesystem;

        if (($hook_extra['plugin'] ?? '') !== $this->slug) return $response;

        $correct = WP_PLUGIN_DIR . '/' . $this->folder;
        $wp_filesystem->move($result['destination'], $correct);
        $result['destination'] = $correct;

        return $result;
    }

    /* ---------------------------------------------------------
     * Clear the cache after a successful update
     * --------------------------------------------------------- */

    public function clear_cache($upgrader, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient(self::CACHE_KEY);
        }
    }
}

new BW_GitHub_Updater(BW_CREDITS_BOOKING_FILE);
