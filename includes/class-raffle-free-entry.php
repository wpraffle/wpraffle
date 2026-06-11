<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Free_Entry {

    public function __construct() {
        add_action( 'wp_ajax_raffle_free_entry', array( $this, 'handle_free_entry' ) );
        add_action( 'wp_ajax_nopriv_raffle_free_entry', array( $this, 'handle_free_entry' ) );
    }

    /**
     * Handle free entry submission.
     */
    public function handle_free_entry() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_free_entry_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $raffle_id   = absint( $_POST['raffle_id'] ?? 0 );
        $buyer_name  = sanitize_text_field( wp_unslash( $_POST['buyer_name'] ?? '' ) );
        $buyer_email = sanitize_email( wp_unslash( $_POST['buyer_email'] ?? '' ) );
        $answer_index = (int) ( $_POST['answer_index'] ?? -1 );

        if ( ! $raffle_id || ! $buyer_name || ! $buyer_email ) {
            wp_send_json_error( array( 'message' => 'All fields are required.' ) );
        }

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d AND status = 'active'",
            $raffle_id
        ) );

        if ( ! $raffle || ! $raffle->allow_free_entry ) {
            wp_send_json_error( array( 'message' => 'Free entries not available for this raffle.' ) );
        }

        // Validate using main skill question (UK Regulations section)
        if ( ! empty( $raffle->enable_question ) && ! empty( $raffle->question_answers ) ) {
            $correct_index = (int) $raffle->correct_answer_index;
            if ( $answer_index !== $correct_index ) {
                wp_send_json_error( array( 'message' => 'Incorrect answer. Please try again.' ) );
            }
        }

        // Rate limiting: one free entry per email per raffle per day
        $rate_key = 'raffle_free_' . md5( $raffle_id . '_' . $buyer_email );
        if ( get_transient( $rate_key ) ) {
            wp_send_json_error( array( 'message' => 'You have already submitted a free entry today. Please try again tomorrow.' ) );
        }

        // Check max tickets per user for free entries
        $existing_free = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}raffle_free_entries WHERE raffle_id = %d AND buyer_email = %s",
            $raffle_id, $buyer_email
        ) );

        $max_free = (int) ( $raffle->max_tickets_per_user ?? 100 );
        if ( $existing_free >= $max_free ) {
            wp_send_json_error( array( 'message' => 'Maximum free entries reached.' ) );
        }

        // Check availability
        $available = $raffle->total_tickets - $raffle->sold_tickets;
        if ( $available <= 0 ) {
            wp_send_json_error( array( 'message' => 'No tickets available.' ) );
        }

        // Create free entry record
        $wpdb->query( 'START TRANSACTION' );

        // Generate a ticket number
        $tickets = Raffle_Tickets::generate_tickets( $raffle_id, 0, 1, $buyer_email, false );

        if ( is_wp_error( $tickets ) ) {
            $wpdb->query( 'ROLLBACK' );
            wp_send_json_error( array( 'message' => $tickets->get_error_message() ) );
        }

        $ticket_number = $tickets[0];

        // Create free entry record
        $wpdb->insert(
            $wpdb->prefix . 'raffle_free_entries',
            array(
                'raffle_id'     => $raffle_id,
                'buyer_name'    => $buyer_name,
                'buyer_email'   => $buyer_email,
                'answer_index'  => $answer_index,
                'ticket_number' => $ticket_number,
                'status'        => 'completed',
                'created_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
        );

        // Also create a purchase record for consistency
        $wpdb->insert(
            $wpdb->prefix . 'raffle_purchases',
            array(
                'raffle_id'      => $raffle_id,
                'buyer_name'     => $buyer_name,
                'buyer_email'    => $buyer_email,
                'quantity'       => 1,
                'total_amount'   => 0,
                'payment_status' => 'completed',
                'purchase_date'  => current_time( 'mysql' ),
                'entry_type'     => 'free',
            ),
            array( '%d', '%s', '%s', '%d', '%f', '%s', '%s', '%s' )
        );

        $wpdb->query( 'COMMIT' );

        // Set rate limit (24 hours)
        set_transient( $rate_key, '1', DAY_IN_SECONDS );

        Raffle_Audit::log( $raffle_id, 'free_entry', array(
            'email'         => $buyer_email,
            'ticket_number' => $ticket_number,
        ), $buyer_email );

        $total_digits = strlen( (string) $raffle->total_tickets );

        wp_send_json_success( array(
            'message' => 'Free entry submitted successfully!',
            'ticket'  => str_pad( $ticket_number, $total_digits, '0', STR_PAD_LEFT ),
        ) );
    }

    /**
     * Get free entry form HTML for a raffle.
     */
    public static function render_free_entry_form( $raffle ) {
        if ( ! $raffle || ! $raffle->allow_free_entry ) {
            return '';
        }

        ob_start();
        ?>
        <div class="raffle-free-entry-section" id="raffle-free-entry-section">
            <h3 class="raffle-section-title">FREE ENTRY (No Purchase Necessary)</h3>
            <p class="raffle-free-entry-desc">Enter for free by answering the skill question below.</p>

            <?php if ( ! empty( $raffle->enable_question ) && ! empty( $raffle->question_text ) ) : ?>
            <div class="raffle-question-wrapper">
                <p style="font-weight:600;margin-bottom:12px;"><?php echo esc_html( $raffle->question_text ); ?></p>
                <?php
                $answers = json_decode( $raffle->question_answers ?? '[]', true );
                if ( ! is_array( $answers ) ) {
                    $answers = array();
                }
                ?>
                <div class="raffle-question-options">
                    <?php foreach ( $answers as $i => $answer ) : ?>
                    <label class="raffle-question-option-card">
                        <input type="radio" name="free_answer" value="<?php echo esc_attr( $i ); ?>">
                        <span class="raffle-question-option-dot"></span>
                        <span class="raffle-question-option-text"><?php echo esc_html( $answer ); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="raffle-free-entry-form">
                <div class="raffle-form-row">
                    <input type="text" id="free-entry-name" placeholder="Full Name *" required>
                </div>
                <div class="raffle-form-row">
                    <input type="email" id="free-entry-email" placeholder="Email Address *" required>
                </div>
                <button type="button" id="raffle-submit-free-entry" class="raffle-enter-comp-btn">SUBMIT FREE ENTRY</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}