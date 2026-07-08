<?php
/**
 * WPRaffle — Instant-Win Prize-Type Handlers
 *
 * Encapsulates the assign/reverse logic for each instant-win prize type
 * (coupon, product, credit, physical, custom). The dispatcher
 * (Raffle_Instant_Win_Prize_Types::assign / ::reverse) switches on the
 * prize_type column and fires an extensible filter so compatibility
 * classes (Phase 4) can register extra types (Smart Coupons, Wallet, etc.)
 * without touching this file.
 *
 * DESIGN NOTES
 *  - Assignment is idempotent: a prize row carries the generated artefact
 *    reference (coupon id, order item id, ledger row id) in its prize_config
 *    under the `_assigned` key, so re-running assign is a no-op and reverse
 *    knows exactly what to undo.
 *  - Reversal is best-effort and safe to call on a prize that was never
 *    assigned (e.g. a legacy physical prize): it short-circuits cleanly.
 *  - WooCommerce is a hard dependency for coupon/product types; this class
 *    guards on `function_exists('wc_get_coupon')` and degrades gracefully.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Instant_Win_Prize_Types {

    const TYPE_PHYSICAL = 'physical';
    const TYPE_COUPON   = 'coupon';
    const TYPE_PRODUCT  = 'product';
    const TYPE_CREDIT   = 'credit';
    const TYPE_CUSTOM   = 'custom';

    /**
     * The list of recognised prize types. Used by the admin UI to render the
     * selector. Filterable so compatibility classes can add types.
     *
     * @return array<string,string> type => label.
     */
    public static function get_types() {
        $types = array(
            self::TYPE_PHYSICAL => __( 'Physical / manual prize', 'wpraffle' ),
            self::TYPE_COUPON   => __( 'Auto-generated coupon', 'wpraffle' ),
            self::TYPE_PRODUCT  => __( 'Gift product (added to order)', 'wpraffle' ),
            self::TYPE_CREDIT   => __( 'Site credit', 'wpraffle' ),
            self::TYPE_CUSTOM   => __( 'Custom (extension)', 'wpraffle' ),
        );
        /**
         * Add or modify instant-win prize types. Extensions should add a
         * type => label pair here and handle assignment via the
         * wpraffle_instant_win_assign_{type} / wpraffle_instant_win_reverse_{type}
         * filters.
         */
        return apply_filters( 'wpraffle_instant_win_prize_types', $types );
    }

    /**
     * Assign a won prize to its winner.
     *
     * Called from Raffle_Instant_Wins::assign_winning_prize() after a ticket
     * purchase matches an instant-win slot. Mutates the prize row's
     * prize_config with an `_assigned` artefact reference so the assignment is
     * idempotent and reversible.
     *
     * @param object $win   Instant-win row (from raffle_instant_wins).
     * @param object $order WC_Order the winning purchase belongs to (may be a
     *                      stub for the non-WC direct-purchase path).
     * @param array  $buyer {user_id, name, email}.
     * @return true|WP_Error True on success (or no-op for physical), error on failure.
     */
    public static function assign( $win, $order, $buyer ) {
        if ( empty( $win->prize_type ) ) {
            $win->prize_type = self::TYPE_PHYSICAL;
        }

        $config = self::decode_config( $win->prize_config );

        // Idempotency: if already assigned, do nothing.
        if ( ! empty( $config['_assigned'] ) ) {
            return true;
        }

        switch ( $win->prize_type ) {
            case self::TYPE_COUPON:
                $result = self::assign_coupon( $win, $order, $buyer, $config );
                break;
            case self::TYPE_PRODUCT:
                $result = self::assign_product( $win, $order, $buyer, $config );
                break;
            case self::TYPE_CREDIT:
                $result = self::assign_credit( $win, $order, $buyer, $config );
                break;
            case self::TYPE_PHYSICAL:
                // No automated artefact; the operator fulfils manually.
                $result = true;
                break;
            default:
                /**
                 * Custom prize-type assignment. Filters receive ($win, $order,
                 * $buyer, $config) and should return true|WP_Error.
                 */
                $result = apply_filters( 'wpraffle_instant_win_assign_' . $win->prize_type, true, $win, $order, $buyer, $config );
                break;
        }

        if ( true === $result && self::TYPE_PHYSICAL !== $win->prize_type ) {
            // Mark assigned so a repeat (e.g. status transition re-fire) is a no-op.
            $config['_assigned'] = current_time( 'mysql', true );
            self::persist_config( $win->id, $config );
        }

        return $result;
    }

    /**
     * Reverse a won prize (called when the owning order is cancelled/refunded).
     *
     * Safe to call on prizes that were never assigned or are physical.
     *
     * @param int    $win_id   Instant-win row id.
     * @param int    $order_id WC order id (0 for non-WC).
     * @return true|WP_Error
     */
    public static function reverse( $win_id, $order_id = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_instant_wins';

        $win = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $win_id ) );
        if ( ! $win ) {
            return true; // Already gone — treat as success.
        }

        $config = self::decode_config( $win->prize_config );

        // Nothing was ever assigned (legacy physical prize or pre-assignment).
        if ( empty( $config['_assigned'] ) ) {
            return true;
        }

        if ( empty( $win->prize_type ) ) {
            $win->prize_type = self::TYPE_PHYSICAL;
        }

        switch ( $win->prize_type ) {
            case self::TYPE_COUPON:
                $result = self::reverse_coupon( $win, $config );
                break;
            case self::TYPE_PRODUCT:
                $result = self::reverse_product( $win, $order_id, $config );
                break;
            case self::TYPE_CREDIT:
                $result = self::reverse_credit( $win, $config );
                break;
            case self::TYPE_PHYSICAL:
                $result = true;
                break;
            default:
                $result = apply_filters( 'wpraffle_instant_win_reverse_' . $win->prize_type, true, $win, $order_id, $config );
                break;
        }

        if ( true === $result ) {
            // Clear the assignment marker.
            unset( $config['_assigned'] );
            self::persist_config( $win->id, $config );
        }

        return $result;
    }

    /* ===================================================================
       Coupon prize type
       =================================================================== */

    /**
     * Auto-generate a single-use shop_coupon for the winner.
     *
     * Config shape: {discount_type, amount, free_shipping, min_spend, max_spend,
     * expiry_days, individual_use, include_products[], exclude_products[],
     * include_categories[], exclude_categories[]}.
     */
    private static function assign_coupon( $win, $order, $buyer, $config ) {
        if ( ! function_exists( 'wc_get_coupon' ) || ! class_exists( 'WC_Coupon' ) ) {
            return new WP_Error( 'wc_required', __( 'WooCommerce is required for coupon prizes.', 'wpraffle' ) );
        }

        $code         = self::generate_coupon_code();
        $discount_type = isset( $config['discount_type'] ) ? sanitize_text_field( $config['discount_type'] ) : 'fixed_cart';
        $amount       = isset( $config['amount'] ) ? (float) $config['amount'] : 0;
        $expiry_days  = isset( $config['expiry_days'] ) ? absint( $config['expiry_days'] ) : 0;

        $coupon = new WC_Coupon();
        $coupon->set_code( $code );
        $coupon->set_discount_type( in_array( $discount_type, array( 'fixed_cart', 'percent', 'fixed_product', 'percent_product' ), true ) ? $discount_type : 'fixed_cart' );
        $coupon->set_amount( $amount );
        $coupon->set_usage_limit( 1 );
        $coupon->set_usage_limit_per_user( 1 );
        $coupon->set_free_shipping( ! empty( $config['free_shipping'] ) );
        $coupon->set_individual_use( ! empty( $config['individual_use'] ) );

        if ( ! empty( $config['min_spend'] ) ) {
            $coupon->set_minimum_amount( (float) $config['min_spend'] );
        }
        if ( ! empty( $config['max_spend'] ) ) {
            $coupon->set_maximum_amount( (float) $config['max_spend'] );
        }
        if ( $expiry_days > 0 ) {
            $coupon->set_date_expires( time() + ( $expiry_days * DAY_IN_SECONDS ) );
        }
        if ( ! empty( $config['include_products'] ) && is_array( $config['include_products'] ) ) {
            $coupon->set_product_ids( array_map( 'absint', $config['include_products'] ) );
        }
        if ( ! empty( $config['exclude_products'] ) && is_array( $config['exclude_products'] ) ) {
            $coupon->set_excluded_product_ids( array_map( 'absint', $config['exclude_products'] ) );
        }
        if ( ! empty( $config['include_categories'] ) && is_array( $config['include_categories'] ) ) {
            $coupon->set_product_categories( array_map( 'absint', $config['include_categories'] ) );
        }
        if ( ! empty( $config['exclude_categories'] ) && is_array( $config['exclude_categories'] ) ) {
            $coupon->set_excluded_product_categories( array_map( 'absint', $config['exclude_categories'] ) );
        }

        // Restrict to the winning customer so the code can't be shared.
        if ( ! empty( $buyer['user_id'] ) ) {
            $user = get_user_by( 'id', $buyer['user_id'] );
            if ( $user && $user->user_email ) {
                $coupon->set_email_restrictions( array( strtolower( $user->user_email ) ) );
            } elseif ( ! empty( $buyer['email'] ) ) {
                $coupon->set_email_restrictions( array( strtolower( $buyer['email'] ) ) );
            }
        } elseif ( ! empty( $buyer['email'] ) ) {
            $coupon->set_email_restrictions( array( strtolower( $buyer['email'] ) ) );
        }

        try {
            $coupon_id = $coupon->save();
        } catch ( Exception $e ) {
            return new WP_Error( 'coupon_save_failed', $e->getMessage() );
        }

        // Email the code to the winner immediately (the standalone instant-win
        // email is also fired by the caller; this stores the code on the row
        // so it appears in the winner's account and in any re-send).
        $config['_assigned']          = current_time( 'mysql', true );
        $config['coupon_id']          = $coupon_id;
        $config['coupon_code']        = $code;
        self::persist_config( $win->id, $config );

        return true;
    }

    private static function reverse_coupon( $win, $config ) {
        if ( empty( $config['coupon_id'] ) ) {
            return true;
        }
        $coupon_id = absint( $config['coupon_id'] );
        $coupon    = new WC_Coupon( $coupon_id );
        if ( $coupon->get_id() ) {
            // If unused, trash it. If already used, leave it (the discount was spent).
            if ( $coupon->get_usage_count() > 0 ) {
                return true;
            }
            $coupon->set_usage_limit( 0 );
            $coupon->save();
            wp_trash_post( $coupon_id );
        }
        return true;
    }

    private static function generate_coupon_code() {
        return strtoupper( substr( md5( wp_generate_password( 16, false ) . uniqid( '', true ) ), 0, 4 )
            . '-' . substr( md5( wp_rand() ), 0, 4 ) . '-' . substr( md5( time() ), 0, 4 ) );
    }

    /* ===================================================================
       Product (gift) prize type
       =================================================================== */

    /**
     * Add the gift product to the winner's order at total 0.
     *
     * Config shape: {product_id, quantity}.
     */
    private static function assign_product( $win, $order, $buyer, $config ) {
        if ( ! $order || ! function_exists( 'wc_get_product' ) ) {
            return new WP_Error( 'wc_required', __( 'WooCommerce is required for gift-product prizes.', 'wpraffle' ) );
        }
        $product_id = isset( $config['product_id'] ) ? absint( $config['product_id'] ) : 0;
        $quantity   = isset( $config['quantity'] ) ? max( 1, absint( $config['quantity'] ) ) : 1;
        if ( ! $product_id ) {
            return new WP_Error( 'no_product', __( 'No gift product configured.', 'wpraffle' ) );
        }
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'product_missing', __( 'Gift product no longer exists.', 'wpraffle' ) );
        }

        $item_id = $order->add_product( $product, $quantity, array(
            'subtotal' => 0,
            'total'    => 0,
        ) );
        if ( ! $item_id ) {
            return new WP_Error( 'add_failed', __( 'Could not add gift product to order.', 'wpraffle' ) );
        }

        // Tag the line item so we can identify and remove it on reversal, and
        // so it renders distinctly on the order/thank-you page.
        wc_add_order_item_meta( $item_id, '_raffle_is_instant_win_gift', 'yes' );
        wc_add_order_item_meta( $item_id, '_raffle_instant_win_id', $win->id );
        $order->calculate_totals();
        $order->save();

        $config['_assigned']      = current_time( 'mysql', true );
        $config['gift_order_item'] = $item_id;
        self::persist_config( $win->id, $config );

        return true;
    }

    private static function reverse_product( $win, $order_id, $config ) {
        if ( empty( $order_id ) || empty( $config['gift_order_item'] ) ) {
            return true;
        }
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return true;
        }
        $item_id = absint( $config['gift_order_item'] );
        foreach ( $order->get_items() as $item ) {
            if ( $item->get_id() === $item_id ) {
                $order->remove_item( $item_id );
                break;
            }
        }
        $order->calculate_totals();
        $order->save();
        return true;
    }

    /* ===================================================================
       Credit prize type
       =================================================================== */

    /**
     * Credit the winner's site-credit balance + live wallet.
     *
     * Config shape: {amount}.
     *
     * Resilience: a payout row is recorded FIRST (via the wallet adapter) so a
     * missed credit is always recoverable via "Sync Wallet Payouts", even if
     * the wallet plugin is down or the buyer is a guest. The internal ledger is
     * written for logged-in winners as the authoritative audit record.
     */
    private static function assign_credit( $win, $order, $buyer, $config ) {
        $user_id = ! empty( $buyer['user_id'] ) ? absint( $buyer['user_id'] ) : 0;
        $amount  = isset( $config['amount'] ) ? (float) $config['amount'] : 0;
        $email   = isset( $buyer['email'] ) ? $buyer['email'] : '';

        if ( $amount <= 0 ) {
            return new WP_Error( 'invalid_credit', __( 'Credit prize has no amount configured.', 'wpraffle' ) );
        }

        // STEP 1 — record a payout row via the wallet adapter. This happens
        // FIRST so that even if everything after fails, the prize is recoverable
        // through the "Sync Wallet Payouts" admin button. For logged-in winners
        // this also performs the live wallet credit immediately when the wallet
        // plugin is active; for guests it records a 'pending' row.
        if ( class_exists( 'Raffle_Wallet_Adapter' ) ) {
            $wallet_result = Raffle_Wallet_Adapter::credit_instant_win( $win->id, $user_id, $email, $amount, $win->prize_name );
            if ( ! is_wp_error( $wallet_result ) ) {
                $config['wallet_payout_id'] = (int) $wallet_result;
            }
            // A WP_Error (guest winner, no wallet plugin) is non-fatal: a
            // 'pending' payout row has still been recorded for manual/re-sync
            // settlement. Fall through to record the internal ledger too.
        } elseif ( class_exists( 'Raffle_Audit' ) ) {
            // No wallet adapter at all — log so it's visible.
            Raffle_Audit::log( (int) $win->raffle_id, 'instant_win_no_wallet_adapter', array(
                'instant_win_id' => $win->id,
                'amount'         => $amount,
            ), 'system' );
        }

        // STEP 2 — internal ledger (audit record) for logged-in winners.
        if ( $user_id && class_exists( 'Raffle_Credits' ) ) {
            $reference = 'instant_win:' . $win->id;
            $existing  = self::ledger_entry_exists( $user_id, $reference );
            if ( $existing ) {
                $config['ledger_id'] = (int) $existing;
            } else {
                /* translators: %s: prize name. */
                $ledger_id = Raffle_Credits::credit( $user_id, $amount, Raffle_Credits::TYPE_INSTANT_WIN, sprintf( __( 'Instant win — %s', 'wpraffle' ), $win->prize_name ), $reference, (int) $win->raffle_id );
                if ( $ledger_id ) {
                    $config['ledger_id'] = (int) $ledger_id;
                }
            }
        }

        $config['_assigned'] = current_time( 'mysql', true );
        self::persist_config( $win->id, $config );

        return true;
    }

    /**
     * Check whether a ledger entry already exists for an idempotency reference.
     */
    private static function ledger_entry_exists( $user_id, $reference ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}raffle_credits WHERE user_id = %d AND reference = %s LIMIT 1",
            $user_id, $reference
        ) );
    }

    private static function reverse_credit( $win, $config ) {
        if ( empty( $config['ledger_id'] ) || ! class_exists( 'Raffle_Credits' ) ) {
            return true;
        }
        global $wpdb;
        $ledger_id = absint( $config['ledger_id'] );
        // Reverse by deleting the original credit row. The ledger is
        // authoritative via SUM, so removing the credit row restores the
        // balance. Guard against deleting an already-removed row.
        $wpdb->delete( $wpdb->prefix . 'raffle_credits', array( 'id' => $ledger_id, 'type' => Raffle_Credits::TYPE_INSTANT_WIN ), array( '%d', '%s' ) );

        // Also reverse the LIVE wallet credit (WooWallet / TerraWallet) so the
        // spendable balance is corrected. Idempotent — no-op if never credited.
        if ( class_exists( 'Raffle_Wallet_Adapter' ) ) {
            Raffle_Wallet_Adapter::debit_instant_win( $win->id );
        }

        return true;
    }

    /* ===================================================================
       Helpers
       =================================================================== */

    private static function decode_config( $raw ) {
        if ( empty( $raw ) ) {
            return array();
        }
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : array();
    }

    private static function persist_config( $win_id, $config ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'raffle_instant_wins',
            array( 'prize_config' => wp_json_encode( $config ) ),
            array( 'id' => (int) $win_id ),
            array( '%s' ),
            array( '%d' )
        );
    }
}
