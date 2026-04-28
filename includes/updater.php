<?php
if (!defined('ABSPATH')) exit;

/**
 * GitHub Releases Updater
 *
 * Wie es funktioniert:
 *  1. Neuen Code committen + pushen
 *  2. git tag v0.5.0 && git push origin v0.5.0
 *  3. Auf GitHub: Releases → "Draft a new release" → Tag auswählen → Changelog eintragen → Publish
 *  4. WordPress zeigt das Update automatisch in "Plugins → Updates" an (Cache: 12h)
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

        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api',                           [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_post_install',                 [$this, 'fix_folder'],  10, 3);
        add_action('upgrader_process_complete',             [$this, 'clear_cache'], 10, 2);
    }

    /* ---------------------------------------------------------
     * GitHub API: neueste Release holen (gecacht 12h)
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
     * Update-Info in WP-Transient injizieren
     * --------------------------------------------------------- */

    public function inject_update($transient) {
        if (empty($transient->checked[$this->slug])) return $transient;

        $release = $this->fetch_release();
        if (!$release) return $transient;

        $remote  = ltrim($release['tag_name'], 'v');
        $current = $transient->checked[$this->slug];

        if (version_compare($remote, $current, '>')) {
            $transient->response[$this->slug] = (object) [
                'slug'        => $this->folder,
                'plugin'      => $this->slug,
                'new_version' => $remote,
                'url'         => 'https://github.com/' . self::REPO,
                'package'     => $release['zipball_url'],
            ];
        }

        return $transient;
    }

    /* ---------------------------------------------------------
     * Plugin-Info für das WP Update-Modal ("View Details")
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
            'last_updated'  => $release['published_at'] ?? '',
            'sections'      => [
                'description' => 'BW Credits + Bookings – WooCommerce Credit-Buchungssystem für Kurse.',
                'changelog'   => nl2br(esc_html($release['body'] ?? 'Keine Changelog-Info vorhanden.')),
            ],
            'download_link' => $release['zipball_url'],
        ];
    }

    /* ---------------------------------------------------------
     * Nach dem Update: Ordner umbenennen
     * GitHub-ZIPs entpacken als "blickwert-bw-credits-booking-{hash}/"
     * → muss zu "bw-credits-booking/" umbenannt werden
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
     * Cache löschen nach erfolgreichem Update
     * --------------------------------------------------------- */

    public function clear_cache($upgrader, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient(self::CACHE_KEY);
        }
    }
}

new BW_GitHub_Updater(BW_CREDITS_BOOKING_FILE);
