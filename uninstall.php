<?php
/**
 * WPRaffle Uninstall Handler
 *
 * Cleans up all plugin data when the plugin is deleted via WordPress admin.
 * This file is called automatically by WordPress during plugin deletion.
 *
 * @package WPRaffle
 * @since 1.0.0
 */

// Prevent direct access and ensure WordPress is handling the uninstall
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Only delete data if the admin has opted in via the plugin settings.
 * This prevents accidental data loss during reinstallation.
 */
$delete_data = get_option( 'wpraffle_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
    return;
}

global $wpdb;

// ── Drop custom database tables ──
$tables = array(
    $wpdb->prefix . 'raffles',
    $wpdb->prefix . 'raffle_purchases',
    $wpdb->prefix . 'raffle_tickets',
    $wpdb->prefix . 'raffle_instant_wins',
    $wpdb->prefix . 'raffle_prizes',
    $wpdb->prefix . 'raffle_referrals',
    $wpdb->prefix . 'raffle_reservations',
    $wpdb->prefix . 'raffle_audit_log',
    $wpdb->prefix . 'raffle_templates',
    $wpdb->prefix . 'raffle_free_entries',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// ── Delete plugin options ──
$options = array(
    'raffle_system_version',
    'raffle_system_wc_product_id',
    'raffle_system_db_migrated_v1',
    'raffle_system_db_migrated_v2',
    'raffle_system_db_migrated_v3',
    'raffle_system_db_migrated_v4',
    'raffle_system_db_migrated_v5',
    'raffle_auto_fix_duplicates',
    'raffle_rate_limit_log',
    'wpraffle_pages',
    'wpraffle_settings',
    'wpraffle_email_settings',
    'wpraffle_shortcode_settings',
    'wpraffle_update_settings',
    'wpraffle_delete_data_on_uninstall',
    // Anonymous activation tracker (see Raffle_Tracker).
    'wpraffle_install_id',
    'wpraffle_activation_ping_sent',
    'wpraffle_tracking_opted_out',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// ── Delete shadow WooCommerce product ──
$shadow_product_id = get_option( 'raffle_system_wc_product_id' );
if ( $shadow_product_id ) {
    wp_delete_post( $shadow_product_id, true );
}

// ── Clear transients ──
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_raffle_%' OR option_name LIKE '_transient_timeout_raffle_%' OR option_name LIKE '_transient_wpraffle_%' OR option_name LIKE '_transient_timeout_wpraffle_%'"
);

// ── Unschedule cron events ──
$cron_hooks = array(
    'raffle_system_auto_draw_cron',
    'raffle_cleanup_reservations',
    'raffle_draw_reminder_cron',
    'wpraffle_check_updates',
);

foreach ( $cron_hooks as $hook ) {
    $timestamp = wp_next_scheduled( $hook );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, $hook );
    }
}

// ── Delete auto-created pages ──
$pages = get_option( 'wpraffle_pages', array() );
if ( is_array( $pages ) ) {
    foreach ( $pages as $page_id ) {
        if ( $page_id && get_post( $page_id ) ) {
            wp_delete_post( $page_id, true );
        }
    }
}

// ── Clean up post meta from WooCommerce products ──
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_raffle_id'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_raffle_tickets_generated'" );

// ── Flush rewrite rules ──
flush_rewrite_rules();
