<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Live_Draw {

    public function __construct() {
        add_action( 'wp_ajax_raffle_get_draw_pool', array( $this, 'ajax_get_draw_pool' ) );
        add_action( 'wp_ajax_raffle_perform_live_draw', array( $this, 'ajax_perform_live_draw' ) );
        add_shortcode( 'raffle_live_draw', array( $this, 'render_live_draw' ) );
    }

    /**
     * Get the ticket pool for a live draw animation.
     */
    public function ajax_get_draw_pool() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        // SEC-2 FIX: Verify nonce to prevent CSRF
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'raffle_draw_nonce' ) ) {
            wp_send_json_error( 'Security error' );
        }

        $raffle_id = absint( $_GET['raffle_id'] ?? 0 );
        if ( ! $raffle_id ) {
            wp_send_json_error( 'Invalid raffle' );
        }

        global $wpdb;
        $tickets = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.id, t.ticket_number, t.buyer_email, p.buyer_name
             FROM {$wpdb->prefix}raffle_tickets t
             JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id
             WHERE t.raffle_id = %d",
            $raffle_id
        ) );

        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            wp_send_json_error( 'Raffle not found' );
        }

        $total_digits = strlen( (string) $raffle->total_tickets );

        $pool = array_map( function( $t ) use ( $total_digits ) {
            // SEC-A7 FIX: Robust email masking — show first char + domain initial only
            $email_parts = explode( '@', $t->buyer_email );
            $local = $email_parts[0] ?? '';
            $domain = $email_parts[1] ?? '';
            $masked_email = substr( $local, 0, 1 ) . str_repeat( '*', max( 3, strlen( $local ) - 1 ) ) . '@' . substr( $domain, 0, 1 ) . '***';
            return array(
                'id'     => $t->id,
                'number' => str_pad( $t->ticket_number, $total_digits, '0', STR_PAD_LEFT ),
                'name'   => $t->buyer_name,
                'email'  => $masked_email,
            );
        }, $tickets );

        wp_send_json_success( array(
            'pool'         => $pool,
            'title'        => $raffle->title,
            'total'        => count( $pool ),
            'multi_winner' => (bool) $raffle->multi_winner,
            'num_winners'  => (int) $raffle->number_of_winners,
        ) );
    }

    /**
     * Perform the actual draw after animation completes.
     */
    public function ajax_perform_live_draw() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_draw_nonce' ) ) {
            wp_send_json_error( 'Security error' );
        }

        $raffle_id = absint( $_POST['raffle_id'] ?? 0 );
        if ( ! $raffle_id ) {
            wp_send_json_error( 'Invalid raffle' );
        }

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            wp_send_json_error( 'Raffle not found' );
        }

        // Use multi-winner draw if enabled
        if ( $raffle->multi_winner && $raffle->number_of_winners > 1 ) {
            $results = Raffle_Prizes::draw_multiple_winners( $raffle_id, (int) $raffle->number_of_winners );
            if ( is_wp_error( $results ) ) {
                wp_send_json_error( $results->get_error_message() );
            }
            wp_send_json_success( array(
                'multi'   => true,
                'winners' => $results,
            ) );
        } else {
            // SEC-3 FIX: Single winner — use transaction with FOR UPDATE lock like Raffle_Draw::handle_draw()
            $wpdb->query( 'START TRANSACTION' );

            // Lock the raffle row to prevent concurrent draws
            $raffle_locked = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d FOR UPDATE",
                $raffle_id
            ) );

            // Check if already drawn
            if ( $raffle_locked->winner_ticket_id ) {
                $wpdb->query( 'COMMIT' );
                wp_send_json_error( 'This raffle already has a winner selected.' );
            }

            $tickets = $wpdb->get_results( $wpdb->prepare(
                "SELECT t.*, p.buyer_name
                 FROM {$wpdb->prefix}raffle_tickets t
                 JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id
                 WHERE t.raffle_id = %d",
                $raffle_id
            ) );

            if ( empty( $tickets ) ) {
                $wpdb->query( 'ROLLBACK' );
                wp_send_json_error( 'No tickets sold' );
            }

            $winner_index  = random_int( 0, count( $tickets ) - 1 );
            $winner_ticket = $tickets[ $winner_index ];
            $total_digits  = strlen( (string) $raffle_locked->total_tickets );

            $wpdb->update(
                $wpdb->prefix . 'raffles',
                array( 'winner_ticket_id' => $winner_ticket->id, 'status' => 'finished' ),
                array( 'id' => $raffle_id ),
                array( '%d', '%s' ),
                array( '%d' )
            );

            $wpdb->query( 'COMMIT' );

            Raffle_Audit::log( $raffle_id, 'live_draw_completed', array(
                'ticket' => $winner_ticket->ticket_number,
            ), 'admin' );

            // Send winner notification email
            $winner_purchase = $wpdb->get_row( $wpdb->prepare(
                "SELECT p.buyer_name, p.buyer_email
                 FROM {$wpdb->prefix}raffle_purchases p
                 JOIN {$wpdb->prefix}raffle_tickets t ON t.purchase_id = p.id
                 WHERE t.id = %d",
                $winner_ticket->id
            ) );
            if ( $winner_purchase && class_exists( 'Raffle_Email' ) ) {
                Raffle_Email::send_winner_notification(
                    $winner_purchase->buyer_email,
                    $winner_purchase->buyer_name,
                    $raffle_locked,
                    $winner_ticket->ticket_number
                );
            }

            wp_send_json_success( array(
                'multi'  => false,
                'winner' => array(
                    'ticket_id'     => $winner_ticket->id,
                    'ticket_number' => str_pad( $winner_ticket->ticket_number, $total_digits, '0', STR_PAD_LEFT ),
                    'buyer_name'    => $winner_ticket->buyer_name,
                    'buyer_email'   => $winner_ticket->buyer_email,
                ),
            ) );
        }
    }

    /**
     * Render the live draw page/shortcode.
     */
    public function render_live_draw( $atts ) {
        $atts = shortcode_atts( array( 'raffle_id' => 0 ), $atts );
        $raffle_id = absint( $atts['raffle_id'] );

        if ( ! $raffle_id ) {
            return '<p>No raffle specified.</p>';
        }

        ob_start();
        ?>
        <div id="raffle-live-draw-container" class="raffle-live-draw-container" data-raffle-id="<?php echo esc_attr( $raffle_id ); ?>">
            <div class="raffle-live-draw-stage">
                <h2 class="raffle-live-draw-title">Live Draw</h2>
                <div class="raffle-live-draw-slot-machine">
                    <div class="raffle-drum" id="raffle-drum">
                        <div class="raffle-drum-inner" id="raffle-drum-inner">
                            <!-- Tickets rendered here -->
                        </div>
                    </div>
                </div>
                <div class="raffle-draw-result" id="raffle-draw-result" style="display:none;">
                    <div class="raffle-winner-announce">
                        <span class="raffle-winner-label">WINNER</span>
                        <span class="raffle-winner-number" id="raffle-winner-number"></span>
                        <span class="raffle-winner-name" id="raffle-winner-name"></span>
                    </div>
                </div>
                <button type="button" id="raffle-start-draw-btn" class="raffle-enter-comp-btn">DRAW WINNER</button>
            </div>
        </div>
        <style>
            .raffle-live-draw-container { max-width: 600px; margin: 40px auto; text-align: center; }
            .raffle-live-draw-stage { background: #111827; border-radius: 16px; padding: 40px 20px; color: #fff; }
            .raffle-live-draw-title { font-size: 28px; font-weight: 800; margin-bottom: 30px; }
            .raffle-live-draw-slot-machine { height: 120px; overflow: hidden; border-radius: 12px; background: #1f2937; margin-bottom: 30px; position: relative; }
            .raffle-drum-inner { transition: transform 0.1s linear; }
            .raffle-drum-ticket { height: 60px; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 700; font-family: monospace; color: #fbbf24; }
            .raffle-draw-result { margin: 20px 0; }
            .raffle-winner-announce { display: flex; flex-direction: column; align-items: center; gap: 8px; }
            .raffle-winner-label { font-size: 14px; color: #fbbf24; font-weight: 700; letter-spacing: 3px; }
            .raffle-winner-number { font-size: 48px; font-weight: 800; color: #10b981; font-family: monospace; }
            .raffle-winner-name { font-size: 18px; color: #d1d5db; }
            #raffle-start-draw-btn { margin-top: 20px; }
            .raffle-drum-ticket.winner-highlight { background: #10b981; color: #fff; border-radius: 8px; animation: pulse-winner 0.5s ease-in-out 3; }
            @keyframes pulse-winner { 0%,100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        </style>
        <?php
        return ob_get_clean();
    }
}
