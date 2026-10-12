<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Raffle_REST {
    public static function register() {
        $public = array( 'methods' => 'GET', 'permission_callback' => '__return_true' );
        register_rest_route( 'wpraffle/v1', '/competitions', array_merge( $public, array( 'callback' => array( __CLASS__, 'listing' ) ) ) );
        register_rest_route( 'wpraffle/v1', '/competitions/(?P<id>\d+)', array_merge( $public, array( 'callback' => array( __CLASS__, 'competition' ) ) ) );
        register_rest_route( 'wpraffle/v1', '/competitions/(?P<id>\d+)/receipt', array_merge( $public, array( 'callback' => array( __CLASS__, 'receipt' ) ) ) );
        register_rest_route( 'wpraffle/v1', '/competitions/(?P<id>\d+)/commitment', array_merge( $public, array( 'callback' => static function( $request ) { $id = absint( $request['id'] ); $record = self::visible( $id ) ? Raffle_Draw_Receipts::commitment( $id ) : null; return $record ? rest_ensure_response( $record ) : new WP_Error( 'not_found', 'No commitment available.', array( 'status' => 404 ) ); } ) ) );
        register_rest_route( 'wpraffle/v1', '/me/entries', array( 'methods' => 'GET', 'permission_callback' => array( __CLASS__, 'account_permission' ), 'callback' => array( __CLASS__, 'entries' ) ) );
        register_rest_route( 'wpraffle/v1', '/templates', array( 'methods' => 'GET', 'permission_callback' => array( __CLASS__, 'admin_permission' ), 'callback' => static function() { return rest_ensure_response( Raffle_Templates::get_templates() ); } ) );
        register_rest_route( 'wpraffle/v1', '/competitions/(?P<id>\d+)/clone', array( 'methods' => 'POST', 'permission_callback' => array( __CLASS__, 'admin_permission' ), 'callback' => static function( $request ) { $id = Raffle_Templates::clone_raffle( absint( $request['id'] ) ); return is_wp_error( $id ) ? $id : new WP_REST_Response( array( 'id' => $id, 'status' => 'draft' ), 201 ); } ) );
    }
    public static function account_permission() { return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', 'Authentication required.', array( 'status' => 401 ) ); }
    public static function admin_permission() { return current_user_can( 'manage_options' ) ? true : new WP_Error( 'rest_forbidden', 'Administrator access required.', array( 'status' => 403 ) ); }
    public static function visible( $id ) {
        global $wpdb;
        $r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d AND status IN ('active','finished','failed')", $id ) );
        if ( ! $r ) { return false; }
        if ( $r->wc_product_id ) {
            $p = function_exists( 'wc_get_product' ) ? wc_get_product( $r->wc_product_id ) : false;
            if ( ! $p || 'publish' !== $p->get_status() || '' !== (string) get_post_field( 'post_password', $r->wc_product_id ) ) { return false; }
        }
        return $r;
    }
    public static function listing( $request ) {
        $args = $request->get_params();
        // Public requests never select drafts, including authenticated administrators.
        if ( isset( $args['status'] ) && 'draft' === $args['status'] ) { $args['status'] = 'upcoming'; }
        $response = new WP_REST_Response( array_map( array( 'Raffle_Discovery', 'public_data' ), Raffle_Discovery::query( $args ) ) );
        $total = Raffle_Discovery::query( $args, true );
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', (int) ceil( $total / Raffle_Discovery::normalize( $args )['per_page'] ) );
        return $response;
    }
    public static function competition( $request ) {
        $r = self::visible( absint( $request['id'] ) );
        return $r ? rest_ensure_response( Raffle_Discovery::public_data( $r ) ) : new WP_Error( 'not_found', 'Competition not found.', array( 'status' => 404 ) );
    }
    public static function receipt( $request ) {
        $id = absint( $request['id'] );
        $record = self::visible( $id ) ? Raffle_Draw_Receipts::get( $id ) : null;
        if ( ! $record ) { return new WP_Error( 'not_found', 'No verification record available.', array( 'status' => 404 ) ); }
        $response = new WP_REST_Response( $record );
        $response->header( 'Content-Disposition', 'attachment; filename="wpraffle-draw-' . $id . '.json"' );
        $response->header( 'Cache-Control', 'no-store' );
        return $response;
    }
    public static function entries( $request ) {
        global $wpdb;
        $page = max( 1, min( 10000, (int) $request->get_param( 'page' ) ) );
        $email = wp_get_current_user()->user_email;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.id, p.raffle_id, p.quantity, p.payment_status, p.purchase_date, r.title, r.draw_date, r.status FROM {$wpdb->prefix}raffle_purchases p JOIN {$wpdb->prefix}raffles r ON r.id = p.raffle_id WHERE p.buyer_email = %s ORDER BY p.id DESC LIMIT 25 OFFSET %d", $email, ( $page - 1 ) * 25 ) );
        $response = rest_ensure_response( $rows ?: array() ); $response->header( 'Cache-Control', 'private, no-store' ); return $response;
    }
}
