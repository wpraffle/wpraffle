<?php
/**
 * SEO plugin compatibility (Yoast + Rank Math) — fixes canonical and Open
 * Graph URLs for raffle archive / entry pages so indexing reflects the
 * pretty raffle URL rather than a raw query-string variant.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Compat_Seo extends Raffle_Compatibility {

    public static function name() {
        return __( 'Yoast SEO / Rank Math', 'wpraffle' );
    }

    public static function is_available() {
        return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' );
    }

    public function register_hooks() {
        if ( defined( 'WPSEO_VERSION' ) ) {
            add_filter( 'wpseo_canonical', array( $this, 'canonical' ) );
            add_filter( 'wpseo_opengraph_url', array( $this, 'canonical' ) );
        }
        if ( defined( 'RANK_MATH_VERSION' ) ) {
            add_filter( 'rank_math/frontend/canonical', array( $this, 'canonical' ) );
            add_filter( 'rank_math/opengraph/facebook/url', array( $this, 'canonical' ) );
        }
    }

    /**
     * On a raffle product page, force the canonical to the product permalink
     * (avoids query-string variants from the number-grid / viewers-now AJAX).
     */
    public function canonical( $url ) {
        if ( ! is_singular( 'product' ) ) {
            return $url;
        }
        global $post;
        if ( ! $post || get_post_meta( $post->ID, '_raffle_id', true ) === '' ) {
            return $url;
        }
        $permalink = get_permalink( $post->ID );
        return $permalink ? $permalink : $url;
    }
}
