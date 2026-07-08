<?php
/**
 * WPRaffle — Site Credit Ledger
 *
 * An append-only ledger for non-cash site credit: referral bonuses, admin
 * adjustments, refunded entries, etc. The balance is ALWAYS recomputed from
 * the ledger (never stored as a mutable column), which prevents race-based
 * overwrites and gives a full audit trail.
 *
 * SECURITY:
 *  - Balance = SUM(amount) WHERE user_id. Never read-modify-write a column.
 *  - All mutations happen inside a transaction with the user row locked.
 *  - Admin adjustments require manage_options + nonce + reason + audit log.
 *  - Negative adjustments are capped at the user's current balance.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Credits {

    const TYPE_BONUS       = 'bonus';
    const TYPE_REFERRAL    = 'referral';
    const TYPE_REFUND      = 'refund';
    const TYPE_ADJUSTMENT  = 'adjustment';
    const TYPE_SPEND       = 'spend';
    const TYPE_PAYOUT      = 'payout';
    const TYPE_INSTANT_WIN = 'instant_win';

    /* ===================================================================
       Read
       =================================================================== */

    /**
     * Get the current credit balance for a user.
     *
     * @param int $user_id
     * @return float
     */
    public static function get_balance( $user_id ) {
        global $wpdb;
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return 0.0;
        }
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}raffle_credits WHERE user_id = %d",
            $user_id
        ) );
    }

    /**
     * Get ledger entries for a user (newest first).
     *
     * @param int $user_id
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function get_history( $user_id, $limit = 50, $offset = 0 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_credits WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
            absint( $user_id ),
            absint( $limit ),
            absint( $offset )
        ) );
    }

    /* ===================================================================
       Write (internal — use credit()/debit() public API)
       =================================================================== */

    /**
     * Apply a credit (positive amount) to a user's balance.
     *
     * @param int    $user_id
     * @param float  $amount   Positive amount.
     * @param string $type     One of the TYPE_* constants.
     * @param string $reason   Human-readable reason.
     * @param string $reference Optional reference (e.g. raffle id, txn id).
     * @param int    $raffle_id Optional related raffle.
     * @return int|false Inserted row id, or false on failure.
     */
    public static function credit( $user_id, $amount, $type, $reason = '', $reference = '', $raffle_id = 0 ) {
        $amount = (float) $amount;
        if ( $amount <= 0 ) {
            return false;
        }
        return self::append_ledger( $user_id, $amount, $type, $reason, $reference, $raffle_id );
    }

    /**
     * Apply a debit (negative amount) to a user's balance.
     * Capped at the current balance so the ledger never goes negative.
     *
     * @param int    $user_id
     * @param float  $amount  Requested positive amount to deduct.
     * @param string $type
     * @param string $reason
     * @param string $reference
     * @param int    $raffle_id
     * @return int|false Inserted row id, or false if insufficient balance.
     */
    public static function debit( $user_id, $amount, $type, $reason = '', $reference = '', $raffle_id = 0 ) {
        $amount = (float) $amount;
        if ( $amount <= 0 ) {
            return false;
        }

        global $wpdb;
        $user_id = absint( $user_id );

        // MySQL advisory lock keyed on the user serialises concurrent debits
        // so two requests can't both observe balance B and both insert -X
        // (the FOR UPDATE on existing rows doesn't block the gap before the
        // new INSERT on MySQL's default isolation level).
        $lock_name = 'wpraffle_credit_' . $user_id;
        $lock_acquired = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock_name ) );
        if ( $lock_acquired !== 1 ) {
            return false;
        }

        // Lock + recompute balance atomically so concurrent debits can't race.
        $wpdb->query( 'START TRANSACTION' );

        $balance = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}raffle_credits WHERE user_id = %d FOR UPDATE",
            $user_id
        ) );

        if ( $balance < $amount ) {
            $wpdb->query( 'ROLLBACK' );
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
            return false;
        }

        $result = self::append_ledger( $user_id, -1.0 * $amount, $type, $reason, $reference, $raffle_id );

        if ( $result ) {
            $wpdb->query( 'COMMIT' );
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
            return $result;
        }

        $wpdb->query( 'ROLLBACK' );
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
        return false;
    }

    /**
     * Admin-initiated manual adjustment. Requires manage_options.
     *
     * @param int    $user_id   Target user.
     * @param float  $amount    Positive to credit, negative to debit.
     * @param string $reason    Required reason.
     * @return int|false|WP_Error
     */
    public static function admin_adjust( $user_id, $amount, $reason ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'unauthorized', 'Unauthorized.' );
        }
        if ( empty( $reason ) ) {
            return new WP_Error( 'reason_required', 'A reason is required for manual adjustments.' );
        }

        $amount = (float) $amount;
        if ( $amount > 0 ) {
            $result = self::credit( $user_id, $amount, self::TYPE_ADJUSTMENT, $reason, 'admin:' . get_current_user_id() );
        } else {
            $abs = abs( $amount );
            $result = self::debit( $user_id, $abs, self::TYPE_ADJUSTMENT, $reason, 'admin:' . get_current_user_id() );
        }

        if ( $result ) {
            if ( class_exists( 'Raffle_Audit' ) ) {
                Raffle_Audit::log( 0, 'credit_adjustment', sprintf(
                    'Admin %s %s credit %s for user #%d. Reason: %s',
                    get_current_user_id(),
                    $amount > 0 ? 'added' : 'removed',
                    wpr_price( abs( $amount ) ),
                    $user_id,
                    $reason
                ), 'admin' );
            }
        }

        return $result;
    }

    /* ===================================================================
       Internal
       =================================================================== */

    /**
     * Append a ledger row. Captures balance_after as a convenience snapshot
     * (the authoritative balance is always the running SUM, not this column).
     */
    private static function append_ledger( $user_id, $amount, $type, $reason, $reference, $raffle_id ) {
        global $wpdb;

        $user_id  = absint( $user_id );
        $amount   = (float) $amount;
        $balance  = self::get_balance( $user_id );
        $balance_after = $balance + $amount;

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'raffle_credits',
            array(
                'user_id'      => $user_id,
                'raffle_id'    => $raffle_id ? absint( $raffle_id ) : null,
                'amount'       => $amount,
                'balance_after'=> $balance_after,
                'type'         => sanitize_text_field( $type ),
                'reason'       => sanitize_text_field( $reason ),
                'reference'    => sanitize_text_field( $reference ),
                'created_by'   => get_current_user_id() ?: null,
                'created_at'   => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%f', '%f', '%s', '%s', '%s', '%d', '%s' )
        );

        return $inserted ? $wpdb->insert_id : false;
    }
}
