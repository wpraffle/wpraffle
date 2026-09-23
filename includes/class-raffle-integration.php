<?php
/**
 * Stable integration surface for WPRaffle-aware themes and builders.
 *
 * @package WPRaffle
 * @since 1.4.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Return a capability manifest so themes can integrate without probing
 * internal classes/files that may change between releases.
 *
 * @return array
 */
function wpraffle_get_integration_manifest() {
    static $manifest = null;
    if ( null !== $manifest ) { return $manifest; }

    $widget_dir = RAFFLE_SYSTEM_PATH . 'includes/elementor-widgets/';
    $widgets = array();
    foreach ( glob( $widget_dir . 'class-widget-*.php' ) ?: array() as $file ) {
        $widgets[] = str_replace( array( 'class-widget-', '.php' ), '', basename( $file ) );
    }
    sort( $widgets );

    $manifest = array(
        'product'        => 'WPRaffle',
        'version'        => defined( 'RAFFLE_SYSTEM_VERSION' ) ? RAFFLE_SYSTEM_VERSION : '',
        'api_version'    => '1.0',
        'theme_minimum'  => '1.4.0',
        'features'       => array(
            'raffle_context'       => true,
            'elementor_widgets'    => count( $widgets ),
            'elementor_dynamic_tag'=> true,
            'instant_wins'         => class_exists( 'Raffle_Instant_Wins' ),
            'featured_winners'     => class_exists( 'Raffle_Featured_Winners' ),
            'live_draw'            => class_exists( 'Raffle_Live_Draw' ),
            'charity'              => class_exists( 'Raffle_Charity' ),
        ),
        'elementor_widgets' => $widgets,
        'shortcodes' => array(
            'raffle', 'raffle_list', 'raffle_ended_list', 'raffle_entry_list',
            'raffle_live_draw', 'raffle_lookup', 'raffle_refer', 'raffle_charities',
        ),
    );
    return apply_filters( 'wpraffle_integration_manifest', $manifest );
}

/**
 * Resolve a raffle and common computed values through a stable public helper.
 * Accepts a raffle id, WooCommerce product id, or current product context.
 *
 * @param int $raffle_id Optional raffle id.
 * @param int $product_id Optional product id.
 * @return array|false
 */
function wpraffle_get_raffle_context( $raffle_id = 0, $product_id = 0 ) {
    $raffle_id = absint( $raffle_id );
    $product_id = absint( $product_id );
    if ( ! $raffle_id ) {
        if ( ! $product_id ) { $product_id = get_the_ID(); }
        if ( $product_id ) { $raffle_id = absint( get_post_meta( $product_id, '_raffle_id', true ) ); }
    }
    if ( ! $raffle_id ) { return false; }

    if ( function_exists( 'wpraffle_get_raffle' ) ) {
        $raffle = wpraffle_get_raffle( $raffle_id );
    } else {
        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d", $raffle_id ) );
    }
    if ( ! $raffle ) { return false; }

    $total = max( 0, (int) $raffle->total_tickets );
    $sold = max( 0, (int) $raffle->sold_tickets );
    $remaining = max( 0, $total - $sold );
    $progress = $total > 0 ? min( 100, round( ( $sold / $total ) * 100 ) ) : 0;
    $wc_product_id = isset( $raffle->wc_product_id ) ? absint( $raffle->wc_product_id ) : 0;

    return apply_filters( 'wpraffle_raffle_context', array(
        'raffle'            => $raffle,
        'raffle_id'         => (int) $raffle->id,
        'product_id'        => $wc_product_id,
        'product_url'       => $wc_product_id ? get_permalink( $wc_product_id ) : '',
        'packages'          => json_decode( (string) $raffle->packages, true ) ?: array(),
        'progress'          => $progress,
        'remaining'         => $remaining,
        'max_tickets'       => isset( $raffle->max_tickets_per_user ) ? (int) $raffle->max_tickets_per_user : 100,
        'cash_alternative'  => ! empty( $raffle->enable_cash_alternative ) ? (float) $raffle->cash_alternative_amount : 0,
    ), $raffle );
}

/**
 * Return a lightweight operational health snapshot for themes and support.
 * This avoids themes querying WPRaffle tables or internal classes directly.
 *
 * @return array
 */
function wpraffle_get_health_status() {
    global $wpdb;

    $raffles_table = $wpdb->prefix . 'raffles';
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    $raffles_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $raffles_table ) ) === $raffles_table;
    $tickets_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tickets_table ) ) === $tickets_table;

    $active = 0;
    $total  = 0;
    if ( $raffles_exists ) {
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$raffles_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // Status values vary across older installs, so use the close date as a
        // version-tolerant proxy for currently marketable competitions.
        $active = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$raffles_table} WHERE status IN ('active','scheduled') AND (draw_date IS NULL OR draw_date >= UTC_TIMESTAMP())" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    $wc_pages = array();
    if ( function_exists( 'wc_get_page_id' ) ) {
        foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $key ) {
            $id = (int) wc_get_page_id( $key );
            $wc_pages[ $key ] = array(
                'id' => $id,
                'ok' => $id > 0 && 'publish' === get_post_status( $id ),
            );
        }
    }

    return apply_filters( 'wpraffle_health_status', array(
        'version'             => defined( 'RAFFLE_SYSTEM_VERSION' ) ? RAFFLE_SYSTEM_VERSION : '',
        'database'            => array(
            'raffles_table' => $raffles_exists,
            'tickets_table' => $tickets_exists,
        ),
        'competitions'        => array(
            'total'  => $total,
            'active' => $active,
        ),
        'woocommerce_pages'   => $wc_pages,
        'elementor'           => array(
            'active'  => did_action( 'elementor/loaded' ) > 0,
            'widgets' => count( wpraffle_get_integration_manifest()['elementor_widgets'] ?? array() ),
        ),
    ) );
}

