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
            sprintf(
                /* translators: 1: payout type (e.g. "Cash"), 2: raffle ID. */
                __( 'Raffle winnings — %1$s #%2$d', 'wpraffle' ),
                $payout_type,
                $raffle_id
            ),
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
       Instant-Win credit (1.3.0)
       =================================================================== */

    /**
     * Credit an instant-win prize to the winner's live wallet balance
     * (WooWallet / TerraWallet / WooWallet Pro). Mirrors credit_winnings() but
     * with an instant-win idempotency key so the same prize can never be paid
     * twice and a refund/cancel can reverse it cleanly.
     *
     * @param int    $win_id   Instant-win row id.
     * @param int    $user_id  Winner's user id (0 = guest → not creditable).
     * @param string $email    Winner's email (for the ledger record).
     * @param float  $amount   Credit amount.
     * @param string $prize_name Prize name (for the wallet transaction note).
     * @return int|WP_Error Payout row id on success; WP_Error on failure.
     */
    public static function credit_instant_win( $win_id, $user_id, $email, $amount, $prize_name = '' ) {
        global $wpdb;

        $win_id  = absint( $win_id );
        $user_id = absint( $user_id );
        $amount  = (float) $amount;
        $email   = sanitize_email( $email );
        $idempotency_key = 'iw:' . $win_id;

        // Idempotency: if we've already recorded this payout, return it.
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_payouts WHERE idempotency_key = %s",
            $idempotency_key
        ) );
        if ( $existing ) {
            return (int) $existing->id;
        }

        // Guest winners (no user id) can't receive a wallet credit — the
        // operator must settle manually. Record a 'pending' row so it's
        // visible in the ledger, then bail.
        if ( ! $user_id || $amount <= 0 ) {
            $wpdb->insert( $wpdb->prefix . 'raffle_payouts', array(
                'raffle_id'       => 0,
                'ticket_id'       => 0,
                'user_id'         => $user_id ?: null,
                'user_email'      => $email,
                'payout_type'     => 'instant_win',
                'amount'          => $amount,
                'status'          => 'pending',
                'idempotency_key' => $idempotency_key,
                'provider'        => 'none',
                'fairness_proof'  => '',
                'created_at'      => current_time( 'mysql' ),
            ), array( '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ) );
            return new WP_Error( 'guest_winner', __( 'Instant-win credit requires a logged-in user; guest prize recorded for manual settlement.', 'wpraffle' ) );
        }

        $provider = self::is_available() ? ( self::is_pro() ? 'woowallet_pro' : 'woowallet' ) : 'none';

        $wpdb->query( 'START TRANSACTION' );

        $inserted = $wpdb->insert( $wpdb->prefix . 'raffle_payouts', array(
            'raffle_id'       => 0,
            'ticket_id'       => 0,
            'user_id'         => $user_id,
            'user_email'      => $email,
            'payout_type'     => 'instant_win',
            'amount'          => $amount,
            'status'          => 'pending',
            'idempotency_key' => $idempotency_key,
            'provider'        => $provider,
            'fairness_proof'  => '',
            'created_at'      => current_time( 'mysql' ),
        ), array( '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ) );

        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'ledger_failed', 'Failed to record instant-win payout.' );
        }

        $payout_id = $wpdb->insert_id;

        if ( ! self::is_available() ) {
            // No wallet plugin — leave as 'pending' for manual settlement.
            $wpdb->query( 'COMMIT' );
            return $payout_id;
        }

        $txn_id = woo_wallet()->wallet->credit(
            $user_id,
            $amount,
            $prize_name
                ? sprintf( __( 'Instant win — %s', 'wpraffle' ), $prize_name ) // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- translators: %s: prize name.
                : __( 'Instant win prize', 'wpraffle' ),
            array(
                'source'    => 'wpraffle_instant_win',
                'instant_win_id' => $win_id,
            )
        );

        if ( ! $txn_id ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'wallet_credit_failed', self::get_wallet_name() . ' credit failed.' );
        }

        $wpdb->update( $wpdb->prefix . 'raffle_payouts', array(
            'status'          => 'credited',
            'provider_txn_id' => (string) $txn_id,
            'credited_at'     => current_time( 'mysql' ),
        ), array( 'id' => $payout_id ), array( '%s', '%s', '%s' ), array( '%d' ) );

        $wpdb->query( 'COMMIT' );

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( 0, 'instant_win_wallet_credit', array(
                'instant_win_id' => $win_id,
                'user_id'        => $user_id,
                'amount'         => $amount,
                'txn_id'         => $txn_id,
            ), 'wallet' );
        }

        return $payout_id;
    }

    /**
     * Reverse an instant-win wallet credit (on cancel/refund/failed). Debits
     * the amount back from the live wallet. Idempotent: no-op if no credited
     * payout exists for this instant-win id.
     *
     * @param int $win_id Instant-win row id.
     * @return true|WP_Error
     */
    public static function debit_instant_win( $win_id ) {
        global $wpdb;
        $win_id = absint( $win_id );

        $payout = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_payouts WHERE idempotency_key = %s AND payout_type = 'instant_win'",
            'iw:' . $win_id
        ) );
        if ( ! $payout ) {
            return true; // Never credited — nothing to reverse.
        }
        if ( $payout->status !== 'credited' ) {
            return true; // Pending/guest — never hit the wallet.
        }
        if ( ! self::is_available() || ! $payout->user_id ) {
            return true;
        }

        $txn_id = woo_wallet()->wallet->debit(
            (int) $payout->user_id,
            (float) $payout->amount,
            __( 'Instant win reversed (order cancelled/refunded)', 'wpraffle' ),
            array(
                'source'         => 'wpraffle_instant_win',
                'instant_win_id' => $win_id,
            )
        );

        if ( ! $txn_id ) {
            return new WP_Error( 'wallet_debit_failed', self::get_wallet_name() . ' debit failed.' );
        }

        $wpdb->update( $wpdb->prefix . 'raffle_payouts', array(
            'status' => 'reversed',
        ), array( 'id' => $payout->id ), array( '%s' ), array( '%d' ) );

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( 0, 'instant_win_wallet_reversed', array(
                'instant_win_id' => $win_id,
                'user_id'        => $payout->user_id,
                'amount'         => $payout->amount,
            ), 'wallet' );
        }

        return true;
    }

    /* ===================================================================
       Manual re-sync of missed payouts (1.3.0)
       =================================================================== */

    /**
     * Reconcile instant-win credit prizes against the live wallet.
     *
     * A TRUE reconciliation — not just a re-scan of the payouts table. It finds
     * every credit-type instant win that should have reached the wallet but
     * hasn't, and (re-)attempts the credit. This catches two failure modes:
     *
     *   1. Orphaned wins: a win was marked 'won' but no payout row was ever
     *      created (e.g. the wallet plugin was inactive, or the prize was won
     *      before the wallet-bridge code shipped). These have NO payout row, so
     *      a simple "scan pending payouts" finds nothing. We resolve the winner
     *      from the instant-win's purchase/email and credit them.
     *   2. Pending payout rows: a payout row exists with status 'pending' (e.g.
     *      guest winner or transient wallet failure) and can be re-attempted.
     *
     * Both paths funnel through credit_instant_win(), which is idempotent, so
     * already-credited prizes are skipped and it is safe to run repeatedly.
     *
     * @param int $raffle_id Optional: limit to one raffle. 0 = all raffles.
     * @return array { 'processed' => int, 'credited' => int, 'still_pending' => int, 'errors' => string[] }
     */
    public static function sync_pending_payouts( $raffle_id = 0 ) {
        global $wpdb;
        $raffle_id = absint( $raffle_id );

        $result = array(
            'processed'     => 0,
            'credited'      => 0,
            'still_pending' => 0,
            'errors'        => array(),
        );

        // ── Phase 1: orphaned credit wins with no payout row at all. ──────
        // Find every credit-type instant win that is 'won' (so it has a
        // winner + purchase), and LEFT JOIN the payouts table to spot those
        // with no payout row. These are the wins the auto-credit path missed.
        $iw_table   = $wpdb->prefix . 'raffle_instant_wins';
        $pay_table  = $wpdb->prefix . 'raffle_payouts';
        $purch_table = $wpdb->prefix . 'raffle_purchases';

        $where  = "iw.status = 'won' AND iw.prize_type = 'credit'";
        $join   = "LEFT JOIN {$pay_table} pay ON pay.idempotency_key = CONCAT('iw:', iw.id)";
        // Resolve the winner's user id from the WP user matching the purchase email.
        if ( $raffle_id ) {
            $orphans = $wpdb->get_results( $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — table names + static SQL only.
                "SELECT iw.id AS iw_id, iw.raffle_id, iw.ticket_number, iw.prize_name, iw.prize_config, iw.winner_email,
                        p.buyer_email AS purchase_email, pay.id AS pay_id, pay.status AS pay_status
                 FROM {$iw_table} iw
                 {$join}
                 LEFT JOIN {$purch_table} p ON p.id = iw.purchase_id
                 WHERE {$where} AND iw.raffle_id = %d",
                $raffle_id
            ) );
        } else {
            $orphans = $wpdb->get_results(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — table names + static SQL only.
                "SELECT iw.id AS iw_id, iw.raffle_id, iw.ticket_number, iw.prize_name, iw.prize_config, iw.winner_email,
                        p.buyer_email AS purchase_email, pay.id AS pay_id, pay.status AS pay_status
                 FROM {$iw_table} iw
                 {$join}
                 LEFT JOIN {$purch_table} p ON p.id = iw.purchase_id
                 WHERE {$where}"
            );
        }

        foreach ( $orphans as $ow ) {
            // Skip if a credited payout already exists — nothing to do.
            if ( $ow->pay_id && $ow->pay_status === 'credited' ) {
                continue;
            }

            $result['processed']++;

            // Recover the winner's user id + email + amount.
            $email   = $ow->winner_email ?: ( $ow->purchase_email ?: '' );
            $config  = json_decode( $ow->prize_config ?: '', true );
            $amount  = isset( $config['amount'] ) ? (float) $config['amount'] : 0;
            $user    = $email ? get_user_by( 'email', $email ) : false;
            $user_id = $user ? (int) $user->ID : 0;

            if ( $amount <= 0 ) {
                $result['errors'][] = sprintf(
                    /* translators: instant-win id. */
                    __( 'Instant win #%d: no credit amount configured — check the prize config.', 'wpraffle' ),
                    $ow->iw_id
                );
                $result['still_pending']++;
                continue;
            }

            $credit_result = self::credit_instant_win( $ow->iw_id, $user_id, $email, $amount, $ow->prize_name ?: '' );

            if ( is_wp_error( $credit_result ) ) {
                // 'guest_winner' is expected for non-logged-in winners; record
                // as pending (recoverable) rather than a hard error.
                if ( $credit_result->get_error_code() === 'guest_winner' ) {
                    $result['still_pending']++;
                } else {
                    $result['still_pending']++;
                    $result['errors'][] = sprintf(
                        /* translators: 1: instant-win id, 2: error message. */
                        __( 'Instant win #%1$d: %2$s', 'wpraffle' ),
                        $ow->iw_id,
                        $credit_result->get_error_message()
                    );
                }
            } else {
                // Confirm it actually credited by re-reading the payout row.
                $new_status = $wpdb->get_var( $wpdb->prepare(
                    "SELECT status FROM {$pay_table} WHERE idempotency_key = %s",
                    'iw:' . (int) $ow->iw_id
                ) );
                if ( $new_status === 'credited' ) {
                    $result['credited']++;
                } else {
                    $result['still_pending']++;
                }
            }
        }

        // ── Phase 2: pending payout rows that pre-date the orphan scan ───
        // (e.g. created by an older code path). credit_instant_win() is
        // idempotent, so re-attempting already-handled rows from Phase 1 is
        // a safe no-op.
        if ( $raffle_id ) {
            $pending = $wpdb->get_results( $wpdb->prepare(
                "SELECT pay.* FROM {$pay_table} pay
                 JOIN {$iw_table} iw ON pay.idempotency_key = CONCAT('iw:', iw.id)
                 WHERE pay.status = 'pending' AND pay.payout_type = 'instant_win' AND iw.raffle_id = %d",
                $raffle_id
            ) );
        } else {
            $pending = $wpdb->get_results(
                "SELECT * FROM {$pay_table} WHERE status = 'pending' AND payout_type = 'instant_win'"
            );
        }

        foreach ( $pending as $payout ) {
            $result['processed']++;
            $win_id = 0;
            if ( preg_match( '/^iw:(\d+)$/', $payout->idempotency_key, $m ) ) {
                $win_id = (int) $m[1];
            }
            if ( ! $win_id ) {
                continue;
            }
            $prize_name = $wpdb->get_var( $wpdb->prepare( "SELECT prize_name FROM {$iw_table} WHERE id = %d", $win_id ) );
            $credit_result = self::credit_instant_win( $win_id, (int) $payout->user_id, $payout->user_email, (float) $payout->amount, $prize_name ?: '' );
            if ( is_wp_error( $credit_result ) && $credit_result->get_error_code() !== 'guest_winner' ) {
                $result['still_pending']++;
                $result['errors'][] = sprintf( __( 'Payout #%1$d: %2$s', 'wpraffle' ), $payout->id, $credit_result->get_error_message() ); // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- translators: 1: payout ID, 2: error message.
            } elseif ( ! is_wp_error( $credit_result ) ) {
                $result['credited']++;
            } else {
                $result['still_pending']++;
            }
        }

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( (int) $raffle_id, 'wallet_payout_sync', array(
                'processed'     => $result['processed'],
                'credited'      => $result['credited'],
                'still_pending' => $result['still_pending'],
            ), 'admin' );
        }

        return $result;
    }

    /**
     * Count instant-win credit prizes that need attention (for the admin
     * button badge). This counts BOTH orphaned won-credit-prizes with no
     * payout row AND pending payout rows — matching what sync_pending_payouts
     * will actually process.
     *
     * @param int $raffle_id Optional raffle scope.
     * @return int
     */
    public static function count_pending_payouts( $raffle_id = 0 ) {
        global $wpdb;
        $raffle_id = absint( $raffle_id );
        $iw_table  = $wpdb->prefix . 'raffle_instant_wins';
        $pay_table = $wpdb->prefix . 'raffle_payouts';
        $join      = "LEFT JOIN {$pay_table} pay ON pay.idempotency_key = CONCAT('iw:', iw.id)";

        // Orphaned wins: won credit prizes where no credited payout exists.
        if ( $raffle_id ) {
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — static SQL + table names.
                "SELECT COUNT(*)
                 FROM {$iw_table} iw {$join}
                 WHERE iw.status = 'won' AND iw.prize_type = 'credit' AND iw.raffle_id = %d
                 AND (pay.id IS NULL OR pay.status != 'credited')",
                $raffle_id
            ) );
        } else {
            $count = (int) $wpdb->get_var(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — static SQL + table names.
                "SELECT COUNT(*)
                 FROM {$iw_table} iw {$join}
                 WHERE iw.status = 'won' AND iw.prize_type = 'credit'
                 AND (pay.id IS NULL OR pay.status != 'credited')"
            );
        }
        return $count;
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
