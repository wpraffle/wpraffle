<?php
/**
 * Dokan (and WC Vendors) compatibility — resolves a [vendor] token in
 * raffle email recipient lists to the product author's email, so multi-vendor
 * stores can route raffle notifications to the vendor who owns the raffle.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Compat_Dokan extends Raffle_Compatibility {

    public static function name() {
        return __( 'Dokan / WC Vendors', 'wpraffle' );
    }

    public static function is_available() {
        return class_exists( 'WeDevs_Dokan' ) || class_exists( 'WCV_Vendors' ) || defined( 'DOKAN_PLUGIN_VERSION' );
    }

    public function register_hooks() {
        add_filter( 'wpraffle_email_recipients', array( $this, 'resolve_vendor_recipient' ), 10, 2 );
    }

    /**
     * Replace a [vendor] entry in the recipient list with the post author's
     * email (the vendor who owns the raffle's WC product).
     *
     * @param array  $recipients
     * @param object $raffle
     * @return array
     */
    public function resolve_vendor_recipient( $recipients, $raffle ) {
        if ( ! is_array( $recipients ) ) {
            return $recipients;
        }
        if ( ! isset( $recipients['vendor'] ) && ! in_array( 'vendor', $recipients, true ) ) {
            return $recipients;
        }
        if ( empty( $raffle->wc_product_id ) ) {
            return $recipients;
        }
        $post = get_post( $raffle->wc_product_id );
        if ( ! $post ) {
            return $recipients;
        }
        $author = get_userdata( $post->post_author );
        if ( $author && $author->user_email ) {
            // Replace any 'vendor' keyed/string entry with the resolved email.
            foreach ( $recipients as $k => $v ) {
                if ( $v === 'vendor' || $k === 'vendor' ) {
                    $recipients[ $k ] = $author->user_email;
                }
            }
        }
        return $recipients;
    }
}
