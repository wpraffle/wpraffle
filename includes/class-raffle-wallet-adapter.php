<?php
/**
 * WPRaffle — Wallet Adapter (WooWallet / WooWallet Pro)
 *
 * Thin, security-hardened adapter over the WooWallet plugin API.
 * Works with both free WooWallet and WooWallet Pro (formerly TerraWallet).
 *
 * SECURITY:
 *  - Every credit is idempotent by (raffle_id, ticket_id, payout_type)
 *  - No withdrawal/debit exposed from WPRaffle
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Wallet_Adapter {

    /**
     * Whether WooWallet (free or Pro) is active.
     */
    public static function is_available() {
        return function_exists( 'woo_wallet' );
    }

    /**
     * Whether the PRO version is active.
     */
    public static function is_pro() {
        return class_exists( 'Woo_Wallet_Pro' ) || defined( 'WOO_WALLET_PRO_VERSION' );
    }

    /**
     * Human-readable wallet name for UI.
     */
    public static function get_wallet_name() {
        return self::is_pro() ? __( 'WooWallet Pro', 'wpraffle' ) : __( 'WooWallet', 'wpraffle' );
    }

    /**
     * Get wallet URL (WooWallet registers a 'wallet' endpoint on My Account).
     */
    public static function get_wallet_url() {
        if ( function_exists( 'wc_get_endpoint_url' ) ) {
            return wc_get_endpoint_url( 'wallet', '', wc_get_page_permalink( 'myaccount' ) );
        }
        return home_url( '/my-account/wallet/' );
    }

    /* ===================================================================
       Balance & History
       =================================================================== */

    public static function get_balance( $user_id ) {
        if ( ! self::is_available() ) {
            return 0.0;
        }
        return (float) woo_wallet()->wallet->get_wallet_balance( absint( $user_id ), 'display' );
    }

    public static function get_transactions( $user_id, $limit = 20 ) {
        if ( ! self::is_available() ) {
            return array();
        }
        $user_id = absint( $user_id );
        // WooWallet API varies by version — try get_transactions first,
        // then fall back to get_statement or a direct DB query.
        $wallet = woo_wallet()->wallet;
        if ( method_exists( $wallet, 'get_transactions' ) ) {
            return $wallet->get_transactions( $user_id, $limit );
        }
        // Fallback: query the WooWallet transactions table directly
        global $wpdb;
        $table = $wpdb->prefix . 'woo_wallet_transactions';
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created DESC LIMIT %d",
            $user_id, absint( $limit )
        ) );
        return $results ?: array();
    }

    /* ===================================================================
       Winnings Payout (idempotent)
       =================================================================== */

    public static function credit_winnings( $raffle_id, $ticket_id, $user_id, $user_email, $amount, $payout_type, $fairness_proof = '' ) {
        global $wpdb;

        $raffle_id   = absint( $raffle_id );
        $ticket_id   = absint( $ticket_id );
        $user_id     = absint( $user_id );
        $amount      = (float) $amount;
        $payout_type = sanitize_text_field( $payout_type );
        $idempotency_key = $raffle_id . ':' . $ticket_id . ':' . $payout_type;

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_payouts WHERE idempotency_key = %s",
            $idempotency_key
        ) );

        if ( $existing ) {
            return (int) $existing->id;
        }

        $provider = self::is_available() ? ( self::is_pro() ? 'woowallet_pro' : 'woowallet' ) : 'none';

        // Wrap the insert + wallet credit + status flip in a single
        // transaction so a transient wallet failure rolls the payout row
        // back (rather than leaving a 'failed' row that blocks retry via the
        // idempotency pre-check above).
        $wpdb->query( 'START TRANSACTION' );

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'raffle_payouts',
            array(
                'raffle_id'      => $raffle_id,
                'ticket_id'      => $ticket_id,
                'user_id'        => $user_id ?: null,
                'user_email'     => sanitize_email( $user_email ),
                'payout_type'    => $payout_type,
                'amount'         => $amount,
                'status'         => 'pending',
                'idempotency_key'=> $idempotency_key,
                'provider'       => $provider,
                'fairness_proof' => sanitize_text_field( $fairness_proof ),
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'ledger_failed', 'Failed to record payout.' );
        }

        $payout_id = $wpdb->insert_id;

        if ( ! $user_id || $amount <= 0 || ! self::is_available() ) {
            // No wallet credit needed — flip straight to 'credited' (or leave
            // 'pending' for $0/missing-user rows) and commit.
            if ( ! $user_id || $amount <= 0 ) {
                $wpdb->update(
                    $wpdb->prefix . 'raffle_payouts',
                    array( 'status' => 'credited', 'credited_at' => current_time( 'mysql' ) ),
                    array( 'id' => $payout_id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
            }
            $wpdb->query( 'COMMIT' );
            return $payout_id;
        }

        $txn_id = woo_wallet()->wallet->credit(
            $user_id,
            $amount,
            sprintf( __( 'Raffle winnings — %s #%d', 'wpraffle' ), $payout_type, $raffle_id ),
            array(
                'source'    => 'wpraffle',
                'raffle_id' => $raffle_id,
                'ticket_id' => $ticket_id,
            )
        );

        if ( ! $txn_id ) {
            // Roll back the pending payout row so the next retry can re-attempt
            // (the idempotency pre-check would otherwise short-circuit it).
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'wallet_credit_failed', self::get_wallet_name() . ' credit failed.' );
        }

        $wpdb->update(
            $wpdb->prefix . 'raffle_payouts',
            array(
                'status'          => 'credited',
                'provider_txn_id' => (string) $txn_id,
                'credited_at'     => current_time( 'mysql' ),
            ),
            array( 'id' => $payout_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        $wpdb->query( 'COMMIT' );

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( $raffle_id, 'wallet_payout', array(
                'ticket_id' => $ticket_id,
                'user_id'   => $user_id,
                'amount'    => $amount,
                'type'      => $payout_type,
                'txn_id'    => $txn_id,
            ), $fairness_proof ?: 'wallet' );
        }

        return $payout_id;
    }

    /* ===================================================================
       Winnings Hold
       =================================================================== */

    public static function get_hold_hours() {
        $advanced = wp_parse_args( get_option( 'wpraffle_advanced_settings', array() ), array(
            'winnings_hold_hours' => 24,
        ) );
        return max( 0, absint( $advanced['winnings_hold_hours'] ) );
    }
}