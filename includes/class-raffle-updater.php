<?php
/**
 * WPRaffle — GitHub Release Updater
 *
 * Checks GitHub releases for plugin updates and integrates with
 * the WordPress plugin update system.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Updater {

    /**
     * Hook into WordPress update system.
     */
    public function __construct() {
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugins_api_info' ), 10, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
        add_action( 'admin_init', array( $this, 'handle_manual_check' ) );

        // Schedule periodic checks if not already scheduled
        if ( ! wp_next_scheduled( 'wpraffle_check_updates' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'wpraffle_check_updates' );
        }
        add_action( 'wpraffle_check_updates', array( $this, 'refresh_version_cache' ) );
    }

    /**
     * Get the configured GitHub repo, validated against the strict
     * `owner/name` format before use. Falls back to the default if the stored
     * value is malformed (defence-in-depth against a compromised/malicious
     * setting being interpolated into the API URL).
     */
    private function get_repo() {
        $settings = wp_parse_args( get_option( 'wpraffle_update_settings', array() ), array(
            'github_repo' => 'wpraffle/wpraffle',
        ) );
        $repo = $settings['github_repo'];
        if ( ! preg_match( '#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo ) ) {
            return 'wpraffle/wpraffle';
        }
        return $repo;
    }

    /**
     * Whether auto-update is enabled.
     */
    private function auto_update_enabled() {
        $settings = wp_parse_args( get_option( 'wpraffle_update_settings', array() ), array(
            'auto_update' => 1,
        ) );
        return (bool) $settings['auto_update'];
    }

    /**
     * Fetch latest release info from GitHub API.
     */
    private function fetch_latest_release() {
        $repo = $this->get_repo();
        $url  = 'https://api.github.com/repos/' . $repo . '/releases/latest';

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WPRaffle/' . RAFFLE_SYSTEM_VERSION,
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! $body || empty( $body['tag_name'] ) ) {
            return false;
        }

        // Find the zip asset
        $download_url = '';
        if ( ! empty( $body['assets'] ) ) {
            foreach ( $body['assets'] as $asset ) {
                if ( isset( $asset['browser_download_url'] ) &&
                     preg_match( '/\.zip$/', $asset['name'] ) ) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        // Fallback: use the zipball URL
        if ( ! $download_url && ! empty( $body['zipball_url'] ) ) {
            $download_url = $body['zipball_url'];
        }

        return array(
            'version'       => ltrim( $body['tag_name'], 'v' ),
            'download_url'  => $download_url,
            'released_at'   => $body['published_at'] ?? '',
            'changelog'     => $body['body'] ?? '',
            'name'          => $body['name'] ?? $body['tag_name'],
            'url'           => $body['html_url'] ?? '',
        );
    }

    /**
     * Refresh the cached version info.
     */
    public function refresh_version_cache() {
        $release = $this->fetch_latest_release();
        if ( $release ) {
            set_transient( 'wpraffle_latest_version', $release['version'], 12 * HOUR_IN_SECONDS );
            set_transient( 'wpraffle_release_info', $release, 12 * HOUR_IN_SECONDS );
        }
    }

    /**
     * Handle manual "Check for Updates" button.
     */
    public function handle_manual_check() {
        if ( ! isset( $_GET['check_updates'] ) || $_GET['check_updates'] !== '1' ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // SEC-10 FIX: Verify nonce to prevent CSRF
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpraffle_check_updates' ) ) {
            return;
        }

        $this->refresh_version_cache();

        // Also delete site transient so WP re-checks
        delete_site_transient( 'update_plugins' );

        wp_safe_redirect( admin_url( 'admin.php?page=wpraffle-settings&tab=updates&saved=1' ) );
        exit;
    }

    /**
     * Inject update data into WordPress plugin update transient.
     */
    public function check_for_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }

        $release_info = get_transient( 'wpraffle_release_info' );

        // If no cached info, try fetching fresh
        if ( ! $release_info ) {
            $release_info = $this->fetch_latest_release();
            if ( $release_info ) {
                set_transient( 'wpraffle_release_info', $release_info, 12 * HOUR_IN_SECONDS );
                set_transient( 'wpraffle_latest_version', $release_info['version'], 12 * HOUR_IN_SECONDS );
            }
        }

        if ( ! $release_info || empty( $release_info['download_url'] ) ) {
            return $transient;
        }

        // Compare versions
        if ( version_compare( $release_info['version'], RAFFLE_SYSTEM_VERSION, '>' ) ) {
            $plugin_file = plugin_basename( RAFFLE_SYSTEM_PATH . 'raffle-system.php' );

            $obj = new stdClass();
            $obj->slug         = 'raffle-system';
            $obj->new_version  = $release_info['version'];
            $obj->url          = $release_info['url'];
            $obj->package      = $release_info['download_url'];
            $obj->plugin       = $plugin_file;
            $obj->icons        = array();
            $obj->banners      = array();
            $obj->banners_rtl  = array();
            $obj->requires     = '6.0';
            $obj->tested       = '6.7';
            $obj->requires_php = '8.0';

            // Auto-update support
            if ( $this->auto_update_enabled() ) {
                $obj->auto_update = true;
            }

            $transient->response[ $plugin_file ] = $obj;
        }

        return $transient;
    }

    /**
     * Provide plugin info for the WordPress updates screen.
     */
    public function plugins_api_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }
        if ( ! isset( $args->slug ) || $args->slug !== 'raffle-system' ) {
            return $result;
        }

        $release_info = get_transient( 'wpraffle_release_info' );
        if ( ! $release_info ) {
            return $result;
        }

        $info = new stdClass();
        $info->name          = 'WPRaffle';
        $info->slug          = 'raffle-system';
        $info->version       = $release_info['version'];
        $info->author        = '<a href="https://github.com/wpraffle">WPRaffle</a>';
        $info->author_profile = 'https://github.com/wpraffle';
        $info->last_updated  = $release_info['released_at'];
        $info->homepage      = $release_info['url'];
        $info->download_link = $release_info['download_url'];
        $info->requires      = '6.0';
        $info->tested        = '6.7';
        $info->requires_php  = '8.0';
        $info->sections      = array(
            'description' => '<p>A fully-featured WooCommerce raffle & competition system with instant wins, skill questions, postal entries, live draws, and more.</p>',
            'changelog'   => '<pre style="white-space:pre-wrap;">' . esc_html( $release_info['changelog'] ) . '</pre>',
        );

        return $info;
    }

    /**
     * After install, ensure the plugin folder name is correct.
     */
    public function after_install( $response, $hook_extra, $result ) {
        if ( ! isset( $hook_extra['plugin'] ) ) {
            return $response;
        }

        $plugin_file = $hook_extra['plugin'];
        if ( strpos( $plugin_file, 'wpraffle' ) === false && strpos( $plugin_file, 'raffle-system' ) === false ) {
            return $response;
        }

        // Ensure correct folder name
        $desired = dirname( RAFFLE_SYSTEM_PATH ) . '/wpraffle';
        if ( $result['destination'] !== $desired ) {
            $renamed = rename( $result['destination'], $desired );
            if ( $renamed ) {
                $result['destination'] = $desired;
            } else {
                return new WP_Error( 'rename_failed', 'Could not rename plugin folder to "wpraffle". Please rename manually.' );
            }
        }

        // BUG-3 FIX: Return $result (the modified array) not $response (a boolean)
        return $result;
    }
}
