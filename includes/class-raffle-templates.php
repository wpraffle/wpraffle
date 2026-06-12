<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Templates {

    public function __construct() {
        add_action( 'wp_ajax_raffle_save_template', array( $this, 'ajax_save_template' ) );
        add_action( 'wp_ajax_raffle_get_templates', array( $this, 'ajax_get_templates' ) );
        add_action( 'wp_ajax_raffle_delete_template', array( $this, 'ajax_delete_template' ) );
        add_action( 'wp_ajax_raffle_apply_template', array( $this, 'ajax_apply_template' ) );
        add_action( 'wp_ajax_raffle_clone_raffle', array( $this, 'ajax_clone_raffle' ) );
    }

    /**
     * Save a raffle configuration as a template.
     */
    public static function save_template( $name, $config ) {
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'raffle_templates',
            array(
                'name'       => sanitize_text_field( $name ),
                'config'     => wp_json_encode( $config ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s' )
        );
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get all templates.
     */
    public static function get_templates() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}raffle_templates ORDER BY created_at DESC"
        );
    }

    /**
     * Get a single template.
     */
    public static function get_template( $template_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_templates WHERE id = %d",
            absint( $template_id )
        ) );
    }

    /**
     * Clone a raffle from an existing one.
     */
    public static function clone_raffle( $raffle_id ) {
        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            return new WP_Error( 'not_found', 'Raffle not found.' );
        }

        // Clone the raffle data
        $data = (array) $raffle;
        unset( $data['id'], $data['winner_ticket_id'], $data['sold_tickets'], $data['reminder_sent'], $data['wc_product_id'] );
        $data['wc_product_id'] = 0;
        $data['title']          = $raffle->title . ' (Copy)';
        $data['status']         = 'draft';
        $data['sold_tickets']   = 0;
        $data['created_at']     = current_time( 'mysql' );

        $wpdb->insert( $wpdb->prefix . 'raffles', $data );
        $new_id = $wpdb->insert_id;

        // Always create a WooCommerce product for the cloned raffle
        if ( class_exists( 'WooCommerce' ) ) {
            $wc_product_id = wp_insert_post( array(
                'post_title'   => $data['title'],
                'post_content' => $raffle->description,
                'post_status'  => 'draft',
                'post_type'    => 'product',
                'post_author'  => 1,
            ) );

            if ( ! is_wp_error( $wc_product_id ) && $wc_product_id ) {
                wp_set_object_terms( $wc_product_id, 'simple', 'product_type' );
                update_post_meta( $wc_product_id, '_visibility', 'visible' );
                update_post_meta( $wc_product_id, '_stock_status', 'instock' );
                update_post_meta( $wc_product_id, '_regular_price', $raffle->ticket_price );
                update_post_meta( $wc_product_id, '_price', $raffle->ticket_price );
                update_post_meta( $wc_product_id, '_virtual', 'yes' );
                update_post_meta( $wc_product_id, '_sold_individually', 'no' );
                update_post_meta( $wc_product_id, '_raffle_id', $new_id );
                update_post_meta( $wc_product_id, '_raffle_start_date', $data['start_date'] );
                update_post_meta( $wc_product_id, '_raffle_draw_date', $data['draw_date'] );
                update_post_meta( $wc_product_id, '_raffle_status', 'draft' );

                // Copy featured image from original product
                $original_thumb_id = get_post_thumbnail_id( $raffle->wc_product_id );
                if ( $original_thumb_id ) {
                    set_post_thumbnail( $wc_product_id, $original_thumb_id );
                }

                // Update the cloned raffle with the new WC product ID
                $wpdb->update(
                    $wpdb->prefix . 'raffles',
                    array( 'wc_product_id' => $wc_product_id ),
                    array( 'id' => $new_id ),
                    array( '%d' ),
                    array( '%d' )
                );
            }
        }

        Raffle_Audit::log( $new_id, 'raffle_cloned', array( 'source_id' => $raffle_id ), 'admin' );

        return $new_id;
    }

    /**
     * AJAX: Save template.
     */
    public function ajax_save_template() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_template_nonce' ) ) {
            wp_send_json_error( 'Security error' );
        }

        $name   = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $config = sanitize_text_field( wp_unslash( $_POST['config'] ?? '{}' ) );
        $config = json_decode( $config, true );

        if ( ! $name || ! $config ) {
            wp_send_json_error( 'Invalid data' );
        }

        $id = self::save_template( $name, $config );
        wp_send_json_success( array( 'id' => $id, 'message' => 'Template saved' ) );
    }

    /**
     * AJAX: Get all templates.
     */
    public function ajax_get_templates() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'raffle_template_nonce' ) ) {
            wp_send_json_error( 'Security error' );
        }
        wp_send_json_success( self::get_templates() );
    }

    /**
     * AJAX: Delete template.
     */
    public function ajax_delete_template() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_template_nonce' ) ) {
            wp_send_json_error( 'Security error' );
        }
        $id = absint( $_POST['template_id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( 'Invalid template' );
        }
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'raffle_templates', array( 'id' => $id ), array( '%d' ) );
        wp_send_json_success( array( 'message' => 'Template deleted' ) );
    }

    /**
     * AJAX: Apply template to a new raffle form (returns config).
     */
    public function ajax_apply_template() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'raffle_template_nonce' ) ) {
            wp_send_json_error( 'Security error' );
        }
        $id = absint( $_GET['template_id'] ?? 0 );
        $template = self::get_template( $id );
        if ( ! $template ) {
            wp_send_json_error( 'Template not found' );
        }
        wp_send_json_success( array(
            'config' => json_decode( $template->config, true ),
            'name'   => $template->name,
        ) );
    }

    /**
     * AJAX: Clone raffle.
     */
    public function ajax_clone_raffle() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_clone_nonce' ) ) {
            wp_send_json_error( 'Security error' );
        }
        $raffle_id = absint( $_POST['raffle_id'] ?? 0 );
        $new_id = self::clone_raffle( $raffle_id );
        if ( is_wp_error( $new_id ) ) {
            wp_send_json_error( $new_id->get_error_message() );
        }
        wp_send_json_success( array(
            'new_id'  => $new_id,
            'message' => 'Raffle cloned',
            'edit_url' => admin_url( 'admin.php?page=raffle-new&action=edit&id=' . $new_id ),
        ) );
    }
}