<?php
/**
 * WPML / WCML compatibility — keeps raffle configuration in sync across
 * translations. Raffles are custom-table entities with a linked WC product,
 * so when an operator edits a raffle on the original-language product this
 * adapter propagates the raffle linkage to the translated products and maps
 * child→parent raffle ids in queries.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Compat_Wpml extends Raffle_Compatibility {

    public static function name() {
        return __( 'WPML / WCML', 'wpraffle' );
    }

    public static function is_available() {
        return defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress' );
    }

    public function register_hooks() {
        // When a WC product is translated, copy the _raffle_id link from the
        // original product so the translated product points at the same raffle.
        add_action( 'wcml_after_duplicate_product', array( $this, 'sync_raffle_link' ), 10, 2 );
        add_action( 'wpml_after_duplicate_post', array( $this, 'sync_raffle_link' ), 10, 2 );

        // Map a translated product id back to the original raffle id on read.
        add_filter( 'wpraffle_resolve_product_raffle_id', array( $this, 'resolve_original_raffle' ), 10, 2 );
    }

    /**
     * Copy the _raffle_id meta from the original product to its translation.
     *
     * @param int $original_id Original product id.
     * @param int $translated_id Translated product id.
     */
    public function sync_raffle_link( $original_id, $translated_id ) {
        $original_id  = absint( $original_id );
        $translated_id = absint( $translated_id );
        if ( ! $original_id || ! $translated_id || $original_id === $translated_id ) {
            return;
        }
        $raffle_id = get_post_meta( $original_id, '_raffle_id', true );
        if ( $raffle_id ) {
            update_post_meta( $translated_id, '_raffle_id', $raffle_id );
            update_post_meta( $translated_id, '_is_raffle_order', 'no' );
        }
    }

    /**
     * Given a translated product id, resolve its raffle id via the original
     * product so language variants share one raffle.
     *
     * @param int|false $raffle_id Current resolution (may be false).
     * @param int       $product_id Product id being resolved.
     * @return int|false
     */
    public function resolve_original_raffle( $raffle_id, $product_id ) {
        if ( $raffle_id ) {
            return $raffle_id;
        }
        if ( ! function_exists( 'wpml_object_id_filter' ) && ! has_filter( 'wpml_object_id' ) ) {
            return $raffle_id;
        }
        $original_id = apply_filters( 'wpml_object_id', $product_id, 'product', true, apply_filters( 'wpml_default_language', null ) );
        if ( $original_id && $original_id !== $product_id ) {
            $resolved = get_post_meta( $original_id, '_raffle_id', true );
            if ( $resolved ) {
                return (int) $resolved;
            }
        }
        return $raffle_id;
    }
}
