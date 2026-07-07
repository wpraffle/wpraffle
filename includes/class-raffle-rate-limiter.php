<?php
/**
 * WPRaffle — Centralized Rate Limiting System
 *
 * Provides a unified rate limiting mechanism across all raffle actions
 * (purchases, free entries, referrals, OTP verification, etc.)
 * with progressive penalties and admin visibility.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Rate_Limiter {

    /**
     * Default rate limit configurations per action type.
     * Each action has: max_attempts, window_seconds, block_duration_seconds.
     */
    private static function get_limits() {
        return apply_filters( 'raffle_rate_limits', array(
            'purchase'     => array( 'max' => 5,  'window' => 60,   'block' => 300 ),   // 5 per min, then 5min block
            'free_entry'   => array( 'max' => 3,  'window' => 60,   'block' => 600 ),   // 3 per min, then 10min block
            'referral'     => array( 'max' => 10, 'window' => 60,   'block' => 300 ),   // 10 per min, then 5min block
            'otp_verify'   => array( 'max' => 5,  'window' => 300,  'block' => 900 ),   // 5 per 5min, then 15min block
            'otp_resend'   => array( 'max' => 3,  'window' => 300,  'block' => 600 ),   // 3 per 5min, then 10min block
            'entry_list'   => array( 'max' => 10, 'window' => 60,   'block' => 120 ),   // 10 per min, then 2min block
            'ajax_general' => array( 'max' => 20, 'window' => 60,   'block' => 120 ),   // 20 per min general, then 2min
        ) );
    }

    /**
     * Progressive block durations: 1st offense = base, 2nd = 2x, 3rd+ = 4x.
     */
    private static function get_progressive_multiplier( $offense_count ) {
        if ( $offense_count <= 1 ) return 1;
        if ( $offense_count === 2 ) return 2;
        return 4;
    }

    /* ===================================================================
       Core Rate Limiting
       =================================================================== */

    /**
     * Check if an action is rate-limited for a given identifier.
     *
     * @param string $action  Action type (e.g. 'purchase', 'free_entry').
     * @param string $id      Identifier (email, IP, or combination).
     * @return array { 'allowed' => bool, 'retry_after' => int, 'reason' => string }
     */
    public static function check( $action, $id ) {
        $limits = self::get_limits();
        $config = isset( $limits[ $action ] ) ? $limits[ $action ] : $limits['ajax_general'];

        // Check if currently blocked
        $block_key   = 'raffle_block_' . $action . '_' . md5( $id );
        $block_until = get_transient( $block_key );

        if ( $block_until ) {
            $remaining = (int) $block_until - time();
            if ( $remaining > 0 ) {
                return array(
                    'allowed'     => false,
                    'retry_after' => $remaining,
                    'reason'      => sprintf( 'Too many %s attempts. Please wait %d seconds.', $action, $remaining ),
                );
            }
            // Block expired, clean up
            delete_transient( $block_key );
        }

        // Check attempt count within window
        $count_key = 'raffle_count_' . $action . '_' . md5( $id );
        $count     = (int) get_transient( $count_key );

        if ( $count >= $config['max'] ) {
            // Rate limit exceeded — apply progressive blocking
            $offense_key = 'raffle_offenses_' . $action . '_' . md5( $id );
            $offenses    = (int) get_transient( $offense_key );
            $offenses++;
            set_transient( $offense_key, $offenses, DAY_IN_SECONDS );

            $multiplier    = self::get_progressive_multiplier( $offenses );
            $block_time    = $config['block'] * $multiplier;
            $block_until   = time() + $block_time;

            set_transient( $block_key, $block_until, $block_time );

            // Reset counter
            delete_transient( $count_key );

            // Log the block
            self::log_block( $action, $id, $block_time, $offenses );

            return array(
                'allowed'     => false,
                'retry_after' => $block_time,
                'reason'      => sprintf( 'Rate limit exceeded for %s. Blocked for %d seconds (offense #%d).', $action, $block_time, $offenses ),
            );
        }

        return array(
            'allowed'     => true,
            'retry_after' => 0,
            'reason'      => '',
        );
    }

    /**
     * Record a successful attempt (increment counter).
     *
     * @param string $action Action type.
     * @param string $id     Identifier.
     */
    public static function hit( $action, $id ) {
        $limits     = self::get_limits();
        $config     = isset( $limits[ $action ] ) ? $limits[ $action ] : $limits['ajax_general'];
        $count_key  = 'raffle_count_' . $action . '_' . md5( $id );
        $count      = (int) get_transient( $count_key );
        $count++;
        set_transient( $count_key, $count, $config['window'] );
    }

    /**
     * Convenience method: check + return error if blocked.
     * Returns null if allowed, or WP_Error if blocked.
     *
     * @param string $action Action type.
     * @param string $id     Identifier.
     * @return WP_Error|null
     */
    public static function check_or_error( $action, $id ) {
        $result = self::check( $action, $id );
        if ( ! $result['allowed'] ) {
            return new WP_Error( 'rate_limited', $result['reason'] );
        }
        return null;
    }

    /* ===================================================================
       Block Logging
       =================================================================== */

    /**
     * Log a rate limit block for admin visibility.
     */
    private static function log_block( $action, $id, $duration, $offense_count ) {
        $log = get_option( 'raffle_rate_limit_log', array() );

        // Keep last 200 entries
        if ( count( $log ) > 200 ) {
            $log = array_slice( $log, -200 );
        }

        $log[] = array(
            'time'          => current_time( 'mysql' ),
            'action'        => $action,
            'identifier'    => substr( $id, 0, 80 ), // Truncate for storage
            'duration'      => $duration,
            'offense_count' => $offense_count,
            'ip'            => wpraffle_get_client_ip(),
        );

        update_option( 'raffle_rate_limit_log', $log, false );
    }

    /* ===================================================================
       Admin Helpers
       =================================================================== */

    /**
     * Get recent rate limit blocks for admin dashboard.
     *
     * @param int $limit Max entries to return.
     * @return array
     */
    public static function get_block_log( $limit = 50 ) {
        $log = get_option( 'raffle_rate_limit_log', array() );
        return array_slice( array_reverse( $log ), 0, $limit );
    }

    /**
     * Get currently blocked identifiers.
     *
     * @return array
     */
    public static function get_active_blocks() {
        global $wpdb;
        $blocks = array();

        // Query transients for rate limit blocks
        $rows = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_raffle_block_%'
             ORDER BY option_id DESC LIMIT 100"
        );

        foreach ( $rows as $row ) {
            $key   = str_replace( '_transient_', '', $row->option_name );
            $until = (int) get_transient( str_replace( '_transient_', '', $row->option_name ) );
            if ( $until && $until > time() ) {
                $blocks[] = array(
                    'key'         => $key,
                    'expires_at'  => date( 'Y-m-d H:i:s', $until ),
                    'seconds_left'=> $until - time(),
                );
            }
        }

        return $blocks;
    }

    /**
     * Clear a specific rate limit block.
     *
     * @param string $key Transient key (without prefix).
     */
    public static function clear_block( $key ) {
        delete_transient( $key );
    }

    /**
     * Clear all rate limit blocks.
     */
    public static function clear_all_blocks() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_raffle_block_%'
                OR option_name LIKE '_transient_raffle_count_%'
                OR option_name LIKE '_transient_raffle_offenses_%'"
        );
    }

    /* ===================================================================
       AJAX Handlers for Admin
       =================================================================== */

    /**
     * Register admin AJAX handlers.
     */
    public static function register_ajax() {
        add_action( 'wp_ajax_raffle_get_rate_limits', array( __CLASS__, 'ajax_get_rate_limits' ) );
        add_action( 'wp_ajax_raffle_clear_rate_block', array( __CLASS__, 'ajax_clear_rate_block' ) );
        add_action( 'wp_ajax_raffle_clear_all_rate_blocks', array( __CLASS__, 'ajax_clear_all_blocks' ) );
    }

    public static function ajax_get_rate_limits() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        check_ajax_referer( 'raffle_admin_nonce', 'nonce' );

        wp_send_json_success( array(
            'active_blocks' => self::get_active_blocks(),
            'recent_log'    => self::get_block_log( 50 ),
            'limits'        => self::get_limits(),
        ) );
    }

    public static function ajax_clear_rate_block() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        check_ajax_referer( 'raffle_admin_nonce', 'nonce' );

        $key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
        if ( $key ) {
            self::clear_block( $key );
            wp_send_json_success( array( 'message' => 'Block cleared.' ) );
        }
        wp_send_json_error( array( 'message' => 'Invalid key.' ) );
    }

    public static function ajax_clear_all_blocks() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        check_ajax_referer( 'raffle_admin_nonce', 'nonce' );

        self::clear_all_blocks();
        wp_send_json_success( array( 'message' => 'All rate limit blocks cleared.' ) );
    }
}
