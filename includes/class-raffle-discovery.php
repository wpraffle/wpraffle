<?php
/** Bounded competition discovery shared by shortcodes and REST. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Raffle_Discovery {
    public static function normalize( $args ) {
        $args = is_array( $args ) ? $args : array();
        $clean = array();
        foreach ( array( 'category', 'status', 'sort', 'availability', 'closing' ) as $key ) {
            $clean[ $key ] = isset( $args[ $key ] ) && is_scalar( $args[ $key ] ) ? sanitize_key( (string) $args[ $key ] ) : '';
        }
        $clean['status'] = in_array( $clean['status'], array( 'active', 'finished', 'ended', 'upcoming', 'draft', 'all' ), true ) ? $clean['status'] : 'active';
        $clean['sort'] = in_array( $clean['sort'], array( 'newest', 'closing', 'price_asc', 'price_desc', 'remaining' ), true ) ? $clean['sort'] : 'newest';
        foreach ( array( 'min_price', 'max_price' ) as $key ) {
            $clean[ $key ] = isset( $args[ $key ] ) && is_scalar( $args[ $key ] ) && is_numeric( $args[ $key ] ) ? max( 0, min( 99999999, (float) $args[ $key ] ) ) : null;
        }
        $clean['per_page'] = max( 1, min( 48, isset( $args['per_page'] ) && is_scalar( $args['per_page'] ) ? (int) $args['per_page'] : 12 ) );
        $clean['page'] = max( 1, min( 10000, isset( $args['page'] ) && is_scalar( $args['page'] ) ? (int) $args['page'] : 1 ) );
        $clean['featured'] = isset( $args['featured'] ) && is_scalar( $args['featured'] ) && '1' === (string) $args['featured'];
        return $clean;
    }

    public static function query( $args = array(), $count = false ) {
        global $wpdb;
        $a = self::normalize( $args );
        $now = current_time( 'mysql' );
        $where = array(); $params = array();
        if ( 'active' === $a['status'] ) {
            $where[] = "r.status = 'active' AND (r.start_date IS NULL OR r.start_date <= %s) AND (r.draw_date IS NULL OR r.draw_date > %s) AND r.sold_tickets < r.total_tickets";
            $params = array( $now, $now );
        } elseif ( in_array( $a['status'], array( 'finished', 'ended' ), true ) ) {
            $where[] = "(r.status IN ('finished','failed') OR (r.status = 'active' AND (r.draw_date <= %s OR r.sold_tickets >= r.total_tickets)))";
            $params[] = $now;
        } elseif ( 'draft' === $a['status'] && current_user_can( 'manage_options' ) ) {
            $where[] = "r.status = 'draft'";
        } elseif ( in_array( $a['status'], array( 'draft', 'upcoming' ), true ) ) {
            $where[] = "r.status = 'active' AND r.start_date > %s"; $params[] = $now;
        } else {
            $where[] = "r.status IN ('active','finished','failed')";
        }
        // A product-linked competition must have a published, unprotected product.
        if ( 'draft' !== $a['status'] || ! current_user_can( 'manage_options' ) ) {
            $where[] = "(COALESCE(r.wc_product_id,0) = 0 OR EXISTS (SELECT 1 FROM {$wpdb->posts} p WHERE p.ID = r.wc_product_id AND p.post_type = 'product' AND p.post_status = 'publish' AND p.post_password = ''))";
        }
        if ( $a['featured'] ) { $where[] = 'r.is_featured = 1'; }
        foreach ( array( 'min_price' => '>=', 'max_price' => '<=' ) as $key => $operator ) {
            if ( null !== $a[ $key ] ) { $where[] = "r.ticket_price {$operator} %f"; $params[] = $a[ $key ]; }
        }
        if ( 'available' === $a['availability'] ) { $where[] = 'r.sold_tickets < r.total_tickets'; }
        if ( 'sold_out' === $a['availability'] ) { $where[] = 'r.sold_tickets >= r.total_tickets'; }
        if ( in_array( $a['closing'], array( '24h', '7d' ), true ) ) {
            $where[] = 'r.draw_date > %s AND r.draw_date <= %s';
            $params[] = $now;
            $params[] = wp_date( 'Y-m-d H:i:s', time() + ( '24h' === $a['closing'] ? DAY_IN_SECONDS : 7 * DAY_IN_SECONDS ), wp_timezone() );
        }
        if ( $a['category'] ) {
            $term = get_term_by( 'slug', $a['category'], 'raffle_category' );
            if ( ! $term ) { return $count ? 0 : array(); }
            $where[] = "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id = r.wc_product_id AND tt.taxonomy = 'raffle_category' AND tt.term_id = %d)";
            $params[] = (int) $term->term_id;
        }
        $select = $count ? 'COUNT(*)' : 'r.*';
        $sql = "SELECT {$select} FROM {$wpdb->prefix}raffles r WHERE " . implode( ' AND ', $where );
        if ( ! $count ) {
            $sort = array( 'newest' => 'r.is_featured DESC, r.created_at DESC, r.id DESC', 'closing' => 'r.draw_date IS NULL, r.draw_date ASC, r.id DESC', 'price_asc' => 'r.ticket_price ASC, r.id DESC', 'price_desc' => 'r.ticket_price DESC, r.id DESC', 'remaining' => '(r.total_tickets-r.sold_tickets) ASC, r.id DESC' );
            $sql .= ' ORDER BY ' . $sort[ $a['sort'] ] . ' LIMIT %d OFFSET %d';
            $params[] = $a['per_page']; $params[] = ( $a['page'] - 1 ) * $a['per_page'];
        }
        $sql = $params ? $wpdb->prepare( $sql, $params ) : $sql;
        return $count ? (int) $wpdb->get_var( $sql ) : ( $wpdb->get_results( $sql ) ?: array() );
    }

    public static function public_data( $r ) {
        return array( 'id' => (int) $r->id, 'title' => $r->title, 'ticket_price' => (string) $r->ticket_price, 'total_tickets' => (int) $r->total_tickets, 'sold_tickets' => (int) $r->sold_tickets, 'remaining' => max( 0, (int) $r->total_tickets - (int) $r->sold_tickets ), 'status' => $r->status, 'start_date' => $r->start_date, 'draw_date' => $r->draw_date, 'url' => $r->wc_product_id ? get_permalink( $r->wc_product_id ) : '', 'receipt_url' => rest_url( 'wpraffle/v1/competitions/' . (int) $r->id . '/receipt' ) );
    }
}
