<?php
/**
 * WPRaffle — Raffle Lifecycle (relist / extend / min-tickets fail)
 *
 * Phase 2 (1.3.0). Adds the lifecycle states and automation that rivals
 * ship but we lacked:
 *   - Min-tickets / min-unique-users threshold → a raffle can FAIL (and
 *     auto-refund participants) instead of silently drawing.
 *   - Extend — push the draw date out (reopens an ended raffle).
 *   - Relist — reset a finished/failed raffle in place, snapshot history,
 *     clear entries, re-instantiate instant wins. Reuses the same raffle id
 *     + WC product (preserves permalink/SEO), unlike clone().
 *
 * All transitions fire their own action so Phase 3 emails can listen in.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Lifecycle {

    public function __construct() {
        // Auto-refund participants of a failed raffle (gated by per-raffle flag).
        add_action( 'wpraffle_raffle_failed', array( __CLASS__, 'maybe_auto_refund' ), 10, 2 );
        // Schedule the auto-relist check (hourly).
        add_action( 'wpraffle_relist_check', array( __CLASS__, 'maybe_auto_relist' ) );
    }

    /* ===================================================================
       Min-thresholds / fail
       =================================================================== */

    /**
     * Evaluate whether a raffle meets its minimum-subscription thresholds.
     * Called from Raffle_Draw::do_draw() before winner selection. On failure,
     * flips status to 'failed', sets fail_reason, commits, fires the
     * wpraffle_raffle_failed action, and returns a WP_Error so the caller
     * aborts the draw.
     *
     * NOTE: this method must be called BEFORE the draw's START TRANSACTION so
     * it can run its own self-contained transaction without nesting.
     *
     * @param object $raffle Raffle row.
     * @return true|WP_Error True if thresholds are met (or none set); WP_Error if failed.
     */
    public static function evaluate_min_thresholds( $raffle ) {
        global $wpdb;
        $table_raffles = $wpdb->prefix . 'raffles';

        $min_tickets = isset( $raffle->min_tickets ) ? (int) $raffle->min_tickets : 0;
        $min_users   = isset( $raffle->min_unique_users ) ? (int) $raffle->min_unique_users : 0;

        if ( $min_tickets <= 0 && $min_users <= 0 ) {
            return true; // No thresholds set.
        }

        $fail_reason = '';
        if ( $min_tickets > 0 && (int) $raffle->sold_tickets < $min_tickets ) {
            $fail_reason = 'min_tickets';
        }

        if ( '' === $fail_reason && $min_users > 0 ) {
            $unique = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT buyer_email) FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d",
                $raffle->id
            ) );
            if ( $unique < $min_users ) {
                $fail_reason = 'min_users';
            }
        }

        if ( '' === $fail_reason ) {
            return true; // Thresholds met.
        }

        // Fail the raffle in its own transaction.
        $wpdb->query( 'START TRANSACTION' );
        $wpdb->update(
            $table_raffles,
            array(
                'status'      => 'failed',
                'fail_reason' => $fail_reason,
            ),
            array( 'id' => $raffle->id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
        $wpdb->query( 'COMMIT' );

        if ( function_exists( 'wpraffle_flush_raffle_cache' ) ) {
            wpraffle_flush_raffle_cache( $raffle->id );
        }
        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( $raffle->id, 'raffle_failed', array(
                'reason'       => $fail_reason,
                'sold_tickets' => (int) $raffle->sold_tickets,
                'min_tickets'  => $min_tickets,
                'min_users'    => $min_users,
            ), 'system' );
        }

        /**
         * Fires when a raffle fails its min-thresholds gate. Listeners:
         * auto-refund (this class), failed-participant + admin emails (Phase 3).
         */
        do_action( 'wpraffle_raffle_failed', $raffle->id, $fail_reason );

        return new WP_Error( 'under_subscribed', 'Raffle failed: ' . $fail_reason . ' threshold not met.' );
    }

    /* ===================================================================
       Auto-refund on fail
       =================================================================== */

    /**
     * Listener for wpraffle_raffle_failed. If the raffle has auto_refund_on_fail
     * enabled (read from its discount_rules/feature config — reusing the
     * pattern of JSON config columns), issue a WC refund for each paid
     * purchase via wc_create_refund(). Idempotent via a per-raffle meta flag.
     */
    public static function maybe_auto_refund( $raffle_id, $fail_reason ) {
        global $wpdb;
        $table_raffles = $wpdb->prefix . 'raffles';

        $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_raffles} WHERE id = %d", $raffle_id ) );
        if ( ! $raffle ) {
            return;
        }

        // Auto-refund is opt-in via a column; default off so existing behaviour
        // is unchanged. The flag is stored on the raffle row as a tiny feature
        // flag (added to the admin form in Phase 2 UI work).
        if ( empty( $raffle->auto_refund_on_fail ) ) {
            return;
        }
        // Idempotency: don't refund twice.
        $done = get_transient( 'wpraffle_autorefund_' . $raffle_id );
        if ( $done ) {
            return;
        }

        if ( ! function_exists( 'wc_create_refund' ) ) {
            return;
        }

        $purchases = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, wc_order_id, total_amount FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d AND payment_status = 'completed' AND wc_order_id > 0",
            $raffle_id
        ) );

        foreach ( $purchases as $purchase ) {
            $order = wc_get_order( $purchase->wc_order_id );
            if ( ! $order ) {
                continue;
            }
            try {
                $refund = wc_create_refund( array(
                    'order_id' => $purchase->wc_order_id,
                    'amount'   => (float) $purchase->total_amount,
                    'reason'   => sprintf( __( 'Raffle #%d did not meet its minimum threshold.', 'wpraffle' ), $raffle_id ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- translators: %d: raffle ID.
                ) );
                if ( $refund && ! is_wp_error( $refund ) ) {
                    if ( class_exists( 'Raffle_Audit' ) ) {
                        Raffle_Audit::log( $raffle_id, 'auto_refund', array(
                            'order'   => $purchase->wc_order_id,
                            'amount'  => $purchase->total_amount,
                        ), 'system' );
                    }
                }
            } catch ( Exception $e ) {
                if ( class_exists( 'Raffle_Audit' ) ) {
                    Raffle_Audit::log( $raffle_id, 'auto_refund_error', array(
                        'order'  => $purchase->wc_order_id,
                        'error'  => $e->getMessage(),
                    ), 'system' );
                }
            }
        }

        set_transient( 'wpraffle_autorefund_' . $raffle_id, 1, DAY_IN_SECONDS );
    }

    /* ===================================================================
       Extend
       =================================================================== */

    /**
     * Push a raffle's draw date out and reopen it. Records the previous draw
     * date in extended_from for audit. Fires wpraffle_raffle_extended.
     *
     * @param int      $raffle_id
     * @param string   $new_draw_date MySQL datetime.
     * @return true|WP_Error
     */
    public static function extend_raffle( $raffle_id, $new_draw_date ) {
        global $wpdb;
        $table_raffles = $wpdb->prefix . 'raffles';

        $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_raffles} WHERE id = %d", $raffle_id ) );
        if ( ! $raffle ) {
            return new WP_Error( 'not_found', __( 'Raffle not found.', 'wpraffle' ) );
        }
        $ts = strtotime( $new_draw_date );
        if ( ! $ts ) {
            return new WP_Error( 'bad_date', __( 'Invalid draw date.', 'wpraffle' ) );
        }
        $mysql_date = gmdate( 'Y-m-d H:i:s', $ts );

        $wpdb->update(
            $table_raffles,
            array(
                'draw_date'     => $mysql_date,
                'status'        => 'active',
                'fail_reason'   => '',
                'extended_from' => $raffle->draw_date,
                'reminder_sent' => 0,
            ),
            array( 'id' => $raffle_id ),
            array( '%s', '%s', '%s', '%s', '%d' ),
            array( '%d' )
        );

        if ( function_exists( 'wpraffle_flush_raffle_cache' ) ) {
            wpraffle_flush_raffle_cache( $raffle_id );
        }
        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( $raffle_id, 'raffle_extended', array(
                'from' => $raffle->draw_date,
                'to'   => $mysql_date,
            ), 'admin' );
        }

        /**
         * Fires after a raffle is extended. Listeners: extended email (Phase 3),
         * cache flush (Phase 4), WC product stock sync.
         */
        do_action( 'wpraffle_raffle_extended', $raffle_id, $mysql_date, $raffle->draw_date );

        return true;
    }

    /* ===================================================================
       Relist
       =================================================================== */

    /**
     * Reset a finished/failed raffle in place: snapshot history, clear entries
     * + winners, re-instantiate instant wins, reopen the WC product. Reuses the
     * same raffle id (preserves permalink/SEO), unlike clone().
     *
     * @param int    $raffle_id
     * @param string $new_draw_date Optional new draw date (defaults to +7 days).
     * @return true|WP_Error
     */
    public static function relist_raffle( $raffle_id, $new_draw_date = '' ) {
        global $wpdb;
        $table_raffles    = $wpdb->prefix . 'raffles';
        $table_tickets    = $wpdb->prefix . 'raffle_tickets';
        $table_purchases  = $wpdb->prefix . 'raffle_purchases';
        $table_relists    = $wpdb->prefix . 'raffle_relists';

        $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_raffles} WHERE id = %d FOR UPDATE", $raffle_id ) );
        if ( ! $raffle ) {
            return new WP_Error( 'not_found', __( 'Raffle not found.', 'wpraffle' ) );
        }

        // Snapshot the current state into the relist history table.
        $wpdb->insert(
            $table_relists,
            array(
                'raffle_id'   => $raffle_id,
                'snapshot'    => wp_json_encode( $raffle ),
                'ticket_count' => (int) $raffle->sold_tickets,
                'status'      => $raffle->status,
                'relisted_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%d', '%s', '%s' )
        );

        // Compute new draw date (default: +7 days from now).
        if ( empty( $new_draw_date ) ) {
            $new_draw_date = gmdate( 'Y-m-d H:i:s', time() + ( 7 * DAY_IN_SECONDS ) );
        } else {
            $ts = strtotime( $new_draw_date );
            $new_draw_date = $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : gmdate( 'Y-m-d H:i:s', time() + ( 7 * DAY_IN_SECONDS ) );
        }

        // Reset the raffle row.
        $wpdb->query( 'START TRANSACTION' );

        $wpdb->delete( $table_tickets, array( 'raffle_id' => $raffle_id ), array( '%d' ) );
        $wpdb->delete( $table_purchases, array( 'raffle_id' => $raffle_id ), array( '%d' ) );

        $wpdb->update(
            $table_raffles,
            array(
                'sold_tickets'     => 0,
                'winner_ticket_id' => null,
                'status'           => 'active',
                'fail_reason'      => '',
                'extended_from'    => null,
                'reminder_sent'    => 0,
                'draw_date'        => $new_draw_date,
            ),
            array( 'id' => $raffle_id ),
            array( '%d', null, '%s', '%s', null, '%d', '%s' ),
            array( '%d' )
        );

        $wpdb->query( 'COMMIT' );

        // Re-instantiate instant wins to 'available' (reverses any assigned prizes).
        if ( class_exists( 'Raffle_Instant_Wins' ) ) {
            Raffle_Instant_Wins::reset_for_raffle( $raffle_id );
        }

        // Reset the linked WC product stock.
        if ( ! empty( $raffle->wc_product_id ) && function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $raffle->wc_product_id );
            if ( $product ) {
                if ( $raffle->total_tickets ) {
                    wc_update_product_stock( $product, (int) $raffle->total_tickets, 'set' );
                }
                // Re-publish if it had been set to a non-purchasable state.
                if ( in_array( $raffle->status, array( 'failed' ), true ) ) {
                    $product->set_status( 'publish' );
                    $product->save();
                }
            }
        }

        // Clear any stale auto-refund guard so a future fail can refund again.
        delete_transient( 'wpraffle_autorefund_' . $raffle_id );

        if ( function_exists( 'wpraffle_flush_raffle_cache' ) ) {
            wpraffle_flush_raffle_cache( $raffle_id );
        }
        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( $raffle_id, 'raffle_relisted', array(
                'previous_status' => $raffle->status,
                'new_draw_date'   => $new_draw_date,
            ), 'admin' );
        }

        /**
         * Fires after a raffle is relisted. Listeners: relisted email (Phase 3),
         * cache flush (Phase 4).
         */
        do_action( 'wpraffle_raffle_relisted', $raffle_id, $raffle );

        return true;
    }

    /* ===================================================================
       Auto-relist (cron)
       =================================================================== */

    /**
     * Hourly cron callback. For finished/failed raffles that have an
     * auto-relist config enabled (stored as a JSON feature flag), trigger a
     * relist. The config shape: {auto_relist: 1, relist_days: 7, relist_count:
     * N} — the count is decremented each relist and the feature turns off at 0.
     */
    public static function maybe_auto_relist() {
        global $wpdb;
        $table_raffles = $wpdb->prefix . 'raffles';

        $raffles = $wpdb->get_results(
            "SELECT * FROM {$table_raffles} WHERE status IN ( 'finished', 'failed' )"
        );
        if ( empty( $raffles ) ) {
            return;
        }

        foreach ( $raffles as $raffle ) {
            $config = isset( $raffle->relist_config ) ? json_decode( $raffle->relist_config, true ) : array();
            if ( empty( $config['auto_relist'] ) ) {
                continue;
            }
            // Count-based limit: stop once relist_count exhausted.
            if ( isset( $config['relist_count'] ) && (int) $config['relist_count'] <= 0 ) {
                continue;
            }

            $draw_date = $new_draw_date = '';
            if ( ! empty( $raffle->draw_date ) ) {
                $draw_date = strtotime( $raffle->draw_date );
            }
            // Respect a pause window if configured.
            $pause_days = isset( $config['relist_pause_days'] ) ? (int) $config['relist_pause_days'] : 0;
            $eligible_at = $draw_date ? $draw_date + ( $pause_days * DAY_IN_SECONDS ) : 0;

            if ( $eligible_at && time() < $eligible_at ) {
                continue; // Still inside the pause window.
            }

            $result = self::relist_raffle( $raffle->id );
            if ( is_wp_error( $result ) ) {
                continue;
            }

            // Decrement the remaining relist count.
            if ( isset( $config['relist_count'] ) ) {
                $config['relist_count'] = max( 0, (int) $config['relist_count'] - 1 );
                $wpdb->update(
                    $table_raffles,
                    array( 'relist_config' => wp_json_encode( $config ) ),
                    array( 'id' => $raffle->id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
        }
    }

    /* ===================================================================
       Status helpers (used by reads that must respect new statuses)
       =================================================================== */

    /**
     * The statuses that mean a raffle is no longer enterable (closed for new
     * entries). Reads that filter for "live" raffles should exclude these.
     *
     * @return string[]
     */
    public static function closed_statuses() {
        return array( 'finished', 'failed' );
    }

    /**
     * Whether a raffle is visible in the "ended" listings (both successful
     * draws and failures are shown as ended, distinct from a draft).
     *
     * @return string[]
     */
    public static function ended_statuses() {
        return array( 'finished', 'failed' );
    }
}
