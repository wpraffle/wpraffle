<?php
/**
 * Cache-plugin compatibility — flushes page caches when a raffle's state
 * changes (draw / sellout / extend / relist) so visitors never see a stale
 * "live" raffle after it has ended. Supports W3TC, WP Super Cache, WP Fastest
 * Cache, WP Rocket, and LiteSpeed. Mirrors the wpgenie rival's pattern.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Compat_Cache extends Raffle_Compatibility {

    public static function name() {
        return __( 'Page cache (W3TC / WPSC / Rocket / LiteSpeed)', 'wpraffle' );
    }

    public static function is_available() {
        return function_exists( 'w3tc_flush_all' )
            || function_exists( 'wp_cache_clear_cache' )
            || function_exists( 'wpfc_clear_post_cache_by_id' )
            || function_exists( 'rocket_clean_post' )
            || class_exists( 'LiteSpeed\Purge' ) || defined( 'LSCWP_V' );
    }

    public function register_hooks() {
        // Hook the common "raffle state changed" actions the plugin fires.
        add_action( 'raffle_draw_completed', array( $this, 'flush_for_raffle' ), 99, 1 );
        add_action( 'wpraffle_raffle_failed', array( $this, 'flush_for_raffle' ), 99, 1 );
        add_action( 'wpraffle_raffle_extended', array( $this, 'flush_for_raffle' ), 99, 1 );
        add_action( 'wpraffle_raffle_relisted', array( $this, 'flush_for_raffle' ), 99, 1 );
        // Sellout is detected at ticket-generation time; flush there too.
        add_action( 'wpraffle_raffle_soldout', array( $this, 'flush_for_raffle' ), 10, 1 );
    }

    /**
     * Flush the caches for a raffle's product page (and a site-wide flush as a
     * fallback where per-URL flushing isn't available).
     *
     * @param int $raffle_id
     */
    public function flush_for_raffle( $raffle_id ) {
        $raffle_id = absint( $raffle_id );
        $post_id   = 0;
        if ( $raffle_id ) {
            global $wpdb;
            $post_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT wc_product_id FROM {$wpdb->prefix}raffles WHERE id = %d", $raffle_id ) );
        }

        // W3 Total Cache.
        if ( function_exists( 'w3tc_flush_post' ) && $post_id ) {
            w3tc_flush_post( $post_id );
        } elseif ( function_exists( 'w3tc_flush_all' ) ) {
            w3tc_flush_all();
        }

        // WP Super Cache.
        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            wp_cache_clear_cache();
        }

        // WP Fastest Cache.
        if ( function_exists( 'wpfc_clear_post_cache_by_id' ) && $post_id ) {
            wpfc_clear_post_cache_by_id( $post_id );
        }

        // WP Rocket.
        if ( function_exists( 'rocket_clean_post' ) && $post_id ) {
            rocket_clean_post( $post_id );
        }

        // LiteSpeed Cache.
        if ( $post_id && class_exists( 'LiteSpeed\Purge' ) ) {
            do_action( 'litespeed_purge_post', $post_id );
        } elseif ( $post_id && has_action( 'litespeed_purge_post' ) ) {
            do_action( 'litespeed_purge_post', $post_id );
        }
    }
}
