<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sends a single, anonymous activation notice to wpraffle.dev so the
 * marketing site can display a unique-install count.
 *
 * Privacy contract — the ping sends ONLY:
 *   - install_id : a random 32-char hex (no relationship to the site)
 *   - event      : the literal string 'activated'
 *   - version    : the plugin version string
 *
 * It NEVER sends the site URL, admin email, WordPress/PHP versions,
 * customer data, or any personal information. The ping is one-shot per
 * install (guarded by wpraffle_activation_ping_sent) and the server
 * dedupes by install_id, so re-activating on the same site never
 * inflates the count. Opt out anytime via Settings → Updates.
 *
 * All failures are silent — a downed endpoint never affects activation
 * or the admin UX (mirrors the updater + geo conventions).
 */
class Raffle_Tracker {

    /** Single receiving endpoint. HTTPS only. */
    const ENDPOINT = 'https://wpraffle.dev/api/activate';

    /**
     * Ensure a stable random install ID exists.
     *
     * Generated once, persisted to wpraffle_install_id, and retained
     * across deactivations (deleted only on full uninstall). The ID is
     * a 32-char hex string with no relationship to the site identity.
     *
     * @return string The install ID.
     */
    public static function ensure_install_id() {
        $id = get_option( 'wpraffle_install_id' );
        if ( ! empty( $id ) ) {
            return $id;
        }
        // random_bytes() is cryptographically secure; bin2hex() → 32 chars.
        $id = bin2hex( random_bytes( 16 ) );
        update_option( 'wpraffle_install_id', $id, false );
        return $id;
    }

    /**
     * Send the one-shot activation ping.
     *
     * Called from Raffle_Activator::activate(). Idempotent and
     * fire-and-forget: never throws, never logs, never admin-notices.
     */
    public static function notify_activation() {
        // Respect the opt-out flag (default off → ping proceeds).
        if ( get_option( 'wpraffle_tracking_opted_out' ) ) {
            return;
        }
        // Never re-send on the same install.
        if ( get_option( 'wpraffle_activation_ping_sent' ) ) {
            return;
        }

        $install_id = self::ensure_install_id();

        $payload = wp_json_encode( array(
            'install_id' => $install_id,
            'event'      => 'activated',
            'version'    => defined( 'RAFFLE_SYSTEM_VERSION' ) ? RAFFLE_SYSTEM_VERSION : '0.0.0',
        ) );

        if ( false === $payload ) {
            return;
        }

        // blocking => false: activation isn't slowed by the ping.
        // The server-side dedup by install_id is the real accuracy guarantee.
        wp_remote_post( self::ENDPOINT, array(
            'timeout'   => 8,
            'blocking'  => false,
            'sslverify' => true,
            'headers'   => array(
                'Content-Type' => 'application/json',
                'User-Agent'   => 'WPRaffle/' . ( defined( 'RAFFLE_SYSTEM_VERSION' ) ? RAFFLE_SYSTEM_VERSION : '0.0.0' ),
            ),
            'body'      => $payload,
        ) );

        // Mark as sent optimistically so we never retry-spam. If the ping
        // never lands (network down), the count is simply lower — never
        // inflated. Uniqueness is enforced server-side regardless.
        update_option( 'wpraffle_activation_ping_sent', 1 );
    }
}
