<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Public {

    public function __construct() {
        add_shortcode( 'raffle', array( $this, 'render_shortcode' ) );
        add_shortcode( 'raffle_lookup', array( $this, 'render_lookup_shortcode' ) );
        add_shortcode( 'raffle_list', array( $this, 'render_raffle_list_shortcode' ) );
        add_shortcode( 'raffle_ended_list', array( $this, 'render_raffle_ended_list_shortcode' ) );
        add_shortcode( 'raffle_entry_list', array( $this, 'render_entry_list_shortcode' ) );
        add_shortcode( 'raffle_refer', array( $this, 'render_refer_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( 'wp_ajax_raffle_get_sold_numbers', array( $this, 'ajax_get_sold_numbers' ) );
        add_action( 'wp_ajax_nopriv_raffle_get_sold_numbers', array( $this, 'ajax_get_sold_numbers' ) );

        // Viewing-now social-proof badge (Phase 2.5). Public endpoint — only
        // returns a coarse count (no PII), keyed on raffle_id.
        add_action( 'wp_ajax_raffle_viewers', array( $this, 'ajax_get_viewers' ) );
        add_action( 'wp_ajax_nopriv_raffle_viewers', array( $this, 'ajax_get_viewers' ) );

        // SEC-H1 FIX: Entry-list PDF contains entrant PII (names). Restrict the
        // AJAX download to logged-in users only; a nonced nopriv endpoint exposed
        // every entrant's full name to the public internet (GDPR breach).
        add_action( 'wp_ajax_raffle_download_entry_list', array( $this, 'ajax_download_entry_list' ) );

        // Guest ticket lookup — sends a single-use secure-link email.
        add_action( 'wp_ajax_raffle_lookup_send', array( $this, 'ajax_lookup_send' ) );
        add_action( 'wp_ajax_nopriv_raffle_lookup_send', array( $this, 'ajax_lookup_send' ) );

        add_action( 'woocommerce_before_single_product', array( $this, 'maybe_override_product_layout' ) );
        add_filter( 'template_include', array( $this, 'override_single_product_template' ), 99 );
    }

    public function enqueue_assets() {
        global $post;
        $is_raffle_product = false;
        if ( is_a( $post, 'WP_Post' ) && $post->post_type === 'product' ) {
            $is_raffle_product = (bool) get_post_meta( $post->ID, '_raffle_id', true );
        }

        // WooCommerce My Account — my-raffles endpoint
        $is_my_raffles = ( function_exists( 'is_account_page' ) && is_account_page() ) || ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'my-raffles' ) );

        $needs_raffle_assets = $is_raffle_product || $is_my_raffles || (
            is_a( $post, 'WP_Post' ) && (
                has_shortcode( $post->post_content, 'raffle' ) ||
                has_shortcode( $post->post_content, 'raffle_list' ) ||
                has_shortcode( $post->post_content, 'raffle_ended_list' ) ||
                has_shortcode( $post->post_content, 'raffle_entry_list' ) ||
                has_shortcode( $post->post_content, 'raffle_live_draw' ) ||
                has_shortcode( $post->post_content, 'raffle_charities' ) ||
                has_shortcode( $post->post_content, 'raffle_refer' )
            )
        );

        if ( $needs_raffle_assets ) {
            wp_enqueue_style( 'wpraffle-icons', RAFFLE_SYSTEM_URL . 'assets/css/icons.css', array(), RAFFLE_SYSTEM_VERSION );
            wp_enqueue_style( 'raffle-public', RAFFLE_SYSTEM_URL . 'assets/css/public.css', array( 'wpraffle-icons' ), RAFFLE_SYSTEM_VERSION );

            wp_enqueue_script( 'raffle-public', RAFFLE_SYSTEM_URL . 'assets/js/public.js', array( 'jquery' ), RAFFLE_SYSTEM_VERSION, true );

            $localize_data = array(
                'ajax_url'       => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'raffle_purchase_nonce' ),
                'currency_symbol' => wpr_currency_symbol(),
            );

            if ( Raffle_WooCommerce::is_available() ) {
                $localize_data['wc_enabled'] = '1';
                $localize_data['cart_url']   = wc_get_cart_url();
                $localize_data['checkout_url'] = wc_get_checkout_url();
            }

            wp_localize_script( 'raffle-public', 'rafflePublic', $localize_data );
            wp_localize_script( 'raffle-public', 'raffleCountdown', array(
                'labels' => array(
                    'days'    => 'Days',
                    'hours'   => 'Hours',
                    'minutes' => 'Min',
                    'seconds' => 'Sec',
                    'expired' => "It's draw time!",
                ),
            ) );
        }

        // For raffle products using custom template, dequeue WooCommerce block/interactivity scripts
        // that cause "Failed to resolve module specifier @wordpress/interactivity" errors
        if ( $is_raffle_product ) {
            add_action( 'wp_enqueue_scripts', function() {
                // Dequeue WooCommerce Interactivity API scripts (block-based product page)
                wp_dequeue_script( 'wc-product-interactivity-frontend' );
                wp_deregister_script( 'wc-product-interactivity-frontend' );
                wp_dequeue_script( 'woocommerce-product-interactivity-frontend' );
                wp_deregister_script( 'woocommerce-product-interactivity-frontend' );
                wp_dequeue_script( 'wc-interactivity-frontend' );
                wp_deregister_script( 'wc-interactivity-frontend' );
                // Dequeue generic interactivity runtime if present
                wp_dequeue_script( 'wp-interactivity' );
                wp_deregister_script( 'wp-interactivity' );
                // Also deregister WP 6.5+ script modules (new module system)
                if ( function_exists( 'wp_deregister_script_module' ) ) {
                    wp_deregister_script_module( '@wordpress/interactivity' );
                    wp_deregister_script_module( '@woocommerce/product-interactivity' );
                    wp_deregister_script_module( 'wc-product-interactivity' );
                }
            }, 100 );

            // Also filter script tags to remove any remaining interactivity module scripts
            add_filter( 'script_loader_tag', function( $tag, $handle ) {
                // Remove module scripts that reference @wordpress/interactivity
                if ( strpos( $tag, '@wordpress/interactivity' ) !== false || strpos( $tag, 'type="module"' ) !== false ) {
                    if ( preg_match( '/woocommerce|wc-product|interactivity/i', $tag ) ) {
                        return '';
                    }
                }
                return $tag;
            }, 100, 2 );
        }
    }

    public function render_shortcode( $atts ) {
        $atts      = shortcode_atts( array( 'id' => 0 ), $atts, 'raffle' );
        $raffle_id = absint( $atts['id'] );

        if ( ! $raffle_id ) {
            return '<p>Raffle ID not specified.</p>';
        }

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            return '<p>Raffle not found.</p>';
        }

        $packages  = json_decode( $raffle->packages, true ) ?: array();
        $progress  = ( $raffle->total_tickets > 0 ) ? round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 ) : 0;
        $remaining = $raffle->total_tickets - $raffle->sold_tickets;

        ob_start();
        include RAFFLE_SYSTEM_PATH . 'public/views/raffle-display.php';
        return ob_get_clean();
    }

    public function render_lookup_shortcode( $atts ) {
        if ( is_user_logged_in() ) {
            return '<p>' . esc_html__( 'Please visit the', 'wpraffle' ) . ' <a href="' . esc_url( wc_get_account_endpoint_url( 'my-raffles' ) ) . '">' . esc_html__( 'My Raffles', 'wpraffle' ) . '</a> ' . esc_html__( 'section in your account dashboard.', 'wpraffle' ) . '</p>';
        }

        // ── Token-redemption flow: a guest clicked the secure link in the email.
        // Validate the token and render their tickets if it's still valid.
        if ( isset( $_GET['raffle_token'] ) && isset( $_GET['email'] ) ) {
            $token = sanitize_text_field( wp_unslash( $_GET['raffle_token'] ) );
            $email = sanitize_email( wp_unslash( $_GET['email'] ) );
            return $this->render_lookup_token_view( $token, $email );
        }

        ob_start();
        ?>
        <div class="raffle-lookup-container" style="max-width: 400px; margin: 0 auto; padding: 20px; background: var(--wpr-bg-surface); border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h3 style="margin-top: 0;"><?php echo wpr_get_icon( 'ticket', 'wpr-icon--md' ); ?> <?php esc_html_e( 'Lookup Your Tickets', 'wpraffle' ); ?></h3>
            <p><?php esc_html_e( 'Enter the email address used during purchase and we\'ll email you a secure link to view your tickets.', 'wpraffle' ); ?></p>
            <form class="rs-lookup-form" id="rs-lookup-form">
                <?php wp_nonce_field( 'raffle_lookup_nonce', 'lookup_nonce' ); ?>
                <div style="margin-bottom: 15px;">
                    <input type="email" name="lookup_email" id="rs-lookup-email" required placeholder="<?php esc_attr_e( 'Enter your email', 'wpraffle' ); ?>" style="width: 100%; padding: 10px; border: 1px solid var(--wpr-border-color); border-radius: 4px;">
                </div>
                <button type="submit" id="rs-lookup-btn" style="width: 100%; padding: 10px; background: var(--wpr-accent); color: var(--wpr-text-inverse); border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">
                    <?php echo wpr_get_icon( 'mail', 'wpr-icon--xs' ); ?> <?php esc_html_e( 'Send me a link', 'wpraffle' ); ?>
                </button>
            </form>
            <div id="rs-lookup-status" style="margin-top: 15px; display:none; padding: 10px; border-radius: 4px; font-size: 14px;"></div>
        </div>
        <script>
        (function(){
            var form = document.getElementById('rs-lookup-form');
            if (!form) return;
            var btn = document.getElementById('rs-lookup-btn');
            var statusEl = document.getElementById('rs-lookup-status');
            form.addEventListener('submit', function(e){
                e.preventDefault();
                var email = document.getElementById('rs-lookup-email').value;
                btn.disabled = true; btn.style.opacity = '0.7';
                statusEl.style.display = 'block';
                statusEl.style.background = 'var(--wpr-info-bg, #e0f2fe)';
                statusEl.style.color = 'var(--wpr-info-text, #075985)';
                statusEl.textContent = '<?php esc_js( _e( 'Sending your link...', 'wpraffle' ) ); ?>';

                jQuery.post((typeof rafflePublic !== 'undefined' ? rafflePublic.ajax_url : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>'), {
                    action: 'raffle_lookup_send',
                    email: email
                }, function(res){
                    btn.disabled = false; btn.style.opacity = '1';
                    // Always show the same confirmation to prevent email enumeration —
                    // the AJAX handler sends the email server-side if tickets exist.
                    statusEl.style.background = 'var(--wpr-success-bg, #dcfce7)';
                    statusEl.style.color = 'var(--wpr-success-text, #166534)';
                    statusEl.textContent = '<?php esc_js( __( 'If tickets are associated with this email, a secure link has been sent. Please check your inbox (and spam folder).', 'wpraffle' ) ); ?>';
                }).fail(function(){
                    btn.disabled = false; btn.style.opacity = '1';
                    statusEl.style.background = 'var(--wpr-danger-bg, #fee2e2)';
                    statusEl.style.color = 'var(--wpr-danger-text, #991b1b)';
                    statusEl.textContent = '<?php esc_js( __( 'Something went wrong. Please try again.', 'wpraffle' ) ); ?>';
                });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler: generate a single-use token + email the secure link.
     * The response is identical whether or not the email has tickets, to
     * prevent email enumeration (anti-phishing best practice).
     */
    public function ajax_lookup_send() {
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

        if ( ! is_email( $email ) ) {
            // Still return success to avoid revealing validity, but don't email.
            wp_send_json_success( array( 'sent' => false ) );
        }

        global $wpdb;
        // Only send if the email actually has purchases — avoids spamming
        // arbitrary addresses entered into the form.
        $has_purchases = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}raffle_purchases WHERE buyer_email = %s",
            $email
        ) );

        if ( $has_purchases > 0 && class_exists( 'Raffle_Email' ) ) {
            // Single-use token, valid 30 minutes, stored keyed on a hash of the
            // email so it can't be brute-forced or reused.
            $token    = wp_generate_password( 32, false );
            $transient_key = 'wpraffle_lookup_' . md5( $email . '|' . $token );
            set_transient( $transient_key, $email, 30 * MINUTE_IN_SECONDS );

            $lookup_url = add_query_arg(
                array(
                    'raffle_token' => $token,
                    'email'        => $email,
                ),
                $this->get_lookup_page_url()
            );

            Raffle_Email::send_ticket_lookup( $email, $lookup_url );
        }

        // Rate-limit: one request per email per 60s to deter abuse.
        $rate_key = 'wpraffle_lookup_rl_' . md5( $email );
        set_transient( $rate_key, 1, 60 );

        wp_send_json_success( array( 'sent' => true ) );
    }

    /**
     * Resolve the page URL hosting the [raffle_lookup] shortcode. Falls back
     * to the site home URL if no dedicated page is configured.
     */
    private function get_lookup_page_url() {
        $pages = get_option( 'wpraffle_pages', array() );
        if ( ! empty( $pages['lookup'] ) && get_post_status( $pages['lookup'] ) ) {
            return get_permalink( (int) $pages['lookup'] );
        }
        // Fall back to searching for any published page containing the shortcode.
        $found = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            's'              => 'raffle_lookup',
        ) );
        if ( ! empty( $found ) ) {
            return get_permalink( $found[0]->ID );
        }
        return home_url( '/' );
    }

    /**
     * Render the guest ticket view after validating a lookup token. Shows a
     * read-only summary of the buyer's completed purchases + ticket numbers.
     * Single-use: the token transient is deleted after a successful redeem.
     */
    private function render_lookup_token_view( $token, $email ) {
        if ( ! is_email( $email ) || empty( $token ) ) {
            return '<div class="raffle-lookup-container" style="max-width:500px;margin:0 auto;padding:20px;background:var(--wpr-bg-surface);border-radius:8px;">'
                . '<p style="color:var(--wpr-danger, #dc2626);">' . esc_html__( 'This link is invalid.', 'wpraffle' ) . '</p>'
                . '</div>';
        }

        $transient_key = 'wpraffle_lookup_' . md5( $email . '|' . $token );
        $stored_email = get_transient( $transient_key );

        if ( ! $stored_email || $stored_email !== $email ) {
            return '<div class="raffle-lookup-container" style="max-width:500px;margin:0 auto;padding:20px;background:var(--wpr-bg-surface);border-radius:8px;">'
                . '<p style="color:var(--wpr-danger, #dc2626);">' . esc_html__( 'This link has expired or already been used. Please request a new one.', 'wpraffle' ) . '</p>'
                . '<p><a href="' . esc_url( $this->get_lookup_page_url() ) . '">' . esc_html__( 'Request a new link', 'wpraffle' ) . '</a></p>'
                . '</div>';
        }

        // Valid — consume the token so it can't be reused.
        delete_transient( $transient_key );

        global $wpdb;
        $purchases = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, r.title, r.status, r.draw_date, r.total_tickets, r.sold_tickets
             FROM {$wpdb->prefix}raffle_purchases p
             JOIN {$wpdb->prefix}raffles r ON p.raffle_id = r.id
             WHERE p.buyer_email = %s AND p.payment_status = 'completed'
             ORDER BY p.purchase_date DESC",
            $email
        ) );

        ob_start();
        echo '<div class="raffle-lookup-container" style="max-width:600px;margin:0 auto;padding:20px;background:var(--wpr-bg-surface);border-radius:8px;">';
        echo '<h3 style="margin-top:0;">' . wpr_get_icon( 'ticket', 'wpr-icon--md' ) . ' ' . esc_html__( 'Your Tickets', 'wpraffle' ) . '</h3>';
        echo '<p style="color:var(--wpr-text-muted);font-size:13px;">' . esc_html( $email ) . '</p>';

        if ( empty( $purchases ) ) {
            echo '<p class="woocommerce-info">' . esc_html__( 'No completed purchases found for this email address.', 'wpraffle' ) . '</p>';
            echo '</div>';
            return ob_get_clean();
        }

        foreach ( $purchases as $p ) {
            $tickets = $wpdb->get_col( $wpdb->prepare(
                "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE purchase_id = %d ORDER BY ticket_number ASC",
                $p->id
            ) );
            $total_digits = strlen( (string) ( $p->total_tickets ?? 3 ) );
            $is_live = ( Raffle_Public::get_raffle_state( $p ) === 'live' );
            ?>
            <div style="border:1px solid var(--wpr-border-color);border-radius:8px;padding:14px;margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                    <strong style="color:var(--wpr-text-primary);"><?php echo esc_html( $p->title ); ?></strong>
                    <span style="font-size:11px;padding:2px 8px;border-radius:4px;font-weight:700;text-transform:uppercase;background:<?php echo $is_live ? 'var(--wpr-success-bg)' : 'var(--wpr-bg-muted)'; ?>;color:<?php echo $is_live ? 'var(--wpr-success-text)' : 'var(--wpr-text-muted)'; ?>;">
                        <?php echo $is_live ? esc_html__( 'Live', 'wpraffle' ) : esc_html__( 'Finished', 'wpraffle' ); ?>
                    </span>
                </div>
                <div style="font-size:12px;color:var(--wpr-text-muted);margin-bottom:8px;">
                    <?php echo count( $tickets ); ?> <?php esc_html_e( 'tickets', 'wpraffle' ); ?>
                    <?php if ( $p->draw_date ) : ?> &bull; <?php esc_html_e( 'Draw:', 'wpraffle' ); ?> <strong><?php echo esc_html( date_i18n( 'j M Y', strtotime( $p->draw_date ) ) ); ?></strong><?php endif; ?>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;">
                    <?php foreach ( $tickets as $t ) : ?>
                        <code style="background:var(--wpr-bg-muted);color:var(--wpr-text-primary);padding:2px 6px;border-radius:3px;font-size:0.8em;"><?php echo esc_html( str_pad( $t, $total_digits, '0', STR_PAD_LEFT ) ); ?></code>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        }
        echo '</div>';
        return ob_get_clean();
    }

    public function ajax_get_sold_numbers() {
        // Verify nonce to prevent unauthorized data harvesting
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_purchase_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }

        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        if ( ! $raffle_id ) wp_send_json_error();

        // SEC-M3: Rate-limit this public endpoint and verify the raffle exists
        // to prevent bulk harvesting of sold-ticket data.
        if ( class_exists( 'Raffle_Rate_Limiter' ) ) {
            $rate_id  = wpraffle_get_client_ip();
            $rate_chk = Raffle_Rate_Limiter::check_or_error( 'entry_list', $rate_id );
            if ( is_wp_error( $rate_chk ) ) {
                wp_send_json_error( array( 'message' => $rate_chk->get_error_message() ) );
            }
        }

        global $wpdb;
        $sold = $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE raffle_id = %d",
            $raffle_id
        ) );

        // Number-picker grid support: also return reserved (held but unpaid)
        // numbers and the total ticket pool so the JS grid can grey out cells.
        $reserved = array();
        if ( class_exists( 'Raffle_Reservations' ) ) {
            $reserved = Raffle_Reservations::get_reserved_numbers( $raffle_id );
        }
        $total_tickets = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT total_tickets FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        wp_send_json_success( array(
            'sold'          => array_map( 'intval', $sold ),
            'reserved'      => array_map( 'intval', $reserved ),
            'total_tickets' => $total_tickets,
        ) );
    }

    /**
     * AJAX: return the current "viewing now" count for a raffle (Phase 2.5).
     *
     * Uses a rolling 90-second transient window — each viewer refreshes their
     * entry on a 30s heartbeat, so a count represents viewers active in the
     * last ~90s. The count is intentionally coarse (rounded) and contains no
     * PII. No nonce required: the data is non-sensitive and the endpoint is
     * cheap (transient read/write only).
     */
    public function ajax_get_viewers() {
        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        if ( ! $raffle_id ) {
            wp_send_json_error();
        }

        $bucket_key = 'wpr_viewers_' . $raffle_id;
        $ip         = function_exists( 'wpraffle_get_client_ip' ) ? wpraffle_get_client_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
        $now        = time();
        $viewers    = get_transient( $bucket_key );

        if ( ! is_array( $viewers ) ) {
            $viewers = array();
        }
        // Drop stale viewers (older than 90s).
        foreach ( $viewers as $vip => $ts ) {
            if ( ( $now - (int) $ts ) > 90 ) {
                unset( $viewers[ $vip ] );
            }
        }
        // Register/refresh this viewer.
        $viewers[ md5( $ip . '|' . $raffle_id ) ] = $now;
        set_transient( $bucket_key, $viewers, 2 * MINUTE_IN_SECONDS );

        // Coarse count (nearest 5 above the real number) for privacy.
        $count = count( $viewers );
        $coarse = $count <= 5 ? $count : (int) ceil( $count / 5 ) * 5;

        wp_send_json_success( array( 'viewers' => $coarse ) );
    }

    public function maybe_override_product_layout() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }

        $raffle_id = get_post_meta( $post->ID, '_raffle_id', true );
        if ( ! $raffle_id ) {
            return;
        }

        // Clean up standard WooCommerce product single display (classic themes)
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
        remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
        remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
        remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

        // Remove WooCommerce's default product images
        remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );

        // Hide WooCommerce block-based elements (Woo 8.3+ / block themes)
        add_action( 'wp_head', function() {
            echo '<style>
                /* Hide WC block elements for raffle products */
                .wpraffle-hide-wc .wp-block-woocommerce-product-image,
                .wpraffle-hide-wc .wp-block-woocommerce-product-title,
                .wpraffle-hide-wc .wp-block-woocommerce-product-price,
                .wpraffle-hide-wc .wp-block-woocommerce-product-button,
                .wpraffle-hide-wc .wp-block-woocommerce-product-rating,
                .wpraffle-hide-wc .wp-block-woocommerce-product-summary,
                .wpraffle-hide-wc .wp-block-woocommerce-product-details,
                .wpraffle-hide-wc .wp-block-woocommerce-product-meta,
                .wpraffle-hide-wc .wp-block-woocommerce-product-related-products,
                .wpraffle-hide-wc .wp-block-woocommerce-product-upsells,
                .wpraffle-hide-wc .wp-block-woocommerce-product-tabs,
                .wpraffle-hide-wc .woocommerce-tabs,
                .wpraffle-hide-wc .related.products,
                .wpraffle-hide-wc .upsells.products,
                .wpraffle-hide-wc .woocommerce-product-gallery,
                .wpraffle-hide-wc .woocommerce-product-details__short-description,
                .wpraffle-hide-wc form.cart,
                .wpraffle-hide-wc .product_meta { display: none !important; }
            </style>';
        } );

        // Add CSS class to product wrapper to trigger WC element hiding
        add_filter( 'woocommerce_single_product_flexslider_enabled', '__return_false' );
        add_filter( 'post_class', function( $classes ) {
            $classes[] = 'wpraffle-hide-wc';
            return $classes;
        } );

        // Render our custom raffle layout
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_raffle_product_layout' ), 5 );
    }

    public function render_raffle_product_layout() {
        global $post;
        $raffle_id = (int) get_post_meta( $post->ID, '_raffle_id', true );
        if ( ! $raffle_id ) {
            return;
        }

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            echo '<p>Raffle not found.</p>';
            return;
        }

        $packages  = json_decode( $raffle->packages, true ) ?: array();
        $progress  = ( $raffle->total_tickets > 0 ) ? round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 ) : 0;
        $remaining = $raffle->total_tickets - $raffle->sold_tickets;

        // Render public view
        include RAFFLE_SYSTEM_PATH . 'public/views/raffle-display.php';
    }

    /**
     * Override single product template for raffle products.
     */
    public function override_single_product_template( $template ) {
        if ( is_singular( 'product' ) ) {
            $product_id = get_the_ID();
            if ( $product_id && get_post_meta( $product_id, '_raffle_id', true ) ) {
                $custom_template = RAFFLE_SYSTEM_PATH . 'public/views/single-raffle.php';
                if ( file_exists( $custom_template ) ) {
                    return $custom_template;
                }
            }
        }
        return $template;
    }

    public static function get_raffle_state( $raffle ) {
        if ( ! $raffle ) {
            return 'live';
        }
        $now = current_time( 'mysql' );
        $status        = isset( $raffle->status ) ? $raffle->status : 'active';
        $total_tickets = isset( $raffle->total_tickets ) ? (int) $raffle->total_tickets : 0;
        $sold_tickets  = isset( $raffle->sold_tickets ) ? (int) $raffle->sold_tickets : 0;
        $start_date    = isset( $raffle->start_date ) ? $raffle->start_date : '';
        $draw_date     = isset( $raffle->draw_date ) ? $raffle->draw_date : '';
        $remaining     = $total_tickets - $sold_tickets;

        if ( $status === 'draft' ) {
            return 'draft';
        }
        if ( $status === 'active' && ! empty( $start_date ) && $start_date > $now ) {
            return 'draft';
        }
        if ( $status === 'finished' ) {
            return 'ended';
        }
        if ( $status === 'active' && ! empty( $draw_date ) && $draw_date <= $now ) {
            return 'ended';
        }
        if ( $status === 'active' && $remaining <= 0 ) {
            return 'ended';
        }
        return 'live';
    }

    /**
     * Resolve the winner display name according to the site's privacy setting.
     * When 'publish_winner_full_name' is OFF, returns initials only (e.g. "J.S.")
     * so main-draw winners get the same privacy treatment as instant-winners.
     * The full name is still used in winner emails + the audit log regardless.
     *
     * @param string $full_name The buyer's full name as stored on the purchase.
     * @return string Safe-to-display name (full or initials).
     */
    public static function winner_display_name( $full_name ) {
        $general = wp_parse_args( get_option( 'wpraffle_general_settings', array() ), array(
            'publish_winner_full_name' => 1,
        ) );
        if ( ! empty( $general['publish_winner_full_name'] ) ) {
            return $full_name;
        }
        // Initials — reuse the instant-wins helper for consistency.
        if ( class_exists( 'Raffle_Instant_Wins' ) ) {
            $initials = Raffle_Instant_Wins::get_initials( $full_name );
            return $initials !== '' ? $initials : __( 'Winner', 'wpraffle' );
        }
        // Fallback if the instant-wins class isn't loaded.
        $parts = array_filter( explode( ' ', trim( (string) $full_name ) ) );
        $initials = array_map( function ( $p ) {
            return strtoupper( mb_substr( $p, 0, 1 ) );
        }, $parts );
        return $initials ? implode( '.', $initials ) : __( 'Winner', 'wpraffle' );
    }

    /**
     * [raffle_refer] — renders the current user's referral card for a raffle:
     * referral URL + share buttons + bonus entries earned. Requires login.
     *
     * @param array $atts  'raffle_id' required.
     */
    public function render_refer_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'raffle_id' => 0 ), $atts, 'raffle_refer' );
        $raffle_id = absint( $atts['raffle_id'] );
        if ( ! $raffle_id ) {
            return '';
        }

        if ( ! is_user_logged_in() ) {
            return '<div class="raffle-refer-card"><p class="raffle-refer-login">' . esc_html__( 'Log in to get your referral link and earn bonus entries.', 'wpraffle' ) . '</p></div>';
        }

        $user  = wp_get_current_user();
        $email = $user->user_email;
        $code  = class_exists( 'Raffle_Referrals' ) ? Raffle_Referrals::get_referral_code( $raffle_id, $email ) : '';

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT title, wc_product_id, referral_bonus_entries FROM {$wpdb->prefix}raffles WHERE id = %d", $raffle_id ) );
        if ( ! $raffle ) {
            return '';
        }
        $referral_row = $wpdb->get_row( $wpdb->prepare( "SELECT bonus_entries FROM {$wpdb->prefix}raffle_referrals WHERE raffle_id = %d AND user_email = %s", $raffle_id, $email ) );
        $bonus_earned = $referral_row ? (int) $referral_row->bonus_entries : 0;

        $base_url   = get_permalink( $raffle->wc_product_id ) ?: home_url( '?raffle=' . $raffle_id );
        $share_url  = $code ? add_query_arg( 'ref', $code, $base_url ) : $base_url;
        $share_text = rawurlencode( sprintf( __( 'I\'ve entered %s — enter now!', 'wpraffle' ), $raffle->title ) );
        $share_url_enc = rawurlencode( $share_url );

        ob_start();
        ?>
        <div class="raffle-refer-card">
            <span class="raffle-qty-heading"><?php echo wpr_get_icon( 'share', 'wpr-icon--sm' ); ?> <?php esc_html_e( 'REFER & EARN', 'wpraffle' ); ?></span>
            <p class="raffle-refer-intro">
                <?php
                /* translators: %d: bonus entries per referral */
                echo esc_html( sprintf( __( 'Earn %d bonus ticket(s) every time a friend buys using your link.', 'wpraffle' ), (int) $raffle->referral_bonus_entries ) );
                ?>
            </p>
            <div class="raffle-refer-link-row">
                <input type="text" readonly value="<?php echo esc_attr( $share_url ); ?>" class="raffle-refer-link-input">
                <button type="button" class="rs-btn rs-btn-primary raffle-share-copy" data-share-url="<?php echo esc_attr( $share_url ); ?>">
                    <?php echo wpr_get_icon( 'link', 'wpr-icon--sm' ); ?> <?php esc_html_e( 'Copy', 'wpraffle' ); ?>
                </button>
            </div>
            <div class="raffle-share-buttons">
                <a class="raffle-share-btn raffle-share-whatsapp" href="https://wa.me/?text=<?php echo esc_attr( $share_text . '%20' . $share_url_enc ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'WhatsApp', 'wpraffle' ); ?>"><?php echo wpr_get_icon( 'share-whatsapp', 'wpr-icon--md' ); ?></a>
                <a class="raffle-share-btn raffle-share-facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_attr( $share_url_enc ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Facebook', 'wpraffle' ); ?>"><?php echo wpr_get_icon( 'share-facebook', 'wpr-icon--md' ); ?></a>
                <a class="raffle-share-btn raffle-share-x" href="https://twitter.com/intent/tweet?text=<?php echo esc_attr( $share_text ); ?>&url=<?php echo esc_attr( $share_url_enc ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'X', 'wpraffle' ); ?>"><?php echo wpr_get_icon( 'share-x', 'wpr-icon--md' ); ?></a>
            </div>
            <p class="raffle-refer-stat">
                <?php echo wpr_get_icon( 'star', 'wpr-icon--sm wpr-icon--primary' ); ?>
                <?php
                /* translators: %d: bonus entries earned */
                echo esc_html( sprintf( __( 'Bonus entries earned: %d', 'wpraffle' ), $bonus_earned ) );
                ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_raffle_list_shortcode( $atts ) {
        $sc_defaults = self::get_sc_settings( 'raffle_list', array(
            'status' => 'active', // 'active' (live), 'finished' (ended), 'draft', or 'all'
        ) );

        $atts = shortcode_atts( $sc_defaults, $atts, 'raffle_list' );

        global $wpdb;
        $table = $wpdb->prefix . 'raffles';
        $now = current_time( 'mysql' );

        if ( $atts['status'] === 'active' ) {
            $raffles = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE status = 'active' 
                   AND ( start_date IS NULL OR start_date <= %s ) 
                   AND ( draw_date IS NULL OR draw_date > %s )
                   AND sold_tickets < total_tickets
                 ORDER BY created_at DESC",
                $now, $now
            ) );
        } elseif ( $atts['status'] === 'finished' || $atts['status'] === 'ended' ) {
            $raffles = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE status = 'finished' 
                    OR ( status = 'active' AND draw_date IS NOT NULL AND draw_date <= %s ) 
                    OR ( status = 'active' AND sold_tickets >= total_tickets )
                 ORDER BY draw_date DESC, created_at DESC",
                $now
            ) );
        } elseif ( $atts['status'] === 'draft' ) {
            $raffles = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} 
                 WHERE status = 'draft' 
                    OR ( status = 'active' AND start_date IS NOT NULL AND start_date > %s )
                 ORDER BY created_at DESC",
                $now
            ) );
        } else {
            // SEC-19: No user input in this query — table name is from $wpdb->prefix (safe)
            $raffles = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
        }

        if ( empty( $raffles ) ) {
            return '<div style="text-align:center; padding: 40px; color: var(--wpr-text-muted); font-weight: 500; font-size: 16px;">' . wpr_get_icon( 'gift', 'wpr-icon--sm' ) . ' No raffle competitions found.</div>';
        }

        // Enqueue countdown JS for the shortcode page
        wp_enqueue_script( 'raffle-shop-countdown', RAFFLE_SYSTEM_URL . 'assets/js/shop-countdown.js', array( 'jquery' ), RAFFLE_SYSTEM_VERSION, true );

        // Batch instant-win counts for all cards in one query (avoids N+1).
        $iw_counts = function_exists( 'wpraffle_batch_instant_win_counts' )
            ? wpraffle_batch_instant_win_counts( wp_list_pluck( $raffles, 'id' ) )
            : array();

        ob_start();
        ?>
        <div class="raffle-list-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; padding: 20px 0;">
            <?php foreach ( $raffles as $r ) :
                // Build a minimal $product-like wrapper and $raffle reference for the template
                $raffle  = $r;
                $product = $r->wc_product_id ? wc_get_product( $r->wc_product_id ) : null;
                if ( ! $product ) {
                    // Create a fake product-like object for the template
                    $product = new stdClass();
                    $product->_fake = true;
                }
                $iw_count = isset( $iw_counts[ $r->id ] ) ? $iw_counts[ $r->id ] : null;
                ?>
                <div class="raffle-list-item">
                    <?php include RAFFLE_SYSTEM_PATH . 'public/views/raffle-loop-card.php'; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_raffle_ended_list_shortcode( $atts ) {
        // Read tab visibility from settings
        $general = wp_parse_args( get_option( 'wpraffle_general_settings', array() ), array(
            'winners_show_live_draw'    => 1,
            'winners_show_auto_draw'    => 1,
            'winners_show_instant_wins' => 1,
        ) );
        $show_tab_live    = ! empty( $general['winners_show_live_draw'] );
        $show_tab_auto    = ! empty( $general['winners_show_auto_draw'] );
        $show_tab_iw      = ! empty( $general['winners_show_instant_wins'] );

        // Merge stored customisation settings as defaults
        $sc_defaults = self::get_sc_settings( 'raffle_ended_list', array(
            'columns'        => '3',
            'show_image'     => 'yes',
            'show_winner'    => 'yes',
            'show_video_btn' => 'yes',
            'show_verified_btn' => 'yes',
            'show_date'      => 'yes',
            'show_entries'   => 'yes',
        ) );

        $atts = shortcode_atts( $sc_defaults, $atts, 'raffle_ended_list' );

        $cols = max( 1, min( 6, absint( $atts['columns'] ) ) );
        $show = array(
            'image'       => $atts['show_image'] === 'yes',
            'winner'      => $atts['show_winner'] === 'yes',
            'video_btn'   => $atts['show_video_btn'] === 'yes',
            'verified_btn'=> $atts['show_verified_btn'] === 'yes',
            'date'        => $atts['show_date'] === 'yes',
            'entries'     => $atts['show_entries'] === 'yes',
        );

        global $wpdb;
        $table = $wpdb->prefix . 'raffles';
        $now = current_time( 'mysql' );

        $raffles = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE status = 'finished' 
                OR ( status = 'active' AND draw_date IS NOT NULL AND draw_date <= %s ) 
                OR ( status = 'active' AND sold_tickets >= total_tickets )
             ORDER BY draw_date DESC, created_at DESC",
            $now
        ) );

        if ( empty( $raffles ) ) {
            return '<div style="text-align:center; padding: 40px; color: var(--wpr-text-muted); font-weight: 500; font-size: 16px;">' . wpr_get_icon( 'trophy', 'wpr-icon--sm' ) . ' No ended competitions to show yet. Check back soon!</div>';
        }

        // Separate raffles by draw type
        $live_draw_raffles = array();
        $auto_draw_raffles = array();
        foreach ( $raffles as $r ) {
            if ( isset( $r->draw_type ) && $r->draw_type === 'auto' ) {
                $auto_draw_raffles[] = $r;
            } else {
                $live_draw_raffles[] = $r;
            }
        }

        // Determine active tab
        $active_tab = '';
        if ( $show_tab_live && ! empty( $live_draw_raffles ) )    $active_tab = 'live-draw';
        elseif ( $show_tab_auto && ! empty( $auto_draw_raffles ) ) $active_tab = 'auto-draw';
        elseif ( $show_tab_iw )                                    $active_tab = 'instant-wins';
        elseif ( $show_tab_live )                                  $active_tab = 'live-draw';
        elseif ( $show_tab_auto )                                  $active_tab = 'auto-draw';

        // Get all instant wins grouped by date
        $all_iw_by_date = array();
        if ( $show_tab_iw ) {
            $raffle_ids = wp_list_pluck( $raffles, 'id' );
            if ( ! empty( $raffle_ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $raffle_ids ), '%d' ) );
                $all_iw = $wpdb->get_results( $wpdb->prepare(
                    "SELECT iw.*, p.buyer_name as winner_name, r.title as raffle_title, r.total_tickets
                     FROM {$wpdb->prefix}raffle_instant_wins iw
                     LEFT JOIN {$wpdb->prefix}raffle_purchases p ON iw.purchase_id = p.id
                     LEFT JOIN {$wpdb->prefix}raffles r ON iw.raffle_id = r.id
                     WHERE iw.raffle_id IN ({$placeholders}) AND iw.status = 'won'
                     ORDER BY iw.created_at DESC",
                    ...$raffle_ids
                ) );
                foreach ( $all_iw as $iw ) {
                    $dk = date_i18n( 'Y-m-d', strtotime( $iw->created_at ) );
                    if ( ! isset( $all_iw_by_date[ $dk ] ) ) $all_iw_by_date[ $dk ] = array();
                    $all_iw_by_date[ $dk ][] = $iw;
                }
            }
        }

        ob_start();
        ?>
        <div class="raffle-ended-wrapper" style="padding: 20px 0;">

        <?php if ( $show_tab_live || $show_tab_auto || $show_tab_iw ) : ?>

        <!-- Tab Navigation -->
        <div class="raffle-winners-tabs" style="display:flex;gap:0;border-bottom:2px solid var(--wpr-border-color);margin-bottom:30px;">
            <?php if ( $show_tab_live ) : ?>
            <button type="button" class="raffle-winners-tab-btn <?php echo $active_tab === 'live-draw' ? 'active' : ''; ?>" data-tab="live-draw" style="padding:14px 28px;font-size:15px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:<?php echo $active_tab === 'live-draw' ? 'var(--wpr-accent)' : 'var(--wpr-text-muted)'; ?>;border-bottom-color:<?php echo $active_tab === 'live-draw' ? 'var(--wpr-accent)' : 'transparent'; ?>;transition:all 0.2s;">
                <?php echo wpr_get_icon( 'zap', 'wpr-icon--sm' ); ?> Live Draw
            </button>
            <?php endif; ?>
            <?php if ( $show_tab_auto ) : ?>
            <button type="button" class="raffle-winners-tab-btn <?php echo $active_tab === 'auto-draw' ? 'active' : ''; ?>" data-tab="auto-draw" style="padding:14px 28px;font-size:15px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:<?php echo $active_tab === 'auto-draw' ? 'var(--wpr-accent)' : 'var(--wpr-text-muted)'; ?>;border-bottom-color:<?php echo $active_tab === 'auto-draw' ? 'var(--wpr-accent)' : 'transparent'; ?>;transition:all 0.2s;">
                <?php echo wpr_get_icon( 'refresh', 'wpr-icon--sm' ); ?> Auto-Draw
            </button>
            <?php endif; ?>
            <?php if ( $show_tab_iw ) : ?>
            <button type="button" class="raffle-winners-tab-btn <?php echo $active_tab === 'instant-wins' ? 'active' : ''; ?>" data-tab="instant-wins" style="padding:14px 28px;font-size:15px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;color:<?php echo $active_tab === 'instant-wins' ? 'var(--wpr-accent)' : 'var(--wpr-text-muted)'; ?>;border-bottom-color:<?php echo $active_tab === 'instant-wins' ? 'var(--wpr-accent)' : 'transparent'; ?>;transition:all 0.2s;">
                <?php echo wpr_get_icon( 'gift', 'wpr-icon--sm' ); ?> Instant Wins
            </button>
            <?php endif; ?>
        </div>

        <!-- Tab: Live Draw -->
        <?php if ( $show_tab_live ) : ?>
        <div class="raffle-winners-tab-content <?php echo $active_tab === 'live-draw' ? 'active' : ''; ?>" id="tab-live-draw" style="<?php echo $active_tab !== 'live-draw' ? 'display:none;' : ''; ?>">
            <?php if ( empty( $live_draw_raffles ) ) : ?>
                <div style="text-align:center;padding:40px;color:var(--wpr-text-muted);font-weight:500;font-size:16px;"><?php echo wpr_get_icon( 'zap', 'wpr-icon--sm' ); ?> No live draw competitions yet. Check back soon!</div>
            <?php else : ?>
            <div style="display:grid;grid-template-columns:repeat(<?php echo esc_attr( $cols ); ?>,1fr);gap:24px;">
                <?php foreach ( $live_draw_raffles as $r ) :
                    $winner_info = null;
                    if ( $r->winner_ticket_id ) {
                        $winner_info = $wpdb->get_row( $wpdb->prepare(
                            "SELECT t.ticket_number, p.buyer_name FROM {$wpdb->prefix}raffle_tickets t JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id WHERE t.id = %d",
                            $r->winner_ticket_id
                        ) );
                    }
                    $total_digits = strlen( (string) $r->total_tickets );
                    $formatted_num = $winner_info ? str_pad( $winner_info->ticket_number, $total_digits, '0', STR_PAD_LEFT ) : '';
                    $has_video = ! empty( $r->draw_video_url );
                    $has_verified = ! empty( $r->verified_result );
                ?>
                <div style="background:var(--wpr-bg-surface);border:1px solid var(--wpr-border-color);border-radius:16px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05);display:flex;flex-direction:column;">
                    <?php if ( $show['image'] ) : ?>
                        <?php if ( ! empty( $r->prize_image ) ) : ?>
                        <div style="position:relative;overflow:hidden;">
                            <img src="<?php echo esc_url( $r->prize_image ); ?>" style="width:100%;height:200px;object-fit:cover;">
                            <?php if ( $show['date'] && $r->draw_date ) : ?>
                            <div style="position:absolute;top:12px;right:12px;background:rgba(0,0,0,0.65);color:var(--wpr-text-inverse);padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;"><?php echo esc_html( date_i18n( 'jS M Y', strtotime( $r->draw_date ) ) ); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php else : ?>
                        <div class="raffle-image-placeholder" style="width: 100%; height: 200px; background: linear-gradient(135deg, var(--wpr-accent-bg) 0%, var(--wpr-border-color) 100%); display: flex; align-items: center; justify-content: center; color: var(--wpr-accent); position: relative;">
                            <?php echo wpr_get_icon( 'gift', 'wpr-icon--lg', 'Competition Prize' ); ?>
                            <?php if ( $show['date'] && $r->draw_date ) : ?>
                            <div style="position:absolute;top:12px;right:12px;background:rgba(0,0,0,0.65);color:var(--wpr-text-inverse);padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;"><?php echo esc_html( date_i18n( 'jS M Y', strtotime( $r->draw_date ) ) ); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div style="padding:20px;display:flex;flex-direction:column;gap:14px;flex:1;">
                        <div>
                            <h3 style="margin:0 0 4px 0;font-size:17px;font-weight:700;color:var(--wpr-text-primary);line-height:1.3;"><?php echo esc_html( $r->title ); ?></h3>
                            <?php if ( $show['entries'] ) : ?><div style="font-size:13px;color:var(--wpr-text-muted);"><?php echo esc_html( $r->sold_tickets ); ?> / <?php echo esc_html( $r->total_tickets ); ?> entries</div><?php endif; ?>
                        </div>
                        <?php if ( $show['winner'] ) : ?>
                        <div style="background:var(--wpr-success-bg);border:1px solid var(--wpr-success-bg);border-radius:10px;padding:10px 14px;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;">
                            <?php echo wpr_get_icon( 'trophy', 'wpr-icon--sm', 'Winner' ); ?>
                            <?php if ( $winner_info ) : ?>
                                <span style="font-size:14px;font-weight:700;color:var(--wpr-success-text);"><?php echo esc_html( self::winner_display_name( $winner_info->buyer_name ) ); ?></span>
                                <span style="font-size:12px;color:var(--wpr-text-muted);">Ticket: <span style="font-family:monospace;background:var(--wpr-success-bg);color:var(--wpr-success-text);padding:2px 6px;border-radius:4px;font-weight:bold;"><?php echo esc_html( $formatted_num ); ?></span></span>
                            <?php else : ?>
                                <span style="font-size:13px;color:var(--wpr-text-muted);font-weight:600;">Draw pending</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:auto;">
                            <?php if ( $show['video_btn'] && $has_video ) : ?>
                            <a href="<?php echo esc_url( $r->draw_video_url ); ?>" target="_blank" rel="noopener noreferrer" style="flex:1;min-width:120px;padding:8px 14px;border-radius:8px;border:1px solid var(--wpr-accent-border);background:var(--wpr-accent-bg);color:var(--wpr-accent-text);font-weight:600;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;">
                                <?php echo wpr_get_icon( 'zap', 'wpr-icon--sm', 'Watch' ); ?> Watch Draw
                            </a>
                            <?php endif; ?>
                            <?php if ( $show['verified_btn'] && $has_verified ) : ?>
                            <a href="<?php echo esc_url( $r->verified_result ); ?>" target="_blank" rel="noopener noreferrer" style="flex:1;min-width:120px;padding:8px 14px;border-radius:8px;border:1px solid var(--wpr-success-bg);background:var(--wpr-success-bg);color:var(--wpr-success-text);font-weight:600;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;">
                                <?php echo wpr_get_icon( 'check-circle', 'wpr-icon--sm', 'Verified' ); ?> Verified Draw
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Tab: Auto-Draw -->
        <?php if ( $show_tab_auto ) : ?>
        <div class="raffle-winners-tab-content <?php echo $active_tab === 'auto-draw' ? 'active' : ''; ?>" id="tab-auto-draw" style="<?php echo $active_tab !== 'auto-draw' ? 'display:none;' : ''; ?>">
            <?php if ( empty( $auto_draw_raffles ) ) : ?>
                <div style="text-align:center;padding:40px;color:var(--wpr-text-muted);font-weight:500;font-size:16px;"><?php echo wpr_get_icon( 'refresh', 'wpr-icon--sm' ); ?> No auto-draw competitions yet. Check back soon!</div>
            <?php else : ?>
            <div style="display:grid;grid-template-columns:repeat(<?php echo esc_attr( $cols ); ?>,1fr);gap:24px;">
                <?php foreach ( $auto_draw_raffles as $r ) :
                    $winner_info = null;
                    if ( $r->winner_ticket_id ) {
                        $winner_info = $wpdb->get_row( $wpdb->prepare(
                            "SELECT t.ticket_number, p.buyer_name FROM {$wpdb->prefix}raffle_tickets t JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id WHERE t.id = %d",
                            $r->winner_ticket_id
                        ) );
                    }
                    $total_digits = strlen( (string) $r->total_tickets );
                    $formatted_num = $winner_info ? str_pad( $winner_info->ticket_number, $total_digits, '0', STR_PAD_LEFT ) : '';
                    $has_video = ! empty( $r->draw_video_url );
                    $has_verified = ! empty( $r->verified_result );
                ?>
                <div style="background:var(--wpr-bg-surface);border:1px solid var(--wpr-border-color);border-radius:16px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05);display:flex;flex-direction:column;">
                    <?php if ( $show['image'] ) : ?>
                        <?php if ( ! empty( $r->prize_image ) ) : ?>
                        <div style="position:relative;overflow:hidden;">
                            <img src="<?php echo esc_url( $r->prize_image ); ?>" style="width:100%;height:200px;object-fit:cover;">
                            <?php if ( $show['date'] && $r->draw_date ) : ?>
                            <div style="position:absolute;top:12px;right:12px;background:rgba(0,0,0,0.65);color:var(--wpr-text-inverse);padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;"><?php echo esc_html( date_i18n( 'jS M Y', strtotime( $r->draw_date ) ) ); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php else : ?>
                        <div class="raffle-image-placeholder" style="width: 100%; height: 200px; background: linear-gradient(135deg, var(--wpr-accent-bg) 0%, var(--wpr-border-color) 100%); display: flex; align-items: center; justify-content: center; color: var(--wpr-accent); position: relative;">
                            <?php echo wpr_get_icon( 'gift', 'wpr-icon--lg', 'Competition Prize' ); ?>
                            <?php if ( $show['date'] && $r->draw_date ) : ?>
                            <div style="position:absolute;top:12px;right:12px;background:rgba(0,0,0,0.65);color:var(--wpr-text-inverse);padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;"><?php echo esc_html( date_i18n( 'jS M Y', strtotime( $r->draw_date ) ) ); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div style="padding:20px;display:flex;flex-direction:column;gap:14px;flex:1;">
                        <div>
                            <h3 style="margin:0 0 4px 0;font-size:17px;font-weight:700;color:var(--wpr-text-primary);line-height:1.3;"><?php echo esc_html( $r->title ); ?></h3>
                            <div style="font-size:13px;color:var(--wpr-text-muted);">
                                <?php if ( $show['entries'] ) : ?><?php echo esc_html( $r->sold_tickets ); ?> / <?php echo esc_html( $r->total_tickets ); ?> entries<?php endif; ?>
                                <span style="background:var(--wpr-info-bg);color:var(--wpr-info-text);padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;margin-left:6px;">AUTO</span>
                            </div>
                        </div>
                        <?php if ( $show['winner'] ) : ?>
                        <div style="background:var(--wpr-success-bg);border:1px solid var(--wpr-success-bg);border-radius:10px;padding:10px 14px;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;">
                            <?php echo wpr_get_icon( 'trophy', 'wpr-icon--sm', 'Winner' ); ?>
                            <?php if ( $winner_info ) : ?>
                                <span style="font-size:14px;font-weight:700;color:var(--wpr-success-text);"><?php echo esc_html( self::winner_display_name( $winner_info->buyer_name ) ); ?></span>
                                <span style="font-size:12px;color:var(--wpr-text-muted);">Ticket: <span style="font-family:monospace;background:var(--wpr-success-bg);color:var(--wpr-success-text);padding:2px 6px;border-radius:4px;font-weight:bold;"><?php echo esc_html( $formatted_num ); ?></span></span>
                            <?php else : ?>
                                <span style="font-size:13px;color:var(--wpr-text-muted);font-weight:600;">Draw pending</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:auto;">
                            <?php if ( $show['video_btn'] && $has_video ) : ?>
                            <a href="<?php echo esc_url( $r->draw_video_url ); ?>" target="_blank" rel="noopener noreferrer" style="flex:1;min-width:120px;padding:8px 14px;border-radius:8px;border:1px solid var(--wpr-accent-border);background:var(--wpr-accent-bg);color:var(--wpr-accent-text);font-weight:600;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;">
                                <?php echo wpr_get_icon( 'zap', 'wpr-icon--sm', 'Watch' ); ?> Watch Draw
                            </a>
                            <?php endif; ?>
                            <?php if ( $show['verified_btn'] && $has_verified ) : ?>
                            <a href="<?php echo esc_url( $r->verified_result ); ?>" target="_blank" rel="noopener noreferrer" style="flex:1;min-width:120px;padding:8px 14px;border-radius:8px;border:1px solid var(--wpr-success-bg);background:var(--wpr-success-bg);color:var(--wpr-success-text);font-weight:600;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;">
                                <?php echo wpr_get_icon( 'check-circle', 'wpr-icon--sm', 'Verified' ); ?> Verified Draw
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Tab: Instant Wins -->
        <?php if ( $show_tab_iw ) : ?>
        <div class="raffle-winners-tab-content <?php echo $active_tab === 'instant-wins' ? 'active' : ''; ?>" id="tab-instant-wins" style="<?php echo $active_tab !== 'instant-wins' ? 'display:none;' : ''; ?>">
            <?php if ( empty( $all_iw_by_date ) ) : ?>
                <div style="text-align:center;padding:40px;color:var(--wpr-text-muted);font-weight:500;font-size:16px;"><?php echo wpr_get_icon( 'gift', 'wpr-icon--sm' ); ?> No instant win claims yet. Check back soon!</div>
            <?php else : ?>
                <?php foreach ( $all_iw_by_date as $date_key => $date_wins ) : ?>
                <div style="margin-bottom:30px;">
                    <h3 style="font-size:16px;font-weight:700;color:var(--wpr-text-primary);margin:0 0 16px 0;display:flex;align-items:center;gap:8px;">
                        <span style="background:var(--wpr-bg-muted);padding:6px 14px;border-radius:8px;font-size:13px;"><?php echo esc_html( date_i18n( 'l, jS F Y', strtotime( $date_key ) ) ); ?></span>
                        <span style="font-size:12px;color:var(--wpr-text-muted);font-weight:500;"><?php echo count( $date_wins ); ?> win<?php echo count( $date_wins ) > 1 ? 's' : ''; ?></span>
                    </h3>
                    <div style="display:grid;grid-template-columns:repeat(<?php echo esc_attr( $cols ); ?>,1fr);gap:16px;">
                        <?php foreach ( $date_wins as $iw ) :
                            $td = strlen( (string) $iw->total_tickets );
                            $fn = str_pad( $iw->ticket_number, $td, '0', STR_PAD_LEFT );
                            $initials = class_exists( 'Raffle_Instant_Wins' ) ? Raffle_Instant_Wins::get_initials( $iw->winner_name ) : '';
                        ?>
                        <div style="background:var(--wpr-bg-surface);border:1px solid var(--wpr-border-color);border-radius:12px;padding:16px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 4px rgba(0,0,0,0.04);">
                            <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--wpr-accent),var(--wpr-accent-dark));display:flex;align-items:center;justify-content:center;color:var(--wpr-text-inverse);font-weight:800;font-size:14px;flex-shrink:0;">
                                <?php echo esc_html( $initials ?: '?' ); ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-weight:700;font-size:14px;color:var(--wpr-text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html( $iw->prize_name ); ?></div>
                                <div style="font-size:12px;color:var(--wpr-text-muted);margin-top:2px;">
                                    <?php echo esc_html( $iw->raffle_title ); ?> &bull; Ticket #<span style="font-family:monospace;font-weight:700;"><?php echo esc_html( $fn ); ?></span>
                                </div>
                            </div>
                            <div style="background:var(--wpr-success-bg);color:var(--wpr-success-text);padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;flex-shrink:0;">Won</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Tab Switching JS -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tabs = document.querySelectorAll('.raffle-winners-tab-btn');
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    var target = tab.getAttribute('data-tab');
                    tabs.forEach(function(t) { t.style.color = 'var(--wpr-text-muted)'; t.style.borderBottomColor = 'transparent'; t.classList.remove('active'); });
                    document.querySelectorAll('.raffle-winners-tab-content').forEach(function(c) { c.style.display = 'none'; c.classList.remove('active'); });
                    tab.style.color = 'var(--wpr-accent)'; tab.style.borderBottomColor = 'var(--wpr-accent)'; tab.classList.add('active');
                    var content = document.getElementById('tab-' + target);
                    if (content) { content.style.display = 'block'; content.classList.add('active'); }
                });
            });
        });
        </script>

        <?php else : ?>
            <div style="text-align:center;padding:40px;color:var(--wpr-text-muted);font-weight:500;font-size:16px;">No tabs enabled. Configure winners page in Settings.</div>
        <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the entry list page showing all closed/ended raffles with download buttons.
     */
    public function render_entry_list_shortcode( $atts ) {
        $sc_defaults = self::get_sc_settings( 'raffle_entry_list', array(
            'button_text' => 'Download Entry List',
            'button_bg'   => 'var(--wpr-accent)',
            'button_color'=> 'var(--wpr-text-inverse)',
            'button_radius' => '8',
            'show_image'  => 'yes',
            'columns'     => '2',
            'layout'      => 'grid',
        ) );

        $atts = shortcode_atts( $sc_defaults, $atts, 'raffle_entry_list' );

        global $wpdb;
        $table = $wpdb->prefix . 'raffles';
        $now   = current_time( 'mysql' );

        $raffles = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = 'finished'
                OR ( status = 'active' AND draw_date IS NOT NULL AND draw_date <= %s )
                OR ( status = 'active' AND sold_tickets >= total_tickets )
             ORDER BY draw_date DESC, created_at DESC",
            $now
        ) );

        if ( empty( $raffles ) ) {
            return '<div style="text-align:center; padding: 40px; color: var(--wpr-text-muted); font-weight: 500; font-size: 16px;">No closed competitions found. Check back soon!</div>';
        }

        $cols         = max( 1, min( 4, absint( $atts['columns'] ) ) );
        $show_image   = $atts['show_image'] === 'yes';
        $btn_text     = $atts['button_text'];
        $btn_bg       = $atts['button_bg'];
        $btn_color    = $atts['button_color'];
        $btn_radius   = intval( $atts['button_radius'] );

        ob_start();
        include RAFFLE_SYSTEM_PATH . 'public/views/entry-list.php';
        return ob_get_clean();
    }

    /**
     * AJAX handler to generate and return a PDF entry list for a closed raffle.
     */
    public function ajax_download_entry_list() {
        // SEC-H1 FIX: Defence in depth — even though this is only registered as a
        // priv-ajax action, enforce an explicit login check here.
        if ( ! is_user_logged_in() ) {
            wp_die( 'You must be logged in to download entry lists.', 'Forbidden', array( 'response' => 403 ) );
        }

        $raffle_id = isset( $_GET['raffle_id'] ) ? absint( $_GET['raffle_id'] ) : 0;
        if ( ! $raffle_id ) {
            wp_die( 'Invalid raffle ID.' );
        }

        // Verify nonce
        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'raffle_entry_list_nonce' ) ) {
            wp_die( 'Security check failed.' );
        }

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            wp_die( 'Raffle not found.' );
        }

        // Only allow downloads for closed/ended raffles
        $state = self::get_raffle_state( $raffle );
        if ( $state !== 'ended' ) {
            wp_die( 'Entry list is only available for closed competitions.' );
        }

        $tickets = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.ticket_number, t.buyer_email, p.buyer_name, p.purchase_date, p.entry_type
             FROM {$wpdb->prefix}raffle_tickets t
             JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id
             WHERE t.raffle_id = %d
             ORDER BY t.ticket_number ASC",
            $raffle_id
        ) );

        $total_digits = strlen( (string) $raffle->total_tickets );
        $filename     = sanitize_file_name( 'entry-list-' . $raffle->id . '-' . sanitize_title( $raffle->title ) . '.pdf' );

        // Generate PDF using built-in lightweight PDF generator
        $pdf_content = WPRaffle_PDF::entry_list( $raffle, $tickets, $total_digits );

        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        header( 'Content-Length: ' . strlen( $pdf_content ) );

        echo $pdf_content;
        exit;
    }

    /**
     * Get stored shortcode customisation settings merged with defaults.
     * If customisation is not enabled for the shortcode, returns original defaults.
     */
    private static function get_sc_settings( $shortcode, $defaults ) {
        $sc_settings = get_option( 'wpraffle_shortcode_settings', array() );
        if ( ! isset( $sc_settings[ $shortcode ] ) || empty( $sc_settings[ $shortcode ]['enabled'] ) ) {
            return $defaults;
        }
        $stored = $sc_settings[ $shortcode ];
        unset( $stored['enabled'] );
        // Merge stored over defaults (stored values take precedence)
        return wp_parse_args( $stored, $defaults );
    }
}
