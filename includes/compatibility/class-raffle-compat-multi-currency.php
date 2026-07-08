<?php
/**
 * CURCY (WooCommerce Multi-Currency) compatibility — converts raffle bundle /
 * package prices back to the store default currency so the per-raffle price
 * set by the operator is interpreted correctly regardless of the visitor's
 * selected currency.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Compat_Multi_Currency extends Raffle_Compatibility {

    public static function name() {
        return __( 'WooCommerce Multi-Currency (CURCY)', 'wpraffle' );
    }

    public static function is_available() {
        return class_exists( 'WOOMULTI_CURRENCY_F_Data' ) || defined( 'WOOMULTI_CURRENCY_F_VERSION' ) || defined( 'WOOMULTI_CURRENCY_VERSION' );
    }

    public function register_hooks() {
        // Normalise the bundle/package price to the default currency before the
        // WC cart price recalculation runs.
        add_filter( 'wpraffle_normalise_packages_price', array( $this, 'convert_to_default' ), 10, 2 );
    }

    /**
     * Convert a CURCY-multiplied price back to the default currency.
     *
     * @param float $price        Price in the visitor's currency.
     * @param int   $raffle_id    Raffle context.
     * @return float Price in the default currency.
     */
    public function convert_to_default( $price, $raffle_id ) {
        if ( ! function_exists( 'wmc_get_price' ) ) {
            return $price;
        }
        // CURCY exposes the reverse-rate via wmc_revert_price; fall back to the
        // raw price if neither helper is present (version differences).
        if ( function_exists( 'wmc_revert_price' ) ) {
            return (float) wmc_revert_price( $price );
        }
        return (float) $price;
    }
}
