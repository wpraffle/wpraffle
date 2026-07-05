<?php
/**
 * WPRaffle Icon Helper
 * Renders inline SVG icons using the wpraffle-icons.svg sprite.
 *
 * Usage: wpr_icon( 'trophy' )
 *        wpr_icon( 'gift', 'wpr-icon--lg wpr-icon--primary' )
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Output an SVG icon from the WPRaffle sprite.
 *
 * @param string $name    Icon ID (without the 'wpr-' prefix), e.g. 'trophy', 'ticket', 'gift'
 * @param string $classes Additional CSS classes, e.g. 'wpr-icon--lg wpr-icon--primary'
 * @param string $title   Optional accessible title
 */
function wpr_icon( $name, $classes = '', $title = '' ) {
    echo wpr_get_icon( $name, $classes, $title );
}

/**
 * Return an SVG icon string from the WPRaffle sprite.
 *
 * @param string $name    Icon ID (without the 'wpr-' prefix)
 * @param string $classes Additional CSS classes
 * @param string $title   Optional accessible title
 * @return string
 */
function wpr_get_icon( $name, $classes = '', $title = '' ) {
    $name    = sanitize_key( $name );
    $classes = 'wpr-icon ' . esc_attr( $classes );
    $title_tag = $title ? '<title>' . esc_html( $title ) . '</title>' : '';
    $aria    = $title ? 'aria-label="' . esc_attr( $title ) . '"' : 'aria-hidden="true"';

    // Defensive inline sizing: mirrors icons.css so icons never render at the
    // SVG's intrinsic (large) size when the stylesheet isn't loaded — e.g. on
    // a content page that only contains the [raffle_charities] shortcode,
    // where the conditional enqueue wouldn't otherwise ship icons.css. The
    // stylesheet still wins via class selectors where both apply (equal
    // specificity, source order), and any theme override still takes effect.
    $size_px = 18; // .wpr-icon base
    if ( strpos( $classes, 'wpr-icon--3xl' ) !== false ) {
        $size_px = 48;
    } elseif ( strpos( $classes, 'wpr-icon--2xl' ) !== false ) {
        $size_px = 36;
    } elseif ( strpos( $classes, 'wpr-icon--xl' ) !== false ) {
        $size_px = 28;
    } elseif ( strpos( $classes, 'wpr-icon--lg' ) !== false ) {
        $size_px = 22;
    } elseif ( strpos( $classes, 'wpr-icon--md' ) !== false ) {
        $size_px = 18;
    } elseif ( strpos( $classes, 'wpr-icon--sm' ) !== false ) {
        $size_px = 14;
    } elseif ( strpos( $classes, 'wpr-icon--xs' ) !== false ) {
        $size_px = 12;
    }
    $style = sprintf( ' style="width:%dpx;height:%dpx;"', $size_px, $size_px );

    return sprintf(
        '<svg class="%s"%s %s role="img" xmlns="http://www.w3.org/2000/svg">%s<use href="#wpr-%s"></use></svg>',
        esc_attr( $classes ),
        $style,
        $aria,
        $title_tag,
        esc_attr( $name )
    );
}

/**
 * Get the WooCommerce currency symbol (falls back to $ if WC not active).
 *
 * @return string
 */
function wpr_currency_symbol() {
    if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
        return get_woocommerce_currency_symbol();
    }
    return '$';
}

/**
 * Format a monetary amount with the WooCommerce currency symbol.
 *
 * @param float $amount   The numeric amount.
 * @param int   $decimals Number of decimal places (default 2).
 * @return string E.g. "£1,234.56"
 */
function wpr_price( $amount, $decimals = 2 ) {
    return wpr_currency_symbol() . number_format( (float) $amount, $decimals, '.', ',' );
}

/**
 * Get the client IP address, honouring proxy headers only when REMOTE_ADDR is
 * a trusted proxy.
 *
 * SECURITY: Any client can spoof X-Forwarded-For / CF-Connecting-IP headers,
 * so we only honour them when REMOTE_ADDR is in the trusted-proxy allowlist
 * (configurable via the `wpraffle_trusted_proxies` option or filter — e.g.
 * your CloudFlare/nginx IPs). On a default install with no proxy configured,
 * this returns REMOTE_ADDR unchanged. Shared across purchase, rate-limiter,
 * and geo-lookup logic so IP-derived decisions are consistent.
 *
 * @return string Valid IP address, or '0.0.0.0' if unavailable.
 */
function wpraffle_get_client_ip() {
    $remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';

    // Trusted-proxy allowlist (comma-separated IPs/CIDRs).
    $trusted = get_option( 'wpraffle_trusted_proxies', '' );
    $trusted = apply_filters( 'wpraffle_trusted_proxies', $trusted );
    $trusted = array_filter( array_map( 'trim', explode( ',', (string) $trusted ) ) );
    $trusted = array_map( 'strtolower', $trusted );

    // Only consult forwarding headers if the direct connection is from a
    // trusted proxy. Otherwise the headers are attacker-controlled.
    $is_trusted_proxy = in_array( strtolower( $remote_addr ), $trusted, true );

    $headers = $is_trusted_proxy
        ? array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' )
        : array();

    foreach ( $headers as $header ) {
        if ( ! empty( $_SERVER[ $header ] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
            // X-Forwarded-For can contain multiple IPs — take the first (client).
            if ( strpos( $ip, ',' ) !== false ) {
                $ip = trim( explode( ',', $ip )[0] );
            }
            if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return $ip;
            }
        }
    }

    // Fall back to the direct connection IP.
    return filter_var( $remote_addr, FILTER_VALIDATE_IP ) ? $remote_addr : '0.0.0.0';
}

/**
 * Output the hidden SVG sprite inline (once per page, in wp_footer or wp_body_open).
 * This avoids CORS issues with external SVG <use> references.
 */
function wpr_output_icon_sprite() {
    static $done = false;
    if ( $done ) return;
    $done = true;
    $sprite_file = RAFFLE_SYSTEM_PATH . 'assets/icons/wpraffle-icons.svg';
    if ( file_exists( $sprite_file ) ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div style="display:none;" id="wpr-icon-sprite">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        readfile( $sprite_file );
        echo '</div>';
    }
}
