<?php
/**
 * WPRaffle — Featured Winners
 *
 * Stores and retrieves featured-winner data (a flag + winner photo + optional
 * testimonial) for finished raffles. One row per raffle. The featured flag
 * and photo are set from the Raffle Details admin page; the get_featured()
 * method is the query entrypoint for a future "Featured Winners" carousel.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Featured_Winners {

    public function __construct() {
        add_action( 'wp_ajax_raffle_save_featured_winner', array( $this, 'ajax_save' ) );
    }

    /**
     * Get the featured-winner row for a single raffle (or null if none).
     *
     * @param int $raffle_id
     * @return object|null
     */
    public static function get( $raffle_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_featured_winners WHERE raffle_id = %d",
            absint( $raffle_id )
        ) );
    }

    /**
     * Get all featured winners for a carousel (only is_featured = 1 rows),
     * joined to the raffle for title/prize context. Ordered by most recent.
     *
     * @param int $limit
     * @return array
     */
    public static function get_featured( $limit = 20 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT fw.*, r.title AS raffle_title, r.prize_image, r.wc_product_id
             FROM {$wpdb->prefix}raffle_featured_winners fw
             JOIN {$wpdb->prefix}raffles r ON r.id = fw.raffle_id
             WHERE fw.is_featured = 1
             ORDER BY fw.updated_at DESC
             LIMIT %d",
            absint( $limit )
        ) );
    }

    /**
     * Upsert the featured-winner row for a raffle.
     *
     * @param int    $raffle_id
     * @param array  $data {winner_name, winner_email, winner_photo_id, is_featured, testimonial}
     * @return int The row id.
     */
    public static function save( $raffle_id, $data ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'raffle_featured_winners';
        $raffle_id = absint( $raffle_id );

        $row = array(
            'raffle_id'      => $raffle_id,
            'winner_name'    => isset( $data['winner_name'] ) ? sanitize_text_field( $data['winner_name'] ) : '',
            'winner_email'   => isset( $data['winner_email'] ) ? sanitize_email( $data['winner_email'] ) : '',
            'winner_photo_id'=> isset( $data['winner_photo_id'] ) ? absint( $data['winner_photo_id'] ) : null,
            'is_featured'    => ! empty( $data['is_featured'] ) ? 1 : 0,
            'testimonial'    => isset( $data['testimonial'] ) ? sanitize_textarea_field( $data['testimonial'] ) : '',
        );
        $fmt = array( '%d', '%s', '%s', '%d', '%d', '%s' );

        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE raffle_id = %d", $raffle_id ) );
        if ( $existing ) {
            $wpdb->update( $table, $row, array( 'id' => (int) $existing ), $fmt, array( '%d' ) );
            return (int) $existing;
        }
        $wpdb->insert( $table, $row, $fmt );
        return (int) $wpdb->insert_id;
    }

    /**
     * AJAX: save the featured-winner data from the Raffle Details page.
     */
    public function ajax_save() {
        check_ajax_referer( 'raffle_featured_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        if ( ! $raffle_id ) {
            wp_send_json_error( array( 'message' => 'Invalid raffle.' ) );
        }

        $data = array(
            'winner_name'     => isset( $_POST['winner_name'] ) ? sanitize_text_field( wp_unslash( $_POST['winner_name'] ) ) : '',
            'winner_email'    => isset( $_POST['winner_email'] ) ? sanitize_email( wp_unslash( $_POST['winner_email'] ) ) : '',
            'winner_photo_id' => isset( $_POST['winner_photo_id'] ) ? absint( $_POST['winner_photo_id'] ) : 0,
            'is_featured'     => isset( $_POST['is_featured'] ) ? 1 : 0,
            'testimonial'     => isset( $_POST['testimonial'] ) ? sanitize_textarea_field( wp_unslash( $_POST['testimonial'] ) ) : '',
        );

        $row_id = self::save( $raffle_id, $data );

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( $raffle_id, 'featured_winner_saved', array(
                'row_id'      => $row_id,
                'is_featured' => $data['is_featured'],
                'has_photo'   => ! empty( $data['winner_photo_id'] ),
            ), 'admin' );
        }

        wp_send_json_success( array( 'message' => 'Featured winner saved.', 'row_id' => $row_id ) );
    }

    /**
     * Helper: get the photo URL for a featured-winner row (or null).
     *
     * @param object $fw Featured-winner row.
     * @param string $size WP image size.
     * @return string|null
     */
    public static function get_photo_url( $fw, $size = 'large' ) {
        if ( empty( $fw->winner_photo_id ) ) {
            return null;
        }
        $url = wp_get_attachment_image_url( (int) $fw->winner_photo_id, $size );
        return $url ?: null;
    }
}
