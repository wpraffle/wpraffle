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

    return sprintf(
        '<svg class="%s" %s role="img" xmlns="http://www.w3.org/2000/svg">%s<use href="#wpr-%s"></use></svg>',
        esc_attr( $classes ),
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
