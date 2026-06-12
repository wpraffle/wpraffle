<?php
/**
 * WPRaffle — GDPR Privacy & Data Management
 *
 * Integrates with WordPress core privacy tools and WooCommerce privacy API
 * to handle data export (Article 15) and erasure (Article 17) requests.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Privacy {

    /**
     * Database tables that contain personal data.
     */
    private static function get_data_tables() {
        global $wpdb;
        return array(
            'purchases'    => $wpdb->prefix . 'raffle_purchases',
            'tickets'      => $wpdb->prefix . 'raffle_tickets',
            'free_entries' => $wpdb->prefix . 'raffle_free_entries',
            'referrals'    => $wpdb->prefix . 'raffle_referrals',
            'instant_wins' => $wpdb->prefix . 'raffle_instant_wins',
        );
    }

    /* ===================================================================
       WordPress Privacy Hooks
       =================================================================== */

    public static function init() {
        // Register with WP privacy export/erasure tools
        add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ), 10 );
        add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ), 10 );

        // AJAX endpoints for My Raffles page
        add_action( 'wp_ajax_raffle_export_my_data', array( __CLASS__, 'ajax_export_my_data' ) );
        add_action( 'wp_ajax_raffle_request_deletion', array( __CLASS__, 'ajax_request_deletion' ) );
    }

    /**
     * Register the raffle data exporter with WP privacy tools.
     */
    public static function register_exporter( $exporters ) {
        $exporters['wpraffle-data'] = array(
            'exporter_friendly_name' => 'Raffle System Data',
            'callback'               => array( __CLASS__, 'export_data' ),
        );
        return $exporters;
    }

    /**
     * Register the raffle data eraser with WP privacy tools.
     */
    public static function register_eraser( $erasers ) {
        $erasers['wpraffle-data'] = array(
            'eraser_friendly_name' => 'Raffle System Data',
            'callback'             => array( __CLASS__, 'erase_data' ),
        );
        return $erasers;
    }

    /* ===================================================================
       Data Export
       =================================================================== */

    /**
     * Export all raffle data for a given email address.
     * Used by WordPress privacy export tool (Tools → Export Personal Data).
     *
     * @param string $email Email address to export data for.
     * @param int    $page  Page number for batched export.
     * @return array Export data in WP privacy format.
     */
    public static function export_data( $email, $page = 1 ) {
        $export_items = array();
        global $wpdb;
        $tables = self::get_data_tables();

        // ── Purchases ──
        $purchases = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, r.title as raffle_title
             FROM {$tables['purchases']} p
             LEFT JOIN {$wpdb->prefix}raffles r ON r.id = p.raffle_id
             WHERE p.buyer_email = %s
             ORDER BY p.purchase_date DESC
             LIMIT 500 OFFSET %d",
            $email,
            ( $page - 1 ) * 500
        ) );

        foreach ( $purchases as $p ) {
            $data = array(
                array( 'name' => 'Raffle', 'value' => $p->raffle_title ?? 'Unknown' ),
                array( 'name' => 'Quantity', 'value' => $p->quantity ),
                array( 'name' => 'Total Amount', 'value' => $p->total_amount ),
                array( 'name' => 'Payment Status', 'value' => $p->payment_status ),
                array( 'name' => 'Entry Type', 'value' => $p->entry_type ?? 'paid' ),
                array( 'name' => 'Purchase Date', 'value' => $p->purchase_date ),
                array( 'name' => 'WC Order ID', 'value' => $p->wc_order_id ?? 'N/A' ),
            );

            // Get ticket numbers for this purchase
            $tickets = $wpdb->get_col( $wpdb->prepare(
                "SELECT ticket_number FROM {$tables['tickets']} WHERE purchase_id = %d ORDER BY ticket_number",
                $p->id
            ) );
            if ( ! empty( $tickets ) ) {
                $data[] = array( 'name' => 'Ticket Numbers', 'value' => implode( ', ', $tickets ) );
            }

            $export_items[] = array(
                'group_id'    => 'raffle-purchases',
                'group_label' => 'Raffle Purchases',
                'item_id'     => 'raffle-purchase-' . $p->id,
                'data'        => $data,
            );
        }

        // ── Free Entries ──
        $free = $wpdb->get_results( $wpdb->prepare(
            "SELECT fe.*, r.title as raffle_title
             FROM {$tables['free_entries']} fe
             LEFT JOIN {$wpdb->prefix}raffles r ON r.id = fe.raffle_id
             WHERE fe.buyer_email = %s
             ORDER BY fe.created_at DESC
             LIMIT 500 OFFSET %d",
            $email,
            ( $page - 1 ) * 500
        ) );

        foreach ( $free as $fe ) {
            $export_items[] = array(
                'group_id'    => 'raffle-free-entries',
                'group_label' => 'Raffle Free Entries',
                'item_id'     => 'raffle-free-' . $fe->id,
                'data'        => array(
                    array( 'name' => 'Raffle', 'value' => $fe->raffle_title ?? 'Unknown' ),
                    array( 'name' => 'Name', 'value' => $fe->buyer_name ),
                    array( 'name' => 'Ticket Number', 'value' => $fe->ticket_number ),
                    array( 'name' => 'Status', 'value' => $fe->status ),
                    array( 'name' => 'Date', 'value' => $fe->created_at ),
                ),
            );
        }

        // ── Referrals ──
        $referrals = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tables['referrals']} WHERE referrer_email = %s OR referred_email = %s ORDER BY created_at DESC LIMIT 500",
            $email, $email
        ) );

        foreach ( $referrals as $ref ) {
            $export_items[] = array(
                'group_id'    => 'raffle-referrals',
                'group_label' => 'Raffle Referrals',
                'item_id'     => 'raffle-referral-' . $ref->id,
                'data'        => array(
                    array( 'name' => 'Referrer Email', 'value' => $ref->referrer_email ),
                    array( 'name' => 'Referred Email', 'value' => $ref->referred_email ),
                    array( 'name' => 'Referral Code', 'value' => $ref->referral_code ),
                    array( 'name' => 'Status', 'value' => $ref->status ),
                    array( 'name' => 'Date', 'value' => $ref->created_at ),
                ),
            );
        }

        // ── Instant Wins ──
        $wins = $wpdb->get_results( $wpdb->prepare(
            "SELECT iw.*, r.title as raffle_title
             FROM {$tables['instant_wins']} iw
             LEFT JOIN {$wpdb->prefix}raffles r ON r.id = iw.raffle_id
             WHERE iw.winner_email = %s
             ORDER BY iw.created_at DESC LIMIT 500",
            $email
        ) );

        foreach ( $wins as $w ) {
            $export_items[] = array(
                'group_id'    => 'raffle-instant-wins',
                'group_label' => 'Raffle Instant Win Prizes',
                'item_id'     => 'raffle-instant-win-' . $w->id,
                'data'        => array(
                    array( 'name' => 'Raffle', 'value' => $w->raffle_title ?? 'Unknown' ),
                    array( 'name' => 'Prize', 'value' => $w->prize_name ),
                    array( 'name' => 'Ticket Number', 'value' => $w->ticket_number ),
                    array( 'name' => 'Status', 'value' => $w->status ),
                    array( 'name' => 'Date', 'value' => $w->created_at ),
                ),
            );
        }

        return array(
            'data' => $export_items,
            'done' => true,
        );
    }

    /* ===================================================================
       Data Erasure / Anonymization
       =================================================================== */

    /**
     * Anonymize personal data for a given email.
     * Retains purchase/ticket records for financial/audit compliance but strips PII.
     *
     * @param string $email Email address to anonymize.
     * @param int    $page  Page number for batched erasure.
     * @return array Erasure result in WP privacy format.
     */
    public static function erase_data( $email, $page = 1 ) {
        global $wpdb;
        $tables  = self::get_data_tables();
        $anon_id = self::get_anon_id( $email );

        // ── Anonymize purchases ──
        $wpdb->update(
            $tables['purchases'],
            array(
                'buyer_name'  => 'Deleted User',
                'buyer_email' => 'deleted_' . $anon_id . '@anonymous',
            ),
            array( 'buyer_email' => $email ),
            array( '%s', '%s' ),
            array( '%s' )
        );
        $purchases_affected = $wpdb->rows_affected;

        // ── Anonymize tickets (update buyer_email reference) ──
        $new_email = 'deleted_' . $anon_id . '@anonymous';
        $purchase_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$tables['purchases']} WHERE buyer_email = %s",
            $new_email
        ) );

        if ( ! empty( $purchase_ids ) ) {
            $ids_placeholders = implode( ',', array_fill( 0, count( $purchase_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$tables['tickets']} SET buyer_email = %s WHERE purchase_id IN ({$ids_placeholders})",
                array_merge( array( $new_email ), array_map( 'intval', $purchase_ids ) )
            ) );
        }

        // ── Anonymize free entries ──
        $wpdb->update(
            $tables['free_entries'],
            array(
                'buyer_name'  => 'Deleted User',
                'buyer_email' => $new_email,
            ),
            array( 'buyer_email' => $email ),
            array( '%s', '%s' ),
            array( '%s' )
        );

        // ── Anonymize referrals ──
        $wpdb->update(
            $tables['referrals'],
            array( 'referrer_email' => $new_email ),
            array( 'referrer_email' => $email ),
            array( '%s' ),
            array( '%s' )
        );
        $wpdb->update(
            $tables['referrals'],
            array( 'referred_email' => $new_email ),
            array( 'referred_email' => $email ),
            array( '%s' ),
            array( '%s' )
        );

        // ── Anonymize instant wins ──
        $wpdb->update(
            $tables['instant_wins'],
            array( 'winner_email' => $new_email ),
            array( 'winner_email' => $email ),
            array( '%s' ),
            array( '%s' )
        );

        // ── Audit log ──
        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( 0, 'gdpr_erasure', "Personal data anonymized for email: {$anon_id}. Purchases affected: {$purchases_affected}.", 'system' );
        }

        return array(
            'items_removed'  => $purchases_affected,
            'items_retained' => $purchases_affected, // Financial records retained but anonymized
            'messages'       => array(
                'Raffle purchase data has been anonymized. Ticket and financial records are retained for regulatory compliance but all personal information has been removed.',
            ),
            'done' => true,
        );
    }

    /**
     * Generate a unique anonymous ID from email hash.
     */
    private static function get_anon_id( $email ) {
        return substr( hash( 'sha256', $email . wp_salt( 'auth' ) ), 0, 12 );
    }

    /* ===================================================================
       AJAX: My Data Export (from My Raffles page)
       =================================================================== */

    /**
     * AJAX: Export current user's raffle data as JSON download.
     */
    public static function ajax_export_my_data() {
        if ( ! current_user_can( 'read' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        check_ajax_referer( 'raffle_my_data_nonce', 'nonce' );

        $user_id = get_current_user_id();
        $user    = wp_get_current_user();
        $email   = $user->user_email;

        if ( ! $email ) {
            wp_send_json_error( array( 'message' => 'No email address found.' ) );
        }

        // Also check billing email from WooCommerce
        if ( function_exists( 'wc_get_customer_email' ) ) {
            $billing_email = get_user_meta( $user_id, 'billing_email', true );
            $emails        = array_unique( array_filter( array( $email, $billing_email ) ) );
        } else {
            $emails = array( $email );
        }

        $export = array(
            'generated_at' => current_time( 'mysql' ),
            'user_id'      => $user_id,
            'export_type'  => 'raffle_data',
            'data'         => array(),
        );

        global $wpdb;

        foreach ( $emails as $em ) {
            // Purchases
            $purchases = $wpdb->get_results( $wpdb->prepare(
                "SELECT p.id, p.quantity, p.total_amount, p.payment_status, p.entry_type, p.purchase_date,
                        r.title as raffle_title
                 FROM {$wpdb->prefix}raffle_purchases p
                 LEFT JOIN {$wpdb->prefix}raffles r ON r.id = p.raffle_id
                 WHERE p.buyer_email = %s
                 ORDER BY p.purchase_date DESC",
                $em
            ) );

            foreach ( $purchases as $p ) {
                $tickets = $wpdb->get_col( $wpdb->prepare(
                    "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE purchase_id = %d ORDER BY ticket_number",
                    $p->id
                ) );
                $export['data'][] = array(
                    'type'           => 'purchase',
                    'raffle'         => $p->raffle_title,
                    'quantity'       => (int) $p->quantity,
                    'total_amount'   => (float) $p->total_amount,
                    'payment_status' => $p->payment_status,
                    'entry_type'     => $p->entry_type,
                    'date'           => $p->purchase_date,
                    'tickets'        => array_map( 'intval', $tickets ),
                );
            }

            // Instant wins
            $wins = $wpdb->get_results( $wpdb->prepare(
                "SELECT iw.prize_name, iw.ticket_number, iw.status, iw.created_at,
                        r.title as raffle_title
                 FROM {$wpdb->prefix}raffle_instant_wins iw
                 LEFT JOIN {$wpdb->prefix}raffles r ON r.id = iw.raffle_id
                 WHERE iw.winner_email = %s
                 ORDER BY iw.created_at DESC",
                $em
            ) );

            foreach ( $wins as $w ) {
                $export['data'][] = array(
                    'type'     => 'instant_win',
                    'raffle'   => $w->raffle_title,
                    'prize'    => $w->prize_name,
                    'ticket'   => (int) $w->ticket_number,
                    'status'   => $w->status,
                    'date'     => $w->created_at,
                );
            }

            // Referrals
            $refs = $wpdb->get_results( $wpdb->prepare(
                "SELECT referral_code, status, created_at FROM {$wpdb->prefix}raffle_referrals WHERE referrer_email = %s OR referred_email = %s ORDER BY created_at DESC",
                $em, $em
            ) );

            foreach ( $refs as $ref ) {
                $export['data'][] = array(
                    'type'          => 'referral',
                    'referral_code' => $ref->referral_code,
                    'status'        => $ref->status,
                    'date'          => $ref->created_at,
                );
            }
        }

        // Audit log
        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( 0, 'data_export', "User #{$user_id} exported their raffle data.", 'system' );
        }

        wp_send_json_success( $export );
    }

    /**
     * AJAX: Request account data deletion.
     */
    public static function ajax_request_deletion() {
        if ( ! current_user_can( 'read' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        check_ajax_referer( 'raffle_my_data_nonce', 'nonce' );

        $user_id = get_current_user_id();
        $email   = wp_get_current_user()->user_email;

        if ( ! $email ) {
            wp_send_json_error( array( 'message' => 'No email address found.' ) );
        }

        // Perform anonymization directly (user-initiated)
        $result = self::erase_data( $email );

        // Audit log
        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( 0, 'data_deletion', "User #{$user_id} requested and completed data deletion. Records anonymized.", 'system' );
        }

        wp_send_json_success( array(
            'message'       => 'Your raffle data has been anonymized. Purchase records are retained for regulatory compliance but your personal information has been removed.',
            'items_removed' => $result['items_removed'],
        ) );
    }

    /* ===================================================================
       WooCommerce Privacy Integration
       =================================================================== */

    /**
     * Check if WooCommerce privacy tools are available.
     * If so, raffle data export/erasure is handled automatically via
     * the WP privacy hooks registered above (WC uses WP privacy tools).
     */
    public static function is_wc_privacy_available() {
        return class_exists( 'WC_Privacy_Erasers' ) || class_exists( 'WP_Privacy_Data_Export_Requests_List_Table' );
    }
}