<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Tickets {

    /**
     * Formats ticket numbers with leading zeros according to 3 or 4 digit rules.
     * Accepts an optional raffle object to apply per-raffle prefix/suffix (1.3.0).
     */
    public static function format_ticket_number($number, $total_tickets, $raffle = null) {
        $digits = ($total_tickets > 999) ? 4 : 3;
        $formatted = str_pad($number, $digits, '0', STR_PAD_LEFT);
        // 1.3.0 — apply per-raffle prefix/suffix when provided.
        if ( $raffle && is_object( $raffle ) ) {
            $prefix = isset( $raffle->ticket_prefix ) ? $raffle->ticket_prefix : '';
            $suffix = isset( $raffle->ticket_suffix ) ? $raffle->ticket_suffix : '';
            if ( $prefix !== '' || $suffix !== '' ) {
                $formatted = $prefix . $formatted . $suffix;
            }
        }
        return $formatted;
    }

    /**
     * Generate unique random ticket numbers for a purchase.
     *
     * Uses random_int() for cryptographically secure randomness.
     * UNIQUE constraint in DB prevents duplicates even under concurrency.
     */
    public static function generate_tickets( $raffle_id, $purchase_id, $quantity, $buyer_email, $manage_transaction = true, $requested_numbers = '' ) {
        global $wpdb;

        $table_tickets = $wpdb->prefix . 'raffle_tickets';
        $table_raffles = $wpdb->prefix . 'raffles';

        // If $manage_transaction is false, the caller has already opened the transaction
        if ( $manage_transaction ) {
            $wpdb->query( 'START TRANSACTION' );
        }

        // SELECT ... FOR UPDATE locks the raffle row
        // No other concurrent purchase can read sold_tickets until this transaction ends
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT total_tickets, sold_tickets, ticket_numbering, ticket_start_number FROM {$table_raffles} WHERE id = %d FOR UPDATE",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'invalid_raffle', 'Raffle not found.' );
        }

        // 1.3.0 — Numbering strategy. Default 'random' preserves the legacy
        // behaviour. 'sequential' assigns the next contiguous number from a
        // per-raffle cursor; 'shuffled' assigns from a pre-shuffled deck.
        $numbering = isset( $raffle->ticket_numbering ) ? $raffle->ticket_numbering : 'random';
        if ( ! in_array( $numbering, array( 'random', 'sequential', 'shuffled' ), true ) ) {
            $numbering = 'random';
        }

        // Check availability (protegido por el lock)
        $available = $raffle->total_tickets - $raffle->sold_tickets;
        if ( $quantity > $available ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'not_enough_tickets', 'Not enough tickets available.' );
        }

        // Get already assigned numbers (lock garantiza consistencia)
        $taken = $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM {$table_tickets} WHERE raffle_id = %d",
            $raffle_id
        ) );
        $taken_set = array_flip( array_map( 'intval', $taken ) );
        $actual_available = $raffle->total_tickets - count( $taken_set );

        if ( $actual_available < $quantity ) {
            if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
            return new WP_Error( 'not_enough_tickets', 'Not enough tickets available.' );
        }

        // Select random unused numbers without creating a full range array.
        // Uses random_int() over [1, total_tickets] and checks against $taken_set (O(1) lookup).
        // For small packages over large raffles, this is much more efficient than range()+array_diff().
        $selected  = array();
        $total     = $raffle->total_tickets;

        // Process manually requested numbers
        $selected_set = array();
        if ( ! empty( $requested_numbers ) ) {
            $req_array = array_filter( array_map( 'intval', explode( ',', $requested_numbers ) ) );
            foreach ( $req_array as $req_num ) {
                if ( $req_num < 1 || $req_num > $total ) {
                    if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
                    return new WP_Error( 'invalid_number', sprintf( 'Selected number %d is out of range.', $req_num ) );
                }
                if ( isset( $taken_set[ $req_num ] ) ) {
                    if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
                    return new WP_Error( 'number_taken', sprintf( 'Ticket number %d is already sold.', $req_num ) );
                }
                if ( isset( $selected_set[ $req_num ] ) ) {
                    if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
                    return new WP_Error( 'duplicate_number', sprintf( 'Duplicate ticket number %d selected.', $req_num ) );
                }
                $selected[] = $req_num;
                $selected_set[ $req_num ] = true;
            }

            if ( count( $selected ) !== $quantity ) {
                if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
                return new WP_Error( 'quantity_mismatch', 'Number of selected tickets does not match the quantity.' );
            }
        }

        // Fill remaining with randoms (if any conflicts happened or random selection is used)
        $remaining_qty = $quantity - count( $selected );

        // 1.3.0 — Sequential / shuffled numbering paths.
        if ( $remaining_qty > 0 && 'random' !== $numbering ) {
            $seq_table = $wpdb->prefix . 'raffle_ticket_sequences';
            // Ensure the sequence cursor row exists.
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$seq_table} (raffle_id, next_seq) VALUES (%d, %d) ON DUPLICATE KEY UPDATE raffle_id = raffle_id",
                $raffle_id,
                isset( $raffle->ticket_start_number ) ? max( 1, (int) $raffle->ticket_start_number ) : 1
            ) );

            // Lock the sequence row for this raffle so concurrent purchases
            // don't grab the same number.
            $seq = $wpdb->get_row( $wpdb->prepare( "SELECT next_seq FROM {$seq_table} WHERE raffle_id = %d FOR UPDATE", $raffle_id ) );
            $next = $seq ? (int) $seq->next_seq : 1;

            if ( 'shuffled' === $numbering ) {
                // Build a shuffled deck of the full range, deterministically
                // offset from the cursor. We shuffle once per fill and take
                // the next contiguous block. (A full persisted shuffle would
                // require storing the deck; the cursor-based approach keeps
                // the schema minimal and is sufficient for typical use.)
                $pool = array();
                for ( $n = 1; $n <= $total; $n++ ) {
                    if ( ! isset( $taken_set[ $n ] ) && ! isset( $selected_set[ $n ] ) ) {
                        $pool[] = $n;
                    }
                }
                // Fisher-Yates with secure randomness.
                for ( $i = count( $pool ) - 1; $i > 0; $i-- ) {
                    $j = random_int( 0, $i );
                    list( $pool[ $i ], $pool[ $j ] ) = array( $pool[ $j ], $pool[ $i ] );
                }
                $take = min( $remaining_qty, count( $pool ) );
                for ( $i = 0; $i < $take; $i++ ) {
                    $num = $pool[ $i ];
                    $selected[] = $num;
                    $selected_set[ $num ] = true;
                }
            } else { // sequential
                // Assign contiguous numbers starting at the cursor, skipping any
                // already-taken (e.g. reserved) numbers.
                while ( count( $selected ) < $quantity && $next <= $total ) {
                    if ( ! isset( $taken_set[ $next ] ) && ! isset( $selected_set[ $next ] ) ) {
                        $selected[] = $next;
                        $selected_set[ $next ] = true;
                    }
                    $next++;
                }
            }

            // Persist the advanced cursor.
            $wpdb->update( $seq_table, array( 'next_seq' => $next ), array( 'raffle_id' => $raffle_id ), array( '%d' ), array( '%d' ) );

            // Re-sort and fall through; if sequential/shuffled couldn't satisfy
            // the full quantity (e.g. collisions), the random path tops it up.
            $remaining_qty = $quantity - count( $selected );
        }

        if ( $remaining_qty > 0 ) {
            if ( $actual_available <= $remaining_qty * 3 ) {
                // Pool almost exhausted: building array of available (small) is more efficient
                $pool = array();
                for ( $n = 1; $n <= $total; $n++ ) {
                    if ( ! isset( $taken_set[ $n ] ) && ! isset( $selected_set[ $n ] ) ) {
                        $pool[] = $n;
                    }
                }
                for ( $i = 0; $i < $remaining_qty; $i++ ) {
                    $index      = random_int( 0, count( $pool ) - 1 );
                    $selected[] = $pool[ $index ];
                    $selected_set[ $pool[ $index ] ] = true;
                    array_splice( $pool, $index, 1 );
                }
            } else {
                // Wide pool: generate randoms and check against set (O(1) per lookup)
                while ( count( $selected ) < $quantity ) {
                    $num = random_int( 1, $total );
                    if ( ! isset( $taken_set[ $num ] ) && ! isset( $selected_set[ $num ] ) ) {
                        $selected[]          = $num;
                        $selected_set[ $num ] = true;
                    }
                }
            }
        }

        sort( $selected );

        // Insert tickets — verify each insertion
        foreach ( $selected as $number ) {
            $result = $wpdb->insert( $table_tickets, array(
                'raffle_id'     => $raffle_id,
                'purchase_id'   => $purchase_id,
                'ticket_number' => $number,
                'buyer_email'   => $buyer_email,
            ), array( '%d', '%d', '%d', '%s' ) );

            if ( false === $result ) {
                if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
                return new WP_Error( 'insert_failed', 'Error assigning tickets. Please try again.' );
            }
        }

        // Update sold count + auto-close if sold out
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table_raffles} SET sold_tickets = sold_tickets + %d, status = CASE WHEN sold_tickets + %d >= total_tickets THEN 'finished' ELSE status END WHERE id = %d",
            $quantity,
            $quantity,
            $raffle_id
        ) );

        // Invalidate the cached raffle row — sold_tickets/status changed.
        if ( function_exists( 'wpraffle_flush_raffle_cache' ) ) {
            wpraffle_flush_raffle_cache( $raffle_id );
        }

        // COMMIT — releases the lock and confirms everything
        if ( $manage_transaction ) {
            $wpdb->query( 'COMMIT' );
        }

        // Recalculate charity raised total if this raffle has a charity.
        // Uses the shared resolver so the write works whether raffles.charity_id
        // holds a DB row id or a CPT post id. If we can't resolve to a DB row
        // (e.g. tables missing), no-op gracefully — the shortcode renders from
        // the live compute and the hourly cron catches up.
        $raffle_charity_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT charity_id FROM {$table_raffles} WHERE id = %d",
            $raffle_id
        ) );
        if ( $raffle_charity_id && class_exists( 'Raffle_Charity' ) ) {
            $live = Raffle_Charity::calculate_total_raised_for_charity( $raffle_charity_id );

            // Resolve to a DB row id to persist total_raised.
            $charities_table = $wpdb->prefix . 'raffle_charities';
            $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $charities_table ) ) === $charities_table;
            if ( $table_exists ) {
                // First try direct lookup, then resolve via CPT post slug.
                $db_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$charities_table} WHERE id = %d",
                    absint( $raffle_charity_id )
                ) );
                if ( ! $db_id ) {
                    $post = get_post( absint( $raffle_charity_id ) );
                    if ( $post && $post->post_type === 'raffle_charity' ) {
                        $slug = $post->post_name ?: sanitize_title( $post->post_title );
                        $db_id = (int) $wpdb->get_var( $wpdb->prepare(
                            "SELECT id FROM {$charities_table} WHERE slug = %s",
                            $slug
                        ) );
                    }
                }
                if ( $db_id ) {
                    $wpdb->update(
                        $charities_table,
                        array( 'total_raised' => $live ),
                        array( 'id' => $db_id ),
                        array( '%f' ),
                        array( '%d' )
                    );
                }
            }
        }

        return $selected;
    }

    /**
     * Validate selected numbers server-side.
     * Returns true if valid, or a WP_Error if invalid.
     */
    public static function validate_selected_numbers( $raffle_id, $selected_numbers_str, $quantity ) {
        global $wpdb;

        if ( empty( $selected_numbers_str ) ) {
            return true; // Auto-generated numbers is fine
        }

        $numbers = array_filter( array_map( 'intval', explode( ',', $selected_numbers_str ) ) );
        if ( count( $numbers ) !== $quantity ) {
            return new WP_Error( 'invalid_quantity', 'The number of selected tickets does not match the quantity requested.' );
        }

        // Get raffle details
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT total_tickets FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            return new WP_Error( 'invalid_raffle', 'Raffle not found.' );
        }

        $total_tickets = (int) $raffle->total_tickets;

        // Check range
        foreach ( $numbers as $num ) {
            if ( $num < 1 || $num > $total_tickets ) {
                return new WP_Error( 'out_of_range', sprintf( 'Selected number %d is out of range (1-%d).', $num, $total_tickets ) );
            }
        }

        // Check unique among selected
        if ( count( array_unique( $numbers ) ) !== count( $numbers ) ) {
            return new WP_Error( 'duplicate_selection', 'Duplicate ticket numbers selected.' );
        }

        // Check if already sold. Build the IN-list from %d placeholders rather
        // than concatenating intval'd literals (defence in depth).
        $placeholders = implode( ',', array_fill( 0, count( $numbers ), '%d' ) );
        $params       = array_merge( array( "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE raffle_id = %d AND ticket_number IN ({$placeholders})" ), array( $raffle_id ), array_map( 'intval', $numbers ) );
        $taken        = $wpdb->get_col(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared via call_user_func_array with %d placeholders above; static analyser cannot trace the spread.
            call_user_func_array( array( $wpdb, 'prepare' ), $params )
        );

        if ( ! empty( $taken ) ) {
            return new WP_Error( 'already_sold', sprintf( 'The following ticket numbers are already sold: %s.', implode( ', ', $taken ) ) );
        }

        // Check active reservations (excluding current session if applicable)
        if ( class_exists( 'Raffle_Reservations' ) ) {
            $reserved = Raffle_Reservations::get_reserved_numbers( $raffle_id );
            $reserved_taken = array_intersect( $numbers, $reserved );
            if ( ! empty( $reserved_taken ) ) {
                return new WP_Error( 'reserved', sprintf( 'The following ticket numbers are currently reserved: %s.', implode( ', ', $reserved_taken ) ) );
            }
        }

        return true;
    }
}
