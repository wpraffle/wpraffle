<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Instant_Wins {

    public function __construct() {
        add_action( 'wp_ajax_raffle_add_instant_win', array( $this, 'ajax_add_instant_win' ) );
        add_action( 'wp_ajax_raffle_delete_instant_win', array( $this, 'ajax_delete_instant_win' ) );
        add_action( 'wp_ajax_raffle_save_instant_win_group', array( $this, 'ajax_save_group' ) );
        add_action( 'wp_ajax_raffle_delete_instant_win_group', array( $this, 'ajax_delete_group' ) );
    }

    public function ajax_add_instant_win() {
        check_ajax_referer( 'raffle_draw_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $raffle_id   = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $prize_name  = isset( $_POST['prize_name'] ) ? sanitize_text_field( wp_unslash( $_POST['prize_name'] ) ) : '';
        $ticket_num  = isset( $_POST['ticket_number'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_number'] ) ) : '';
        $quantity    = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
        if ( $quantity < 1 ) $quantity = 1;

        // New in 1.3.0: prize type + config + group + image.
        $prize_type   = isset( $_POST['prize_type'] ) ? sanitize_text_field( wp_unslash( $_POST['prize_type'] ) ) : 'physical';
        $prize_group  = isset( $_POST['prize_group_id'] ) ? absint( $_POST['prize_group_id'] ) : 0;
        $image_id     = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
        $prize_config = self::sanitize_prize_config( $prize_type, $_POST );

        // Validate the recognised type.
        $valid_types = array_keys( Raffle_Instant_Win_Prize_Types::get_types() );
        if ( ! in_array( $prize_type, $valid_types, true ) ) {
            $prize_type = 'physical';
        }

        if ( ! $raffle_id || empty( $prize_name ) ) {
            wp_send_json_error( array( 'message' => 'Prize name is required.' ) );
        }

        global $wpdb;
        $table_instant = $wpdb->prefix . 'raffle_instant_wins';
        $table_raffles = $wpdb->prefix . 'raffles';

        // Wrap lookup + insert in a transaction with a FOR UPDATE lock on the
        // raffle row so two concurrent admin requests (or a double-click)
        // can't both pass the "already an instant win?" check and insert
        // duplicate rows for the same ticket number.
        $wpdb->query( 'START TRANSACTION' );
        $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_raffles} WHERE id = %d FOR UPDATE", $raffle_id ) );
        if ( ! $raffle ) {
            $wpdb->query( 'ROLLBACK' );
            wp_send_json_error( array( 'message' => 'Raffle not found.' ) );
        }

        if ( empty( $ticket_num ) ) {
            // Generate random unused ticket number
            $taken = $wpdb->get_col( $wpdb->prepare(
                "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE raffle_id = %d UNION SELECT ticket_number FROM {$table_instant} WHERE raffle_id = %d",
                $raffle_id, $raffle_id
            ) );
            $taken_set = array_flip( array_map( 'intval', $taken ) );

            $available = array();
            for ( $i = 1; $i <= $raffle->total_tickets; $i++ ) {
                if ( ! isset( $taken_set[ $i ] ) ) {
                    $available[] = $i;
                }
            }

            if ( empty( $available ) ) {
                $wpdb->query( 'ROLLBACK' );
                wp_send_json_error( array( 'message' => 'No available tickets left for instant wins.' ) );
            }

            $ticket_num = $available[ random_int( 0, count( $available ) - 1 ) ];
        } else {
            $ticket_num = absint( $ticket_num );
            if ( $ticket_num < 1 || $ticket_num > $raffle->total_tickets ) {
                $wpdb->query( 'ROLLBACK' );
                wp_send_json_error( array( 'message' => 'Invalid ticket number.' ) );
            }

            // Check if it's already an instant win or sold
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_instant} WHERE raffle_id = %d AND ticket_number = %d",
                $raffle_id, $ticket_num
            ) );
            if ( $exists ) {
                $wpdb->query( 'ROLLBACK' );
                wp_send_json_error( array( 'message' => 'Ticket is already an instant win.' ) );
            }
        }

        // Support quantity > 1: create multiple rows with different ticket numbers
        $added = 0;
        for ( $q = 0; $q < $quantity; $q++ ) {
            $current_ticket = $ticket_num;

            if ( $quantity > 1 || empty( $current_ticket ) ) {
                // For quantity > 1, always auto-assign ticket numbers
                $taken = $wpdb->get_col( $wpdb->prepare(
                    "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE raffle_id = %d UNION SELECT ticket_number FROM {$table_instant} WHERE raffle_id = %d",
                    $raffle_id, $raffle_id
                ) );
                $taken_set = array_flip( array_map( 'intval', $taken ) );

                $available = array();
                for ( $i = 1; $i <= $raffle->total_tickets; $i++ ) {
                    if ( ! isset( $taken_set[ $i ] ) ) {
                        $available[] = $i;
                    }
                }

                if ( empty( $available ) ) {
                    if ( $added > 0 ) {
                        // Some were added already — commit what we have.
                        $wpdb->query( 'COMMIT' );
                        wp_send_json_success( array( 'message' => $added . ' instant win(s) added. No more available tickets.' ) );
                    }
                    $wpdb->query( 'ROLLBACK' );
                    wp_send_json_error( array( 'message' => 'No available tickets left for instant wins.' ) );
                }

                $current_ticket = $available[ random_int( 0, count( $available ) - 1 ) ];
            }

            $insert_data = array(
                'raffle_id'     => $raffle_id,
                'ticket_number' => $current_ticket,
                'prize_name'    => $prize_name,
                'status'        => 'available',
            );
            $insert_fmt = array( '%d', '%d', '%s', '%s' );

            // New 1.3.0 columns.
            $insert_data['prize_type']    = $prize_type;
            $insert_fmt[]                 = '%s';
            if ( ! empty( $prize_config ) ) {
                $insert_data['prize_config'] = wp_json_encode( $prize_config );
                $insert_fmt[]                = '%s';
            }
            if ( $prize_group ) {
                $insert_data['prize_group_id'] = $prize_group;
                $insert_fmt[]                  = '%d';
            }
            if ( $image_id ) {
                $insert_data['image_id'] = $image_id;
                $insert_fmt[]            = '%d';
            }

            $result = $wpdb->insert( $table_instant, $insert_data, $insert_fmt );

            if ( false !== $result ) {
                $added++;
            }
        }

        if ( $added === 0 ) {
            $wpdb->query( 'ROLLBACK' );
            wp_send_json_error( array( 'message' => 'Database error.' ) );
        }

        $wpdb->query( 'COMMIT' );
        wp_send_json_success( array( 'message' => $added . ' instant win(s) added.' ) );
    }

    public function ajax_delete_instant_win() {
        check_ajax_referer( 'raffle_draw_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        global $wpdb;
        $table_instant = $wpdb->prefix . 'raffle_instant_wins';

        $wpdb->delete( $table_instant, array( 'id' => $id ), array( '%d' ) );

        wp_send_json_success( array( 'message' => 'Instant win deleted.' ) );
    }

    public static function get_instant_wins( $raffle_id ) {
        global $wpdb;
        $table_instant = $wpdb->prefix . 'raffle_instant_wins';
        $table_purchases = $wpdb->prefix . 'raffle_purchases';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT iw.*, p.buyer_name as winner_name
             FROM {$table_instant} iw
             LEFT JOIN {$table_purchases} p ON iw.purchase_id = p.id
             WHERE iw.raffle_id = %d
             ORDER BY iw.created_at DESC",
            $raffle_id
        ) );
    }

    public static function get_initials( $name ) {
        if ( empty( $name ) ) {
            return '';
        }
        $parts = array_filter( explode( ' ', trim( $name ) ) );
        $initials = array_map( function( $part ) {
            return strtoupper( mb_substr( $part, 0, 1 ) );
        }, $parts );
        return implode( '.', $initials );
    }

    public static function check_for_instant_wins( $raffle_id, $purchase_id, $ticket_numbers, $buyer_email ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_instant_wins';
        $won_prizes = array();

        if ( empty( $ticket_numbers ) ) {
            return $won_prizes;
        }

        $placeholders = implode( ',', array_fill( 0, count( $ticket_numbers ), '%d' ) );
        $args = array_merge( array( $raffle_id ), $ticket_numbers );

        $wins = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE raffle_id = %d AND status = 'available' AND ticket_number IN ({$placeholders}) FOR UPDATE",
            $args
        ) );

        foreach ( $wins as $win ) {
            $wpdb->update(
                $table,
                array(
                    'status'       => 'won',
                    'winner_email' => $buyer_email,
                    'purchase_id'  => $purchase_id,
                    'won_at'       => current_time( 'mysql' ),
                ),
                array( 'id' => $win->id ),
                array( '%s', '%s', '%d', '%s' ),
                array( '%d' )
            );
            $won_prizes[] = $win;
        }

        return $won_prizes;
    }

    /* ===================================================================
       Prize assignment / reversal (new in 1.3.0)
       =================================================================== */

    /**
     * Assign the actual prize artefacts (coupon/gift product/credit) for a set
     * of won instant-win rows. Called by the WC on_payment_complete flow after
     * check_for_instant_wins has marked the rows 'won'. Each row's prize_config
     * is mutated with an `_assigned` marker so the assignment is idempotent.
     *
     * @param array   $wins  Won instant-win rows.
     * @param object  $order WC_Order (null on the non-WC direct-purchase path).
     * @param array   $buyer {user_id, name, email}.
     * @return array  Per-win results ['id' => true|WP_Error].
     */
    public static function assign_winning_prizes( $wins, $order, $buyer ) {
        if ( ! class_exists( 'Raffle_Instant_Win_Prize_Types' ) ) {
            return array();
        }
        $results = array();
        foreach ( $wins as $win ) {
            $result = Raffle_Instant_Win_Prize_Types::assign( $win, $order, $buyer );
            $results[ $win->id ] = $result;
            // 1.3.0 — never swallow an assignment failure silently. If a prize
            // couldn't be assigned (wallet down, invalid config, missing user,
            // fatal caught below), record it in the audit log with the full
            // error message so the operator can see why a payout was missed
            // and the "Sync Wallet Payouts" button can recover it.
            if ( is_wp_error( $result ) && class_exists( 'Raffle_Audit' ) ) {
                Raffle_Audit::log( (int) $win->raffle_id, 'instant_win_assign_failed', array(
                    'instant_win_id' => (int) $win->id,
                    'ticket_number'  => (int) $win->ticket_number,
                    'prize_type'     => isset( $win->prize_type ) ? $win->prize_type : 'physical',
                    'prize_name'     => $win->prize_name,
                    'buyer_email'    => isset( $buyer['email'] ) ? $buyer['email'] : '',
                    'user_id'        => isset( $buyer['user_id'] ) ? $buyer['user_id'] : 0,
                    'error_code'     => $result->get_error_code(),
                    'error_message'  => $result->get_error_message(),
                ), 'system' );
            }
        }
        return $results;
    }

    /**
     * Reverse every won instant-win prize attached to an order. Called by the
     * WC cancel/refund/failed revert path. Re-instantiates the prize rows as
     * 'available' so they can be won again (e.g. after a relist).
     *
     * @param int $order_id WC order id.
     */
    public static function reverse_for_order( $order_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_instant_wins';

        $wins = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'won' AND purchase_id IN (
                SELECT id FROM {$wpdb->prefix}raffle_purchases WHERE wc_order_id = %d
            )",
            $order_id
        ) );

        foreach ( $wins as $win ) {
            if ( class_exists( 'Raffle_Instant_Win_Prize_Types' ) ) {
                Raffle_Instant_Win_Prize_Types::reverse( $win->id, $order_id );
            }
            // Re-instantiate the slot so it's winnable again.
            $wpdb->update(
                $table,
                array(
                    'status'       => 'available',
                    'winner_email' => null,
                    'purchase_id'  => null,
                    'won_at'       => null,
                ),
                array( 'id' => $win->id ),
                array( '%s', null, null, null ),
                array( '%d' )
            );
        }
    }

    /**
     * Reset all instant-win rows for a raffle back to 'available'. Used by the
     * lifecycle relist flow (Phase 2) so a re-run raffle can re-award its
     * instant wins.
     */
    public static function reset_for_raffle( $raffle_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_instant_wins';

        // Reverse any still-assigned prizes first (safety).
        $assigned = $wpdb->get_results( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE raffle_id = %d AND status = 'won'",
            $raffle_id
        ) );
        foreach ( $assigned as $row ) {
            if ( class_exists( 'Raffle_Instant_Win_Prize_Types' ) ) {
                Raffle_Instant_Win_Prize_Types::reverse( $row->id, 0 );
            }
        }

        $wpdb->update(
            $table,
            array(
                'status'       => 'available',
                'winner_email' => null,
                'purchase_id'  => null,
                'won_at'       => null,
            ),
            array( 'raffle_id' => $raffle_id ),
            array( '%s', null, null, null ),
            array( '%d' )
        );
    }

    /* ===================================================================
       Prize groups CRUD (new in 1.3.0)
       =================================================================== */

    public static function get_groups( $raffle_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_instant_win_groups WHERE raffle_id = %d ORDER BY created_at ASC",
            $raffle_id
        ) );
    }

    public static function save_group( $raffle_id, $name, $image_id = 0, $display_config = array() ) {
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'raffle_instant_win_groups',
            array(
                'raffle_id'      => $raffle_id,
                'name'           => sanitize_text_field( $name ),
                'image_id'       => $image_id ? absint( $image_id ) : null,
                'display_config' => wp_json_encode( $display_config ),
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%d', '%s', '%s' )
        );
        return $result ? $wpdb->insert_id : false;
    }

    public static function delete_group( $group_id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'raffle_instant_win_groups', array( 'id' => $group_id ), array( '%d' ) );
    }

    public function ajax_save_group() {
        check_ajax_referer( 'raffle_draw_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }
        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $image_id  = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
        if ( ! $raffle_id || empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'Raffle and name required.' ) );
        }
        $id = self::save_group( $raffle_id, $name, $image_id );
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Could not save group.' ) );
        }
        wp_send_json_success( array( 'id' => $id, 'message' => 'Group saved.' ) );
    }

    public function ajax_delete_group() {
        check_ajax_referer( 'raffle_draw_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }
        self::delete_group( $id );
        wp_send_json_success( array( 'message' => 'Group deleted.' ) );
    }

    /* ===================================================================
       Config sanitisation (new in 1.3.0)
       =================================================================== */

    /**
     * Sanitise the prize_config fields from the admin POST based on type.
     */
    private static function sanitize_prize_config( $prize_type, $post ) {
        $post = is_array( $post ) ? $post : array();
        $config = array();

        switch ( $prize_type ) {
            case 'coupon':
                if ( isset( $post['coupon_discount_type'] ) ) {
                    $config['discount_type'] = sanitize_text_field( wp_unslash( $post['coupon_discount_type'] ) );
                }
                if ( isset( $post['coupon_amount'] ) ) {
                    $config['amount'] = (float) $post['coupon_amount'];
                }
                $config['free_shipping']   = ! empty( $post['coupon_free_shipping'] );
                $config['individual_use']  = ! empty( $post['coupon_individual_use'] );
                if ( isset( $post['coupon_min_spend'] ) ) {
                    $config['min_spend'] = (float) $post['coupon_min_spend'];
                }
                if ( isset( $post['coupon_max_spend'] ) ) {
                    $config['max_spend'] = (float) $post['coupon_max_spend'];
                }
                if ( isset( $post['coupon_expiry_days'] ) ) {
                    $config['expiry_days'] = absint( $post['coupon_expiry_days'] );
                }
                $config['include_products'] = isset( $post['coupon_include_products'] ) ? array_filter( array_map( 'absint', (array) $post['coupon_include_products'] ) ) : array();
                $config['exclude_products'] = isset( $post['coupon_exclude_products'] ) ? array_filter( array_map( 'absint', (array) $post['coupon_exclude_products'] ) ) : array();
                $config['include_categories'] = isset( $post['coupon_include_categories'] ) ? array_filter( array_map( 'absint', (array) $post['coupon_include_categories'] ) ) : array();
                $config['exclude_categories'] = isset( $post['coupon_exclude_categories'] ) ? array_filter( array_map( 'absint', (array) $post['coupon_exclude_categories'] ) ) : array();
                break;
            case 'product':
                if ( isset( $post['gift_product_id'] ) ) {
                    $config['product_id'] = absint( $post['gift_product_id'] );
                }
                if ( isset( $post['gift_quantity'] ) ) {
                    $config['quantity'] = max( 1, absint( $post['gift_quantity'] ) );
                }
                break;
            case 'credit':
                if ( isset( $post['credit_amount'] ) ) {
                    $config['amount'] = (float) $post['credit_amount'];
                }
                break;
        }

        /**
         * Allow extensions to sanitise their own prize-config fields.
         */
        return apply_filters( 'wpraffle_instant_win_sanitize_config', $config, $prize_type, $post );
    }
}
