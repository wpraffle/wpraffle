<?php
/**
 * Polylang compatibility — mirrors the WPML adapter using Polylang's API so
 * translated products resolve to the same raffle.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Compat_Polylang extends Raffle_Compatibility {

    public static function name() {
        return __( 'Polylang', 'wpraffle' );
    }

    public static function is_available() {
        return function_exists( 'pll_get_post_language' ) || defined( 'POLYLANG_VERSION' );
    }

    public function register_hooks() {
        add_filter( 'wpraffle_resolve_product_raffle_id', array( $this, 'resolve_original_raffle' ), 10, 2 );
    }

    public function resolve_original_raffle( $raffle_id, $product_id ) {
        if ( $raffle_id ) {
            return $raffle_id;
        }
        if ( ! function_exists( 'pll_get_post' ) ) {
            return $raffle_id;
        }
        // Resolve to the default-language version of the product.
        $default = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
        if ( ! $default ) {
            return $raffle_id;
        }
        $original_id = pll_get_post( $product_id, $default );
        if ( $original_id && $original_id !== $product_id ) {
            $resolved = get_post_meta( $original_id, '_raffle_id', true );
            if ( $resolved ) {
                return (int) $resolved;
            }
        }
        return $raffle_id;
    }
}
