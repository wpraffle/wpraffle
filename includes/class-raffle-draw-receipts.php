<?php
/** Public, privacy-preserving draw result receipts. Does not certify RNG fairness. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Raffle_Draw_Receipts {
    public static function init() {
        add_action( 'admin_post_wpraffle_commit_draw', array( __CLASS__, 'commit_draw' ) );
    }
    public static function settings() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        require RAFFLE_SYSTEM_PATH . 'admin/views/draw-verification.php';
    }
    public static function commit_draw() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'wpraffle_commit_draw' );
        $id = isset( $_POST['raffle_id'] ) && is_scalar( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $result = self::commit( $id );
        if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
        wp_safe_redirect( admin_url( 'admin.php?page=wpraffle-verification&saved=1' ) ); exit;
    }
    public static function commit( $id ) {
        global $wpdb;
        $result = Raffle_Transactions::run( array( 'wpraffle_seed_' . absint( $id ) ), static function() use ( $wpdb, $id ) {
            $r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d FOR UPDATE", $id ) );
            if ( ! $r || $r->sold_tickets || $r->winner_ticket_id || ! in_array( $r->status, array( 'draft','active' ), true ) ) { return new WP_Error( 'commit_not_available', 'Commitment requires a new competition with no entries or winner.' ); }
            if ( self::commitment( $id ) ) { return new WP_Error( 'already_committed', 'A commitment already exists. It cannot be replaced.' ); }
            $seed = bin2hex( random_bytes( 32 ) );
            $record = array( 'algorithm' => 'sha256-rejection-v1', 'raffle_id' => (int) $id, 'seed_commitment' => hash( 'sha256', $seed ), 'published_at' => gmdate( 'Y-m-d\TH:i:s\Z' ) );
            if ( false === $wpdb->insert( $wpdb->prefix . 'raffle_audit_log', array( 'raffle_id' => $id, 'action_type' => 'draw_commitment', 'user_id' => get_current_user_id(), 'details' => wp_json_encode( $record ), 'fairness_proof' => $record['seed_commitment'], 'created_at' => current_time( 'mysql' ) ) ) ) { return new WP_Error( 'commit_write_failed', 'Could not publish the commitment.' ); }
            if ( ! add_option( 'wpraffle_draw_seed_' . $id, $seed, '', false ) ) { return new WP_Error( 'seed_write_failed', 'Could not store the private seed.' ); }
            return $record;
        } );
        if ( is_wp_error( $result ) ) { wp_cache_delete( 'wpraffle_draw_seed_' . $id, 'options' ); wp_cache_delete( 'notoptions', 'options' ); }
        return $result;
    }
    public static function commitment( $id ) {
        global $wpdb;
        $raw = $wpdb->get_var( $wpdb->prepare( "SELECT details FROM {$wpdb->prefix}raffle_audit_log WHERE raffle_id = %d AND action_type = 'draw_commitment' ORDER BY id DESC LIMIT 1", $id ) );
        $record = json_decode( (string) $raw, true ); return is_array( $record ) ? $record : null;
    }
    /** Platform-independent 32-bit rejection sampling; selection without replacement. */
    public static function reproduce( $seed, $pool, $count ) {
        if ( ! is_string( $seed ) || ! preg_match( '/^[0-9a-f]{64}$/D', $seed ) || ! is_array( $pool ) || $count < 1 || $count > count( $pool ) ) { return array(); }
        $remaining = array_values( array_map( 'intval', $pool ) ); sort( $remaining, SORT_NUMERIC );
        if ( count( $remaining ) !== count( array_unique( $remaining ) ) ) { return array(); }
        $pool_hash = hash( 'sha256', implode( ',', $remaining ) ); $counter = 0; $selected = array();
        while ( count( $selected ) < $count ) {
            $n = count( $remaining ); $limit = 4294967296 - ( 4294967296 % $n );
            do { $value = hexdec( substr( hash( 'sha256', $seed . '|' . $pool_hash . '|' . $counter++ ), 0, 8 ) ); } while ( $value >= $limit );
            $index = (int) ( $value % $n ); $selected[] = $remaining[ $index ]; array_splice( $remaining, $index, 1 );
        }
        return $selected;
    }
    public static function select( $id, $tickets, $count ) {
        $commitment = self::commitment( $id );
        if ( ! $commitment ) { return null; } // Preserve legacy random_int draws.
        $seed = get_option( 'wpraffle_draw_seed_' . $id );
        if ( ! is_string( $seed ) || ! hash_equals( $commitment['seed_commitment'], hash( 'sha256', $seed ) ) ) { return new WP_Error( 'seed_missing', 'The private seed is missing or does not match the published commitment.' ); }
        $pool = array_map( static function( $t ) { return (int) $t->ticket_number; }, $tickets );
        $numbers = self::reproduce( $seed, $pool, $count );
        if ( count( $numbers ) !== $count ) { return new WP_Error( 'invalid_pool', 'The committed draw pool is invalid.' ); }
        $lookup = array(); foreach ( $tickets as $t ) { $lookup[ (int) $t->ticket_number ] = $t; }
        return array( 'algorithm' => 'sha256-rejection-v1', 'seed' => $seed, 'winners' => array_map( static function( $number ) use ( $lookup ) { return $lookup[ $number ]; }, $numbers ) );
    }

    public static function build( $raffle_id, $tickets, $winners, $selection = array() ) {
        $pool = array_map( static function( $t ) { return (int) $t->ticket_number; }, $tickets );
        sort( $pool, SORT_NUMERIC );
        $numbers = array_map( static function( $w ) { return (int) ( is_array( $w ) ? $w['ticket_number'] : $w->ticket_number ); }, $winners );
        $record = array( 'schema' => 'wpraffle-draw-receipt-v1', 'draw_id' => wp_generate_uuid4(), 'raffle_id' => (int) $raffle_id, 'drawn_at' => gmdate( 'Y-m-d\TH:i:s\Z' ), 'selection' => isset( $selection['algorithm'] ) ? $selection['algorithm'] : 'php-random-int', 'eligible_ticket_numbers' => $pool, 'winning_ticket_numbers' => $numbers );
        if ( ! empty( $selection['seed'] ) ) {
            $record['seed_reveal'] = $selection['seed'];
            $record['seed_commitment'] = hash( 'sha256', $selection['seed'] );
            $record['pool_sha256'] = hash( 'sha256', implode( ',', $pool ) );
        }
        return array( 'record' => $record, 'sha256' => hash( 'sha256', wp_json_encode( $record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ), 'scope' => 'Result and eligible-pool integrity only. Legacy draws verify recorded result integrity. Committed draws also allow seed verification and result reproduction. Neither proves pool completeness or operator independence.' );
    }
    public static function valid( $receipt ) {
        if ( ! is_array( $receipt ) || ! isset( $receipt['record'], $receipt['sha256'] ) || ! is_array( $receipt['record'] ) || ! is_string( $receipt['sha256'] ) || ! hash_equals( hash( 'sha256', wp_json_encode( $receipt['record'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ), $receipt['sha256'] ) ) { return false; }
        $r = $receipt['record'];
        if ( isset( $r['selection'] ) && 'sha256-rejection-v1' === $r['selection'] ) {
            if ( ! isset( $r['seed_reveal'], $r['seed_commitment'], $r['eligible_ticket_numbers'], $r['winning_ticket_numbers'] ) || ! is_string( $r['seed_reveal'] ) || ! is_string( $r['seed_commitment'] ) || ! is_array( $r['eligible_ticket_numbers'] ) || ! is_array( $r['winning_ticket_numbers'] ) ) { return false; }
            return isset( $r['pool_sha256'] ) && is_string( $r['pool_sha256'] ) && hash_equals( $r['pool_sha256'], hash( 'sha256', implode( ',', $r['eligible_ticket_numbers'] ) ) ) && hash_equals( $r['seed_commitment'], hash( 'sha256', $r['seed_reveal'] ) ) && self::reproduce( $r['seed_reveal'], $r['eligible_ticket_numbers'], count( $r['winning_ticket_numbers'] ) ) === $r['winning_ticket_numbers'];
        }
        return true;
    }
    public static function persist( $raffle_id, $tickets, $winners, $selection = array() ) {
        global $wpdb;
        $receipt = self::build( $raffle_id, $tickets, $winners, $selection );
        return false !== $wpdb->insert( $wpdb->prefix . 'raffle_audit_log', array( 'raffle_id' => (int) $raffle_id, 'action_type' => 'draw_receipt', 'user_id' => get_current_user_id() ?: null, 'details' => wp_json_encode( $receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 'fairness_proof' => $receipt['sha256'], 'created_at' => current_time( 'mysql' ) ), array( '%d', '%s', '%d', '%s', '%s', '%s' ) );
    }
    public static function get( $raffle_id ) {
        global $wpdb;
        $json = $wpdb->get_var( $wpdb->prepare( "SELECT details FROM {$wpdb->prefix}raffle_audit_log WHERE raffle_id = %d AND action_type = 'draw_receipt' ORDER BY id DESC LIMIT 1", $raffle_id ) );
        $receipt = json_decode( (string) $json, true );
        return self::valid( $receipt ) ? $receipt : null;
    }
    public static function render( $atts ) {
        $atts = shortcode_atts( array( 'raffle_id' => 0 ), $atts, 'raffle_draw_receipt' );
        $id = absint( $atts['raffle_id'] );
        if ( ! Raffle_REST::visible( $id ) ) { return ''; }
        if ( ! self::get( $id ) ) {
            $commitment = self::commitment( $id );
            return $commitment ? '<section class="wpraffle-draw-receipt"><h3>' . esc_html__( 'Published draw commitment', 'wpraffle' ) . '</h3><code>' . esc_html( $commitment['seed_commitment'] ) . '</code><p>' . esc_html( $commitment['published_at'] ) . '</p><a href="' . esc_url( rest_url( 'wpraffle/v1/competitions/' . $id . '/commitment' ) ) . '">' . esc_html__( 'Download commitment (JSON)', 'wpraffle' ) . '</a></section>' : '';
        }
        $receipt = self::get( $id );
        return '<section class="wpraffle-draw-receipt"><h3>' . esc_html__( 'Draw record', 'wpraffle' ) . '</h3><p>' . esc_html__( 'Winning tickets:', 'wpraffle' ) . ' ' . esc_html( implode( ', ', $receipt['record']['winning_ticket_numbers'] ) ) . '</p><p>' . esc_html( $receipt['record']['drawn_at'] ) . '</p><p><code>' . esc_html( $receipt['sha256'] ) . '</code></p><a href="' . esc_url( rest_url( 'wpraffle/v1/competitions/' . $id . '/receipt' ) ) . '">' . esc_html__( 'Download verification record (JSON)', 'wpraffle' ) . '</a><p>' . esc_html__( 'Verifies the recorded result and ticket pool; does not prove draw fairness.', 'wpraffle' ) . '</p></section>';
    }
}
