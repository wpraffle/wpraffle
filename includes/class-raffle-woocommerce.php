<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_WooCommerce {

    public function __construct() {
        // AJAX: create WooCommerce order and return pay URL
        add_action( 'wp_ajax_raffle_create_order', array( $this, 'ajax_create_order' ) );
        add_action( 'wp_ajax_nopriv_raffle_create_order', array( $this, 'ajax_create_order' ) );

        // AJAX: add raffle tickets natively to WooCommerce cart
        add_action( 'wp_ajax_raffle_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_raffle_add_to_cart', array( $this, 'ajax_add_to_cart' ) );

        // On payment complete → generate tickets
        add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ) );
        add_action( 'woocommerce_order_status_completed', array( $this, 'on_payment_complete' ) );
        add_action( 'woocommerce_order_status_processing', array( $this, 'on_payment_complete' ) );

        // Custom thank-you page content for raffle orders
        add_action( 'woocommerce_thankyou', array( $this, 'thankyou_raffle_tickets' ) );

        // Show raffle info in admin order details
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_order_meta' ) );

        // Cart Integration Hooks
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_price' ), 20, 1 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_meta' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );

        // My Account Integration
        add_action( 'init', array( $this, 'add_my_raffles_endpoint' ) );
        add_filter( 'query_vars', array( $this, 'add_my_raffles_query_vars' ), 0 );
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_my_raffles_menu_item' ) );
        add_action( 'woocommerce_account_my-raffles_endpoint', array( $this, 'my_raffles_endpoint_content' ) );

        // Shop Loop Customization
        add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'custom_raffle_loop_button_text' ), 10, 2 );
        add_filter( 'woocommerce_product_add_to_cart_url', array( $this, 'custom_raffle_loop_button_url' ), 10, 2 );

        // Shop Loop Override — replace entire card for raffle products
        add_action( 'woocommerce_before_shop_loop_item', array( $this, 'override_raffle_shop_loop_item' ), 1 );
        add_filter( 'woocommerce_post_class', array( $this, 'add_raffle_product_class' ), 10, 2 );
        add_filter( 'post_class', array( $this, 'add_raffle_post_class' ), 10, 3 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_shop_assets' ) );

        // Register Raffle Product Type
        add_filter( 'product_type_selector', array( $this, 'add_raffle_product_type' ) );
        add_filter( 'woocommerce_product_class', array( $this, 'raffle_product_class' ), 10, 2 );

        // Custom tabs & panels in WooCommerce Admin Product Edit Screen
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'configure_raffle_product_tabs' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'raffle_product_data_panels' ) );
        add_action( 'woocommerce_process_product_meta_raffle', array( $this, 'save_raffle_product_data' ), 10, 1 );

        // Exclude non-live raffles from Shop catalog
        add_action( 'woocommerce_product_query', array( $this, 'exclude_non_live_raffles_from_shop' ) );

        // Cart quantity enforcement: prevent quantity changes for raffle items
        add_filter( 'woocommerce_cart_item_quantity', array( $this, 'lock_cart_quantity' ), 10, 3 );
        add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'enforce_cart_item_subtotal' ), 10, 3 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'enforce_cart_quantity_limits' ), 99, 1 );
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout_quantities' ) );
    }

    public function add_my_raffles_endpoint() {
        add_rewrite_endpoint( 'my-raffles', EP_ROOT | EP_PAGES );
    }

    public function add_my_raffles_query_vars( $vars ) {
        $vars[] = 'my-raffles';
        return $vars;
    }

    public function add_my_raffles_menu_item( $items ) {
        $items['my-raffles'] = 'My Raffles';
        return $items;
    }

    public function my_raffles_endpoint_content() {
        require_once RAFFLE_SYSTEM_PATH . 'public/views/my-raffles.php';
    }

    /**
     * Check if WooCommerce is installed and active.
     */
    public static function is_available() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * AJAX: Create a WooCommerce order from the raffle purchase form.
     */
    public function ajax_create_order() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_purchase_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error. Please refresh the page.' ) );
        }

        if ( ! self::is_available() ) {
            wp_send_json_error( array( 'message' => 'WooCommerce is not installed.' ) );
        }

        $raffle_id   = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $quantity    = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;
        $buyer_name  = isset( $_POST['buyer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['buyer_name'] ) ) : '';
        $buyer_email = isset( $_POST['buyer_email'] ) ? sanitize_email( wp_unslash( $_POST['buyer_email'] ) ) : '';
        $selected_numbers = isset( $_POST['selected_numbers'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_numbers'] ) ) : '';

        if ( ! $raffle_id || ! $quantity || ! $buyer_name || ! $buyer_email ) {
            wp_send_json_error( array( 'message' => 'All fields are required.' ) );
        }

        if ( ! is_email( $buyer_email ) ) {
            wp_send_json_error( array( 'message' => 'Invalid email address.' ) );
        }

        global $wpdb;
        $table_raffles   = $wpdb->prefix . 'raffles';
        $table_purchases = $wpdb->prefix . 'raffle_purchases';

        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_raffles} WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle || Raffle_Public::get_raffle_state( $raffle ) !== 'live' ) {
            wp_send_json_error( array( 'message' => 'This raffle is not currently active/live.' ) );
        }

        // SEC-6 FIX: Enforce geo restrictions in order creation
        if ( class_exists( 'Raffle_Geo' ) && ! Raffle_Geo::check_eligibility( $raffle ) ) {
            wp_send_json_error( array( 'message' => 'This competition is not available in your region.' ) );
        }

        // Validate max tickets per user
        $max_tickets = isset( $raffle->max_tickets_per_user ) ? (int) $raffle->max_tickets_per_user : 100;
        if ( $quantity < 1 || $quantity > $max_tickets ) {
            wp_send_json_error( array( 'message' => sprintf( 'You can purchase between 1 and %d tickets.', $max_tickets ) ) );
        }

        // Validate skill question
        $enable_question = isset( $raffle->enable_question ) ? (bool) $raffle->enable_question : false;
        if ( $enable_question ) {
            $answer_index = isset( $_POST['answer_index'] ) ? (int) $_POST['answer_index'] : -1;
            $correct_index = isset( $raffle->correct_answer_index ) ? (int) $raffle->correct_answer_index : 0;
            if ( $answer_index !== $correct_index ) {
                wp_send_json_error( array( 'message' => 'Incorrect answer to the skill question. Please try again.' ) );
            }
        }

        $available = $raffle->total_tickets - $raffle->sold_tickets;
        if ( $quantity > $available ) {
            wp_send_json_error( array( 'message' => 'Not enough tickets available. ' . $available . ' remaining.' ) );
        }

        // SEC-5 FIX: Validate selected_numbers server-side
        if ( ! empty( $selected_numbers ) ) {
            $validation = Raffle_Tickets::validate_selected_numbers( $raffle_id, $selected_numbers, $quantity );
            if ( is_wp_error( $validation ) ) {
                wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
            }
        }

        $total_amount = $quantity * $raffle->ticket_price;

        $product_id = (int) $raffle->wc_product_id;
        if ( ! $product_id || ! get_post( $product_id ) ) {
            $product_id = get_option( 'raffle_system_wc_product_id' );
        }
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'System error: Raffle product not configured.' ) );
        }

        // Empty cart if you only want 1 raffle checkout at a time, or leave it to allow multiple
        // WC()->cart->empty_cart();

        $cart_item_data = array(
            'raffle_id'        => $raffle_id,
            'raffle_quantity'  => $quantity,
            'raffle_price'     => $total_amount,
            'raffle_title'     => $raffle->title,
            'buyer_name'       => $buyer_name,
            'buyer_email'      => $buyer_email,
            'selected_numbers' => $selected_numbers,
        );

        WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

        // Set session data to prepopulate checkout fields
        if ( ! is_user_logged_in() ) {
            $name_parts = explode( ' ', $buyer_name, 2 );
            WC()->session->set( 'customer', array(
                'first_name' => $name_parts[0],
                'last_name'  => isset( $name_parts[1] ) ? $name_parts[1] : '',
                'email'      => $buyer_email,
            ) );
        }

        wp_send_json_success( array(
            'pay_url' => wc_get_checkout_url(),
        ) );
    }

    /**
     * AJAX: Add raffle tickets natively to WooCommerce cart.
     */
    public function ajax_add_to_cart() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'raffle_purchase_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security error. Please refresh the page.' ) );
        }

        if ( ! self::is_available() ) {
            wp_send_json_error( array( 'message' => 'WooCommerce is not installed.' ) );
        }

        $raffle_id        = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $quantity         = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;
        $selected_numbers = isset( $_POST['selected_numbers'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_numbers'] ) ) : '';
        $answer_index     = isset( $_POST['answer_index'] ) ? (int) $_POST['answer_index'] : -1;

        if ( ! $raffle_id || ! $quantity ) {
            wp_send_json_error( array( 'message' => 'Raffle ID and quantity are required.' ) );
        }

        global $wpdb;
        $table_raffles = $wpdb->prefix . 'raffles';

        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_raffles} WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle || Raffle_Public::get_raffle_state( $raffle ) !== 'live' ) {
            wp_send_json_error( array( 'message' => 'This raffle is not currently active/live.' ) );
        }

        // SEC-6 FIX: Enforce geo restrictions in add to cart
        if ( class_exists( 'Raffle_Geo' ) && ! Raffle_Geo::check_eligibility( $raffle ) ) {
            wp_send_json_error( array( 'message' => 'This competition is not available in your region.' ) );
        }

        // Validate max tickets per user
        $max_tickets = isset( $raffle->max_tickets_per_user ) ? (int) $raffle->max_tickets_per_user : 100;
        if ( $quantity < 1 || $quantity > $max_tickets ) {
            wp_send_json_error( array( 'message' => sprintf( 'You can purchase between 1 and %d tickets.', $max_tickets ) ) );
        }

        // Validate skill question
        $enable_question = isset( $raffle->enable_question ) ? (bool) $raffle->enable_question : false;
        if ( $enable_question ) {
            $correct_index = isset( $raffle->correct_answer_index ) ? (int) $raffle->correct_answer_index : 0;
            if ( $answer_index !== $correct_index ) {
                wp_send_json_error( array( 'message' => 'Incorrect answer to the skill question. Please try again.' ) );
            }
        }

        $available = $raffle->total_tickets - $raffle->sold_tickets;
        if ( $quantity > $available ) {
            wp_send_json_error( array( 'message' => 'Not enough tickets available. Only ' . $available . ' remaining.' ) );
        }

        // SEC-5 FIX: Validate selected_numbers server-side
        if ( ! empty( $selected_numbers ) ) {
            $validation = Raffle_Tickets::validate_selected_numbers( $raffle_id, $selected_numbers, $quantity );
            if ( is_wp_error( $validation ) ) {
                wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
            }
        }

        $product_id = (int) $raffle->wc_product_id;
        if ( ! $product_id || ! get_post( $product_id ) ) {
            wp_send_json_error( array( 'message' => 'System error: WooCommerce product is not configured.' ) );
        }

        $cart_item_data = array(
            'raffle_id'        => $raffle_id,
            'raffle_quantity'  => $quantity,
            'selected_numbers' => $selected_numbers,
            'answer_index'     => $answer_index,
        );

        // Add to cart natively
        $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, 0, array(), $cart_item_data );

        if ( ! $cart_item_key ) {
            wp_send_json_error( array( 'message' => 'Could not add raffle to cart. Please try again.' ) );
        }

        wp_send_json_success( array(
            'cart_url'     => wc_get_cart_url(),
            'checkout_url' => wc_get_checkout_url(),
        ) );
    }

    public function set_cart_item_price( $cart_obj ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        foreach ( $cart_obj->get_cart() as $key => $value ) {
            if ( isset( $value['raffle_price'] ) ) {
                $value['data']->set_price( $value['raffle_price'] );
                $value['data']->set_name( 'Raffle Tickets — ' . $value['raffle_title'] . ' (x' . $value['raffle_quantity'] . ')' );
            }
        }
    }

    public function display_cart_item_meta( $item_data, $cart_item ) {
        if ( isset( $cart_item['raffle_id'] ) ) {
            global $wpdb;
            $raffle = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
                absint( $cart_item['raffle_id'] )
            ) );

            if ( $raffle ) {
                if ( isset( $cart_item['answer_index'] ) && $cart_item['answer_index'] !== -1 ) {
                    $answers = json_decode( $raffle->question_answers, true ) ?: array();
                    $selected_answer = $answers[ $cart_item['answer_index'] ] ?? '';
                    if ( $selected_answer ) {
                        $item_data[] = array(
                            'key'   => 'Skill Question Answer',
                            'value' => $selected_answer,
                        );
                    }
                }

                if ( ! empty( $cart_item['selected_numbers'] ) ) {
                    $total_tickets = $raffle->total_tickets;
                    $nums = explode( ',', $cart_item['selected_numbers'] );
                    $formatted = array_map( function( $n ) use ( $total_tickets ) {
                        return Raffle_Tickets::format_ticket_number( (int)$n, $total_tickets );
                    }, $nums );

                    $item_data[] = array(
                        'key'   => 'Selected Numbers',
                        'value' => implode( ', ', $formatted ),
                    );
                } else {
                    $item_data[] = array(
                        'key'   => 'Selection Mode',
                        'value' => 'Random Selection',
                    );
                }
            }
        }
        return $item_data;
    }

    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['raffle_id'] ) ) {
            $item->add_meta_data( '_raffle_id', $values['raffle_id'] );
            
            $quantity = isset( $values['raffle_quantity'] ) ? (int) $values['raffle_quantity'] : ( isset( $values['quantity'] ) ? (int) $values['quantity'] : $item->get_quantity() );
            $item->add_meta_data( '_raffle_quantity', $quantity );
            
            // Retrieve name and email natively from the billing details submitted at checkout
            $buyer_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
            $buyer_email = $order->get_billing_email();
            
            if ( empty( $buyer_email ) && isset( $_POST['billing_email'] ) ) {
                $buyer_email = sanitize_email( wp_unslash( $_POST['billing_email'] ) );
            }
            if ( empty( $buyer_email ) && is_user_logged_in() ) {
                $buyer_email = wp_get_current_user()->user_email;
            }
            
            if ( empty( $buyer_name ) && isset( $_POST['billing_first_name'] ) ) {
                $first = sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ) );
                $last  = isset( $_POST['billing_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ) ) : '';
                $buyer_name = trim( $first . ' ' . $last );
            }
            if ( empty( $buyer_name ) && is_user_logged_in() ) {
                $buyer_name = wp_get_current_user()->display_name;
            }
            
            $item->add_meta_data( '_raffle_buyer_name', $buyer_name );
            $item->add_meta_data( '_raffle_buyer_email', $buyer_email );
            $item->add_meta_data( '_is_raffle_order', 'yes' );
            
            if ( ! empty( $values['selected_numbers'] ) ) {
                $item->add_meta_data( '_raffle_selected_numbers', $values['selected_numbers'] );
            }
            if ( isset( $values['answer_index'] ) ) {
                $item->add_meta_data( '_raffle_answer_index', $values['answer_index'] );
            }
            
            // Mark the order itself as containing raffles
            $order->update_meta_data( '_has_raffle_items', 'yes' );
        }
    }

    /**
     * Hook: On payment complete, generate raffle tickets.
     */
    public function on_payment_complete( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        // Only process if order has raffle items
        if ( $order->get_meta( '_has_raffle_items' ) !== 'yes' ) {
            return;
        }

        // Already processed?
        if ( $order->get_meta( '_raffle_tickets_generated' ) === 'yes' ) {
            return;
        }

        global $wpdb;
        $table_purchases = $wpdb->prefix . 'raffle_purchases';
        $table_raffles   = $wpdb->prefix . 'raffles';

        $all_tickets_formatted = array();
        $all_instant_wins = array();
        
        // Loop through all order items to find raffles
        foreach ( $order->get_items() as $item_id => $item ) {
            if ( $item->get_meta( '_is_raffle_order' ) !== 'yes' ) {
                continue;
            }

            $raffle_id   = (int) $item->get_meta( '_raffle_id' );
            $quantity    = (int) $item->get_meta( '_raffle_quantity' );
            $buyer_name  = $item->get_meta( '_raffle_buyer_name' );
            $buyer_email = $item->get_meta( '_raffle_buyer_email' );
            $selected_numbers = $item->get_meta( '_raffle_selected_numbers' );

            if ( empty( $buyer_email ) ) {
                $buyer_email = $order->get_billing_email();
            }
            if ( empty( $buyer_email ) && $order->get_user_id() ) {
                $user = $order->get_user();
                if ( $user ) {
                    $buyer_email = $user->user_email;
                }
            }
            if ( empty( $buyer_email ) && is_user_logged_in() ) {
                $buyer_email = wp_get_current_user()->user_email;
            }

            if ( empty( $buyer_name ) ) {
                $buyer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
            }
            if ( empty( $buyer_name ) && $order->get_user_id() ) {
                $user = $order->get_user();
                if ( $user ) {
                    $buyer_name = $user->display_name;
                }
            }
            if ( empty( $buyer_name ) && is_user_logged_in() ) {
                $buyer_name = wp_get_current_user()->display_name;
            }

            // Update item meta so it has the fallback values stored correctly
            $item->update_meta_data( '_raffle_buyer_name', $buyer_name );
            $item->update_meta_data( '_raffle_buyer_email', $buyer_email );
            $item->save();

            if ( ! $raffle_id || ! $quantity ) {
                continue;
            }

            // FINAL VALIDATION: Re-check quantity against raffle limits before generating tickets
            $raffle_check = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$table_raffles} WHERE id = %d",
                $raffle_id
            ) );

            if ( ! $raffle_check ) {
                $order->add_order_note( "Raffle ID {$raffle_id} not found. Skipping ticket generation." );
                continue;
            }

            $max_per_user = (int) $raffle_check->max_tickets_per_user;
            $available    = (int) $raffle_check->total_tickets - (int) $raffle_check->sold_tickets;

            if ( $quantity > $max_per_user ) {
                $order->add_order_note( "SECURITY: Order attempted {$quantity} tickets for raffle #{$raffle_id} (max: {$max_per_user}). Quantity clamped." );
                $quantity = min( $quantity, $max_per_user );
            }

            if ( $quantity > $available ) {
                $order->add_order_note( "SECURITY: Order attempted {$quantity} tickets but only {$available} available for raffle #{$raffle_id}. Quantity clamped." );
                $quantity = min( $quantity, max( $available, 0 ) );
            }

            if ( $quantity < 1 ) {
                $order->add_order_note( "Raffle #{$raffle_id}: No tickets available. Skipping." );
                continue;
            }

            // Create purchase record now since we are in standard cart flow
            $wpdb->insert( $table_purchases, array(
                'raffle_id'      => $raffle_id,
                'buyer_name'     => $buyer_name,
                'buyer_email'    => $buyer_email,
                'quantity'       => $quantity,
                'total_amount'   => $item->get_total(),
                'payment_status' => 'completed',
                'wc_order_id'    => $order_id,
                'purchase_date'  => current_time( 'mysql' ),
            ), array( '%d', '%s', '%s', '%d', '%f', '%s', '%d', '%s' ) );

            $purchase_id = $wpdb->insert_id;
            
            $item->add_meta_data( '_raffle_purchase_id', $purchase_id );
            $item->save();

            // ATOMIC TRANSACTION: tickets + status update
            $wpdb->query( 'START TRANSACTION' );

            $tickets = Raffle_Tickets::generate_tickets( $raffle_id, $purchase_id, $quantity, $buyer_email, false, $selected_numbers );

            if ( is_wp_error( $tickets ) ) {
                $wpdb->query( 'ROLLBACK' );
                $order->add_order_note( 'Error generating tickets for Raffle ID ' . $raffle_id . ': ' . $tickets->get_error_message() );
                continue;
            }

            // Check for instant wins (inside transaction so FOR UPDATE lock is effective)
            $instant_wins = Raffle_Instant_Wins::check_for_instant_wins( $raffle_id, $purchase_id, $tickets, $buyer_email );
            if ( ! empty( $instant_wins ) ) {
                $all_instant_wins = array_merge( $all_instant_wins, $instant_wins );
            }

            $wpdb->query( 'COMMIT' );

            // Audit log for WC purchase
            if ( class_exists( 'Raffle_Audit' ) ) {
                Raffle_Audit::log( $raffle_id, 'purchase', "WooCommerce purchase: {$quantity} ticket(s) by {$buyer_email} ({$buyer_name}). Order #{$order_id}, Purchase #{$purchase_id}.", '' );
            }

            $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_raffles} WHERE id = %d", $raffle_id ) );
            $total_digits = $raffle ? strlen( (string) $raffle->total_tickets ) : 3;
            $formatted    = array_map( function ( $n ) use ( $total_digits ) {
                return str_pad( $n, $total_digits, '0', STR_PAD_LEFT );
            }, $tickets );

            $all_tickets_formatted = array_merge( $all_tickets_formatted, $formatted );
            
            $item->add_meta_data( '_raffle_ticket_numbers', $formatted );
            if ( ! empty( $instant_wins ) ) {
                $item->add_meta_data( '_raffle_instant_wins', wp_json_encode( $instant_wins ) );
            }
            $item->save();

            // Send confirmation email per raffle
            Raffle_Email::send_purchase_confirmation( $purchase_id, $raffle, $tickets, $instant_wins );
        }

        // Mark as processed on the order
        $order->update_meta_data( '_raffle_tickets_generated', 'yes' );
        $order->update_meta_data( '_raffle_ticket_numbers', $all_tickets_formatted );
        $order->save();

        if ( ! empty( $all_tickets_formatted ) ) {
            $order->add_order_note( 'Raffle tickets generated: ' . implode( ', ', $all_tickets_formatted ) );
        }
        if ( ! empty( $all_instant_wins ) ) {
            $win_names = array_map( function( $w ) { return $w->prize_name; }, $all_instant_wins );
            $order->add_order_note( 'Instant wins awarded: ' . implode( ', ', $win_names ) );
        }
    }

    /**
     * Show raffle tickets on the WooCommerce thank-you page.
     */
    public function thankyou_raffle_tickets( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order || $order->get_meta( '_has_raffle_items' ) !== 'yes' ) {
            return;
        }

        $tickets = $order->get_meta( '_raffle_ticket_numbers' );

        if ( ! empty( $tickets ) && is_array( $tickets ) ) {
            echo '<div class="raffle-thankyou-tickets" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:30px;border-radius:16px;margin:20px 0;text-align:center;">';
            echo '<h2 style="color:#fff;margin:0 0 8px;">' . wpr_get_icon( 'star', 'wpr-icon--md wpr-icon--white', 'Tickets' ) . ' Your Raffle Tickets!</h2>';
            echo '<div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;">';
            foreach ( $tickets as $ticket ) {
                echo '<span style="background:rgba(255,255,255,0.2);padding:8px 16px;border-radius:8px;font-weight:700;font-size:18px;backdrop-filter:blur(4px);">' . esc_html( $ticket ) . '</span>';
            }
            echo '</div>';

            // Show instant wins
            $has_wins = false;
            foreach ( $order->get_items() as $item ) {
                $iw_meta = $item->get_meta('_raffle_instant_wins');
                if ( $iw_meta ) {
                    $wins = json_decode( $iw_meta );
                    if ( ! empty( $wins ) ) {
                        if ( ! $has_wins ) {
                            echo '<div style="margin-top:20px;padding:15px;background:rgba(255,215,0,0.2);border:2px dashed #ffd700;border-radius:12px;">';
                            echo '<h3 style="color:#fff;margin:0 0 10px;">' . wpr_get_icon( 'gift', 'wpr-icon--md wpr-icon--white', 'Instant Win' ) . ' YOU FOUND INSTANT WINS! ' . wpr_get_icon( 'gift', 'wpr-icon--md wpr-icon--white', 'Instant Win' ) . '</h3>';
                            $has_wins = true;
                        }
                        foreach( $wins as $w ) {
                            echo '<p style="margin:5px 0;"><strong>Ticket #' . esc_html( $w->ticket_number ) . '</strong> won: ' . esc_html( $w->prize_name ) . '</p>';
                        }
                    }
                }
            }
            if ( $has_wins ) {
                echo '</div>';
            }

            echo '<p style="opacity:0.8;margin:16px 0 0;font-size:14px;">📧 A confirmation email with your numbers was also sent.</p>';
            echo '</div>';
        } else {
            // Payment may still be processing
            $status = $order->get_status();
            if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
                echo '<div style="background:#fff3cd;color:#856404;padding:16px;border-radius:8px;margin:20px 0;">';
                echo '<p><strong>⏳ Your payment is being processed.</strong></p>';
                echo '<p>You will receive your tickets by email once the payment is confirmed.</p>';
                echo '</div>';
            }
        }
    }

    /**
     * Show raffle meta in WooCommerce admin order page.
     */
    public function admin_order_meta( $order ) {
        if ( $order->get_meta( '_has_raffle_items' ) !== 'yes' ) {
            return;
        }

        echo '<div class="order_data_column" style="border-left:2px solid #667eea;padding-left:12px;margin-top:12px;">';
        echo '<h3>Raffle Data</h3>';

        foreach ( $order->get_items() as $item ) {
            if ( $item->get_meta( '_is_raffle_order' ) === 'yes' ) {
                $raffle_id   = $item->get_meta( '_raffle_id' );
                $quantity    = $item->get_meta( '_raffle_quantity' );
                $purchase_id = $item->get_meta( '_raffle_purchase_id' );
                $tickets     = $item->get_meta( '_raffle_ticket_numbers' );
                $iw_meta     = $item->get_meta( '_raffle_instant_wins' );
                
                echo '<div style="margin-bottom:10px;padding:10px;background:#f9fafb;border-radius:4px;">';
                echo '<p style="margin:0 0 5px;"><strong>Raffle ID:</strong> ' . esc_html( $raffle_id ) . '</p>';
                echo '<p style="margin:0 0 5px;"><strong>Quantity:</strong> ' . esc_html( $quantity ) . ' tickets</p>';
                echo '<p style="margin:0 0 5px;"><strong>Purchase ID:</strong> ' . esc_html( $purchase_id ) . '</p>';

                if ( ! empty( $tickets ) && is_array( $tickets ) ) {
                    echo '<p style="margin:0 0 5px;"><strong>Tickets:</strong> ' . esc_html( implode( ', ', $tickets ) ) . '</p>';
                }

                if ( $iw_meta ) {
                    $wins = json_decode( $iw_meta );
                    if ( ! empty( $wins ) ) {
                        echo '<p style="margin:5px 0 0;color:#d97706;"><strong>Instant Wins:</strong> ';
                        $win_strings = array();
                        foreach( $wins as $w ) {
                            $win_strings[] = '#' . $w->ticket_number . ' (' . $w->prize_name . ')';
                        }
                        echo esc_html( implode( ', ', $win_strings ) ) . '</p>';
                    }
                }
                echo '</div>';
            }
        }
        echo '</div>';
    }

    /**
     * Shop loop: completely replace the product card for raffle products.
     * Removes all default WooCommerce loop hooks and renders our template instead.
     */
    public function override_raffle_shop_loop_item() {
        global $product;
        if ( ! $product ) return;

        $raffle_id = get_post_meta( $product->get_id(), '_raffle_id', true );
        if ( ! $raffle_id ) return; // Not a raffle product — let WooCommerce render normally.

        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            (int) $raffle_id
        ) );
        if ( ! $raffle ) return;

        // Remove ALL default WooCommerce loop output for THIS product
        remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
        remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
        remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
        remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
        remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
        remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
        remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
        remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

        // Render our competition card template
        include RAFFLE_SYSTEM_PATH . 'public/views/raffle-loop-card.php';

        // Re-add hooks for the NEXT product in the loop
        add_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
        add_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
        add_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
        add_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
        add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
        add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
        add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
        add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
    }

    /**
     * Add 'raffle-product-card' class to raffle product list items for CSS targeting.
     */
    public function add_raffle_product_class( $classes, $product ) {
        if ( get_post_meta( $product->get_id(), '_raffle_id', true ) ) {
            $classes[] = 'raffle-product-card';
        }
        return $classes;
    }

    /**
     * Add 'raffle-product-card' class using standard WordPress post_class filter.
     */
    public function add_raffle_post_class( $classes, $class, $post_id ) {
        if ( get_post_type( $post_id ) === 'product' && get_post_meta( $post_id, '_raffle_id', true ) ) {
            $classes[] = 'raffle-product-card';
        }
        return $classes;
    }

    /**
     * Shop loop: Change "Add to cart" text to "Enter Raffle" for raffle products.
     */
    public function custom_raffle_loop_button_text( $text, $product ) {
        if ( get_post_meta( $product->get_id(), '_raffle_id', true ) ) {
            return 'Enter Raffle';
        }
        return $text;
    }

    /**
     * Shop loop: Link raffle products to their product page instead of add-to-cart.
     */
    public function custom_raffle_loop_button_url( $url, $product ) {
        if ( get_post_meta( $product->get_id(), '_raffle_id', true ) ) {
            return get_permalink( $product->get_id() );
        }
        return $url;
    }

    /**
     * Enqueue public assets on shop/archive pages that display raffle products.
     */
    public function enqueue_shop_assets() {
        if ( is_shop() || is_product_taxonomy() ) {
            wp_enqueue_style( 'raffle-public', RAFFLE_SYSTEM_URL . 'assets/css/public.css', array(), RAFFLE_SYSTEM_VERSION );
            wp_enqueue_script( 'raffle-shop-countdown', RAFFLE_SYSTEM_URL . 'assets/js/shop-countdown.js', array( 'jquery' ), RAFFLE_SYSTEM_VERSION, true );
        }
    }

    public function exclude_non_live_raffles_from_shop( $q ) {
        if ( is_admin() ) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'raffles';
        $now = current_time( 'mysql' );

        // Get IDs of raffles that are NOT live (either draft or ended/finished)
        $non_live_wc_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT wc_product_id FROM {$table} 
             WHERE wc_product_id IS NOT NULL 
               AND (
                    status = 'draft' 
                    OR status = 'finished'
                    OR ( status = 'active' AND start_date IS NOT NULL AND start_date > %s )
                    OR ( status = 'active' AND draw_date IS NOT NULL AND draw_date <= %s )
                    OR ( sold_tickets >= total_tickets )
               )",
            $now, $now
        ) );

        if ( ! empty( $non_live_wc_ids ) ) {
            $post__not_in = $q->get( 'post__not_in' );
            if ( ! is_array( $post__not_in ) ) {
                $post__not_in = array();
            }
            $q->set( 'post__not_in', array_merge( $post__not_in, array_map( 'intval', $non_live_wc_ids ) ) );
        }
    }

    /**
     * Lock cart quantity input for raffle items — prevents users from changing quantity in cart.
     */
    public function lock_cart_quantity( $product_quantity, $cart_item_key, $cart_item ) {
        if ( isset( $cart_item['raffle_id'] ) ) {
            $qty = isset( $cart_item['raffle_quantity'] ) ? $cart_item['raffle_quantity'] : $cart_item['quantity'];
            return '<span style="font-weight:700;font-size:15px;">' . esc_html( $qty ) . '</span>
                    <input type="hidden" name="cart[' . esc_attr( $cart_item_key ) . '][qty]" value="' . esc_attr( $qty ) . '">';
        }
        return $product_quantity;
    }

    /**
     * Enforce quantity limits when cart totals are calculated.
     * Forces raffle items back to their original raffle_quantity if tampered with.
     */
    public function enforce_cart_quantity_limits( $cart_obj ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        foreach ( $cart_obj->get_cart() as $key => $value ) {
            if ( ! isset( $value['raffle_id'] ) ) {
                continue;
            }

            $original_qty = isset( $value['raffle_quantity'] ) ? (int) $value['raffle_quantity'] : 0;
            $current_qty  = (int) $value['quantity'];

            // If the WC cart quantity was tampered with, force it back
            if ( $original_qty > 0 && $current_qty !== $original_qty ) {
                WC()->cart->set_quantity( $key, $original_qty, false );
            }

            // Re-verify against raffle limits from the database
            global $wpdb;
            $raffle = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
                (int) $value['raffle_id']
            ) );

            if ( ! $raffle ) {
                WC()->cart->remove_cart_item( $key );
                wc_add_notice( 'This raffle no longer exists and has been removed from your cart.', 'error' );
                continue;
            }

            $max_tickets = (int) $raffle->max_tickets_per_user;
            $available   = (int) $raffle->total_tickets - (int) $raffle->sold_tickets;
            $buyer_email = $value['buyer_email'] ?? '';

            // Check cumulative purchases by this email
            if ( ! empty( $buyer_email ) ) {
                $existing = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(quantity), 0) FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d AND buyer_email = %s AND payment_status IN ('completed','pending')",
                    (int) $value['raffle_id'], $buyer_email
                ) );
                if ( ( $existing + $original_qty ) > $max_tickets ) {
                    WC()->cart->remove_cart_item( $key );
                    wc_add_notice( sprintf( 'You have exceeded the maximum of %d tickets for this raffle.', $max_tickets ), 'error' );
                    continue;
                }
            }

            if ( $original_qty > $max_tickets ) {
                WC()->cart->remove_cart_item( $key );
                wc_add_notice( sprintf( 'Maximum %d tickets per user for this raffle.', $max_tickets ), 'error' );
                continue;
            }

            if ( $original_qty > $available ) {
                WC()->cart->remove_cart_item( $key );
                wc_add_notice( 'Not enough tickets remaining. The item has been removed from your cart.', 'error' );
                continue;
            }

            // Check raffle is still live
            if ( Raffle_Public::get_raffle_state( $raffle ) !== 'live' ) {
                WC()->cart->remove_cart_item( $key );
                wc_add_notice( 'This raffle is no longer active and has been removed from your cart.', 'error' );
                continue;
            }
        }
    }

    /**
     * Verify raffle item subtotals match expected price (quantity × ticket_price).
     */
    public function enforce_cart_item_subtotal( $subtotal, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['raffle_id'] ) ) {
            global $wpdb;
            $raffle = $wpdb->get_row( $wpdb->prepare(
                "SELECT ticket_price FROM {$wpdb->prefix}raffles WHERE id = %d",
                (int) $cart_item['raffle_id']
            ) );
            if ( $raffle ) {
                $qty = isset( $cart_item['raffle_quantity'] ) ? (int) $cart_item['raffle_quantity'] : (int) $cart_item['quantity'];
                $expected = $qty * (float) $raffle->ticket_price;
                $actual   = (float) $cart_item['data']->get_price() * (int) $cart_item['quantity'];
                if ( abs( $expected - $actual ) > 0.01 ) {
                    // Force the correct price
                    $cart_item['data']->set_price( $expected );
                    return wc_price( $expected );
                }
            }
        }
        return $subtotal;
    }

    /**
     * Final validation at checkout — re-check all raffle quantity limits.
     */
    public function validate_checkout_quantities() {
        if ( ! self::is_available() ) {
            return;
        }

        foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
            if ( ! isset( $cart_item['raffle_id'] ) ) {
                continue;
            }

            global $wpdb;
            $raffle_id = (int) $cart_item['raffle_id'];
            $qty       = isset( $cart_item['raffle_quantity'] ) ? (int) $cart_item['raffle_quantity'] : (int) $cart_item['quantity'];

            $raffle = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
                $raffle_id
            ) );

            if ( ! $raffle ) {
                wc_add_notice( 'A raffle in your cart no longer exists. Please remove it.', 'error' );
                continue;
            }

            // Check raffle is still live
            if ( Raffle_Public::get_raffle_state( $raffle ) !== 'live' ) {
                wc_add_notice( sprintf( '"%s" is no longer active.', $raffle->title ), 'error' );
                continue;
            }

            // SEC-6 FIX: Enforce geo restrictions in checkout process
            if ( class_exists( 'Raffle_Geo' ) && ! Raffle_Geo::check_eligibility( $raffle ) ) {
                wc_add_notice( sprintf( '"%s" is not available in your region.', $raffle->title ), 'error' );
                continue;
            }

            // Check max tickets per user
            $max_tickets = (int) $raffle->max_tickets_per_user;
            if ( $qty > $max_tickets ) {
                wc_add_notice( sprintf( 'You can purchase a maximum of %d tickets for "%s".', $max_tickets, $raffle->title ), 'error' );
                continue;
            }

            // Check availability
            $available = (int) $raffle->total_tickets - (int) $raffle->sold_tickets;
            if ( $qty > $available ) {
                wc_add_notice( sprintf( 'Only %d tickets remaining for "%s".', $available, $raffle->title ), 'error' );
                continue;
            }

            // SEC-5 FIX: Validate selected_numbers server-side
            if ( ! empty( $cart_item['selected_numbers'] ) ) {
                $validation = Raffle_Tickets::validate_selected_numbers( $raffle_id, $cart_item['selected_numbers'], $qty );
                if ( is_wp_error( $validation ) ) {
                    wc_add_notice( sprintf( 'For "%s": %s', $raffle->title, $validation->get_error_message() ), 'error' );
                    continue;
                }
            }

            // Cumulative email check
            $buyer_email = $cart_item['buyer_email'] ?? '';
            $billing_email = '';
            if ( isset( $_POST['billing_email'] ) ) {
                $billing_email = sanitize_email( wp_unslash( $_POST['billing_email'] ) );
            }
            $check_email = $buyer_email ?: $billing_email;
            if ( ! empty( $check_email ) ) {
                $existing = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(quantity), 0) FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d AND buyer_email = %s AND payment_status IN ('completed','pending')",
                    $raffle_id, $check_email
                ) );
                if ( ( $existing + $qty ) > $max_tickets ) {
                    $remaining = max( 0, $max_tickets - $existing );
                    wc_add_notice( sprintf( 'You can only purchase %d more ticket(s) for "%s" (limit: %d per user).', $remaining, $raffle->title, $max_tickets ), 'error' );
                }
            }
        }
    }

    /**
     * Add "Raffle Product" to the WooCommerce product type selector.
     */
    public function add_raffle_product_type( $types ) {
        $types['raffle'] = __( 'Raffle Product', 'raffle-system' );
        return $types;
    }

    /**
     * Map the product class for "raffle" product type.
     */
    public function raffle_product_class( $classname, $product_type ) {
        if ( 'raffle' === $product_type ) {
            $classname = 'WC_Product_Raffle';
        }
        return $classname;
    }

    /**
     * Configure WooCommerce tabs for the raffle product type.
     */
    public function configure_raffle_product_tabs( $tabs ) {
        // Hide shipping, attributes, linked products, advanced for raffle
        if ( isset( $tabs['shipping'] ) ) {
            $tabs['shipping']['class'][] = 'hide_if_raffle';
        }
        if ( isset( $tabs['attribute'] ) ) {
            $tabs['attribute']['class'][] = 'hide_if_raffle';
        }
        if ( isset( $tabs['linked_product'] ) ) {
            $tabs['linked_product']['class'][] = 'hide_if_raffle';
        }
        if ( isset( $tabs['advanced'] ) ) {
            $tabs['advanced']['class'][] = 'hide_if_raffle';
        }

        // Add custom raffle settings tab
        $tabs['raffle_settings'] = array(
            'label'    => __( 'Raffle Settings', 'raffle-system' ),
            'target'   => 'raffle_product_data',
            'class'    => array( 'show_if_raffle' ),
            'priority' => 21,
        );

        return $tabs;
    }

    /**
     * Display configuration panels inside the Raffle Settings tab.
     */
    public function raffle_product_data_panels() {
        global $post, $wpdb;

        // Load current raffle settings if associated
        $raffle_id = get_post_meta( $post->ID, '_raffle_id', true );
        $raffle = null;
        if ( $raffle_id ) {
            $raffle = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
                (int) $raffle_id
            ) );
        }

        // Set default values if new
        $ticket_price = $raffle ? $raffle->ticket_price : '';
        $total_tickets = $raffle ? $raffle->total_tickets : '';
        $max_tickets_per_user = $raffle ? $raffle->max_tickets_per_user : 100;
        $start_date = $raffle ? $raffle->start_date : '';
        $draw_date = $raffle ? $raffle->draw_date : '';
        $cash_alternative = $raffle ? $raffle->enable_cash_alternative : 0;
        $cash_alternative_amount = $raffle ? $raffle->cash_alternative_amount : '';
        $enable_question = $raffle ? $raffle->enable_question : 0;
        $question_text = $raffle ? $raffle->question_text : '';

        $answers = array( '', '', '' );
        if ( $raffle && ! empty( $raffle->question_answers ) ) {
            $decoded = json_decode( $raffle->question_answers, true );
            if ( is_array( $decoded ) ) {
                $answers = array_pad( $decoded, 3, '' );
            }
        }
        $correct_answer_index = $raffle ? $raffle->correct_answer_index : 0;
        $postal_instructions = $raffle ? $raffle->postal_instructions : "To enter by post, send a postcard with your full name, email, phone number, and selected option to our office address.";
        $status = $raffle ? $raffle->status : 'active';
        ?>
        <div id="raffle_product_data" class="panel woocommerce_options_panel show_if_raffle" style="display: none;">
            <div class="options_group">
                <?php
                // Ticket Price
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_ticket_price',
                    'label'       => __( 'Ticket Price ($)', 'raffle-system' ),
                    'value'       => $ticket_price,
                    'placeholder' => 'e.g. 5.99',
                    'desc_tip'    => true,
                    'description' => __( 'Price per ticket.', 'raffle-system' ),
                    'type'        => 'text',
                ) );

                // Total Tickets
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_total_tickets',
                    'label'       => __( 'Total Tickets', 'raffle-system' ),
                    'value'       => $total_tickets,
                    'placeholder' => 'e.g. 1000',
                    'desc_tip'    => true,
                    'description' => __( 'Maximum number of tickets available.', 'raffle-system' ),
                    'type'        => 'number',
                    'custom_attributes' => array( 'min' => 1 ),
                ) );

                // Max Tickets Per User
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_max_tickets_per_user',
                    'label'       => __( 'Max Tickets Per User', 'raffle-system' ),
                    'value'       => $max_tickets_per_user,
                    'placeholder' => 'e.g. 50',
                    'desc_tip'    => true,
                    'description' => __( 'Maximum tickets a single user can buy.', 'raffle-system' ),
                    'type'        => 'number',
                    'custom_attributes' => array( 'min' => 1 ),
                ) );

                // Start Date
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_start_date',
                    'label'       => __( 'Start Date & Time', 'raffle-system' ),
                    'value'       => ! empty( $start_date ) ? str_replace( ' ', 'T', $start_date ) : '',
                    'type'         => 'datetime-local',
                    'desc_tip'    => true,
                    'description' => __( 'Date and time the raffle starts/goes live.', 'raffle-system' ),
                ) );

                // Draw Date
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_draw_date',
                    'label'       => __( 'Draw Date & Time', 'raffle-system' ),
                    'value'       => ! empty( $draw_date ) ? str_replace( ' ', 'T', $draw_date ) : '',
                    'type'         => 'datetime-local',
                    'desc_tip'    => true,
                    'description' => __( 'Date and time of the draw.', 'raffle-system' ),
                ) );

                // Status dropdown
                woocommerce_wp_select( array(
                    'id'          => '_raffle_status',
                    'label'       => __( 'Raffle Status', 'raffle-system' ),
                    'value'       => $status,
                    'options'     => array(
                        'draft'    => __( 'Draft', 'raffle-system' ),
                        'active'   => __( 'Active', 'raffle-system' ),
                        'finished' => __( 'Finished', 'raffle-system' ),
                    ),
                ) );
                ?>
            </div>

            <div class="options_group">
                <?php
                // Cash Alternative Toggle
                woocommerce_wp_checkbox( array(
                    'id'            => '_raffle_cash_alternative',
                    'label'         => __( 'Enable Cash Alternative', 'raffle-system' ),
                    'value'         => $cash_alternative ? 'yes' : 'no',
                    'cbvalue'       => 'yes',
                    'description'   => __( 'Toggle whether a cash alternative is offered.', 'raffle-system' ),
                ) );

                // Cash Alternative Amount
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_cash_alternative_amount',
                    'label'       => __( 'Cash Alternative Amount ($)', 'raffle-system' ),
                    'value'       => $cash_alternative_amount,
                    'placeholder' => 'e.g. 5000',
                    'desc_tip'    => true,
                    'description' => __( 'Cash prize amount if selected.', 'raffle-system' ),
                    'type'        => 'text',
                ) );
                ?>
            </div>

            <div class="options_group">
                <?php
                // Skill Question Toggle
                woocommerce_wp_checkbox( array(
                    'id'            => '_raffle_enable_question',
                    'label'         => __( 'Enable Skill Question (UK Compliance)', 'raffle-system' ),
                    'value'         => $enable_question ? 'yes' : 'no',
                    'cbvalue'       => 'yes',
                    'description'   => __( 'Toggle multiple choice question validation.', 'raffle-system' ),
                ) );

                // Question Text
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_question_text',
                    'label'       => __( 'Question Text', 'raffle-system' ),
                    'value'       => $question_text,
                    'placeholder' => 'e.g. What is the capital of the UK?',
                    'type'        => 'text',
                ) );

                // Option 1
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_question_answer_0',
                    'label'       => __( 'Option 1', 'raffle-system' ),
                    'value'       => $answers[0],
                    'type'        => 'text',
                ) );

                // Option 2
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_question_answer_1',
                    'label'       => __( 'Option 2', 'raffle-system' ),
                    'value'       => $answers[1],
                    'type'        => 'text',
                ) );

                // Option 3
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_question_answer_2',
                    'label'       => __( 'Option 3', 'raffle-system' ),
                    'value'       => $answers[2],
                    'type'        => 'text',
                ) );

                // Correct Answer index
                woocommerce_wp_select( array(
                    'id'          => '_raffle_correct_answer_index',
                    'label'       => __( 'Correct Option', 'raffle-system' ),
                    'value'       => $correct_answer_index,
                    'options'     => array(
                        '0' => __( 'Option 1', 'raffle-system' ),
                        '1' => __( 'Option 2', 'raffle-system' ),
                        '2' => __( 'Option 3', 'raffle-system' ),
                    ),
                ) );
                ?>
            </div>

            <div class="options_group">
                <?php
                // Postal instructions
                woocommerce_wp_textarea_input( array(
                    'id'          => '_raffle_postal_instructions',
                    'label'       => __( 'Postal Entry Instructions', 'raffle-system' ),
                    'value'       => $postal_instructions,
                    'placeholder' => 'Describe how postal entries should be submitted...',
                ) );
                ?>
            </div>

            <?php if ( $raffle_id ) : ?>
                <div class="options_group" style="padding: 12px 20px 20px;">
                    <h3><?php esc_html_e( 'Configure Instant Wins', 'raffle-system' ); ?></h3>
                    <div class="rs-instant-wins-config">
                        <div style="display:flex;gap:10px;margin-bottom:15px;align-items:flex-end;">
                            <div style="flex:1;">
                                <label style="display:block;margin-bottom:5px;"><?php esc_html_e( 'Prize Name', 'raffle-system' ); ?></label>
                                <input type="text" id="new-instant-prize-name" style="width:100%;" placeholder="e.g. £50 Cash">
                            </div>
                            <div style="width:150px;">
                                <label style="display:block;margin-bottom:5px;"><?php esc_html_e( 'Ticket Number', 'raffle-system' ); ?></label>
                                <input type="number" id="new-instant-ticket-number" style="width:100%;" placeholder="Random">
                            </div>
                            <button type="button" class="button button-primary" id="btn-add-instant-win" data-raffle-id="<?php echo esc_attr( $raffle_id ); ?>">
                                <?php esc_html_e( 'Add Prize', 'raffle-system' ); ?>
                            </button>
                        </div>

                        <table class="wp-list-table widefat fixed striped" id="table-instant-wins">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Ticket #', 'raffle-system' ); ?></th>
                                    <th><?php esc_html_e( 'Prize Name', 'raffle-system' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'raffle-system' ); ?></th>
                                    <th><?php esc_html_e( 'Winner', 'raffle-system' ); ?></th>
                                    <th style="width:70px;"><?php esc_html_e( 'Action', 'raffle-system' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $instant_wins = Raffle_Instant_Wins::get_instant_wins( $raffle_id );
                                if ( ! empty( $instant_wins ) ) :
                                    foreach ( $instant_wins as $win ) :
                                        ?>
                                        <tr id="row-iw-<?php echo esc_attr( $win->id ); ?>">
                                            <td><?php echo esc_html( str_pad( $win->ticket_number, strlen( (string) $total_tickets ), '0', STR_PAD_LEFT ) ); ?></td>
                                            <td><?php echo esc_html( $win->prize_name ); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $win->status === 'won' ? 'won' : 'available'; ?>" style="padding:3px 8px;border-radius:4px;font-size:11px;font-weight:bold;background:<?php echo $win->status === 'won' ? '#fecaca;color:#991b1b;' : '#d1fae5;color:#065f46;'; ?>">
                                                    <?php echo esc_html( ucfirst( $win->status ) ); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html( $win->winner_email ? $win->winner_email : '-' ); ?></td>
                                            <td>
                                                <button type="button" class="button button-link delete-instant-win text-danger" data-id="<?php echo esc_attr( $win->id ); ?>" style="color:#b91c1c;">
                                                    <?php esc_html_e( 'Delete', 'raffle-system' ); ?>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php
                                    endforeach;
                                else :
                                    ?>
                                    <tr class="no-items"><td colspan="5" align="center"><?php esc_html_e( 'No instant wins configured.', 'raffle-system' ); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Show/hide sub-fields dynamically
                    function toggleRaffleFields() {
                        var cashEnabled = $('#_raffle_cash_alternative').is(':checked');
                        if (cashEnabled) {
                            $('#_raffle_cash_alternative_amount').closest('.form-field').show();
                        } else {
                            $('#_raffle_cash_alternative_amount').closest('.form-field').hide();
                        }

                        var questionEnabled = $('#_raffle_enable_question').is(':checked');
                        if (questionEnabled) {
                            $('#_raffle_question_text').closest('.form-field').show();
                            $('#_raffle_question_answer_0').closest('.form-field').show();
                            $('#_raffle_question_answer_1').closest('.form-field').show();
                            $('#_raffle_question_answer_2').closest('.form-field').show();
                            $('#_raffle_correct_answer_index').closest('.form-field').show();
                        } else {
                            $('#_raffle_question_text').closest('.form-field').hide();
                            $('#_raffle_question_answer_0').closest('.form-field').hide();
                            $('#_raffle_question_answer_1').closest('.form-field').hide();
                            $('#_raffle_question_answer_2').closest('.form-field').hide();
                            $('#_raffle_correct_answer_index').closest('.form-field').hide();
                        }
                    }

                    $('#_raffle_cash_alternative, #_raffle_enable_question').on('change', toggleRaffleFields);
                    toggleRaffleFields();

                    // Make sure general price options and other standard tabs are adjusted when product type is changed
                    $('body').on('woocommerce-product-type-change', function(e, select_val) {
                        if (select_val === 'raffle') {
                            $('.show_if_raffle').show();
                            $('.hide_if_raffle').hide();
                            // set virtual and sold individually automatically for raffle
                            $('#_virtual').prop('checked', true).trigger('change');
                        } else {
                            $('.show_if_raffle').hide();
                        }
                    });

                    // Trigger type change check initially
                    if ( $('#product-type').val() === 'raffle' ) {
                        $('.show_if_raffle').show();
                        $('.hide_if_raffle').hide();
                    }

                    // Instant wins AJAX handlers (similar to admin.js)
                    $('#btn-add-instant-win').on('click', function(e) {
                        e.preventDefault();
                        var btn = $(this);
                        var raffleId = btn.data('raffle-id');
                        var prizeName = $('#new-instant-prize-name').val();
                        var ticketNumber = $('#new-instant-ticket-number').val();

                        if (!prizeName) {
                            alert('Please enter a prize name.');
                            return;
                        }

                        btn.prop('disabled', true);

                        $.post(ajaxurl, {
                            action: 'raffle_add_instant_win',
                            nonce: '<?php echo esc_js( wp_create_nonce( "raffle_draw_nonce" ) ); ?>',
                            raffle_id: raffleId,
                            prize_name: prizeName,
                            ticket_number: ticketNumber
                        }, function(response) {
                            btn.prop('disabled', false);
                            if (response.success) {
                                $('#new-instant-prize-name').val('');
                                $('#new-instant-ticket-number').val('');
                                alert(response.data.message);
                                window.location.reload();
                            } else {
                                alert(response.data.message);
                            }
                        });
                    });

                    $(document).on('click', '.delete-instant-win', function(e) {
                        e.preventDefault();
                        if (!confirm('Are you sure you want to delete this instant win prize?')) {
                            return;
                        }
                        var btn = $(this);
                        var id = btn.data('id');

                        $.post(ajaxurl, {
                            action: 'raffle_delete_instant_win',
                            nonce: '<?php echo esc_js( wp_create_nonce( "raffle_draw_nonce" ) ); ?>',
                            id: id
                        }, function(response) {
                            if (response.success) {
                                $('#row-iw-' + id).fadeOut(function() { $(this).remove(); });
                            } else {
                                alert(response.data.message);
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }

    /**
     * Save product meta and synchronize data to wp_raffles table.
     */
    public function save_raffle_product_data( $product_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'raffles';

        // Check if the product type is raffle
        $product_type = isset( $_POST['product-type'] ) ? sanitize_text_field( wp_unslash( $_POST['product-type'] ) ) : '';
        if ( 'raffle' !== $product_type ) {
            return;
        }

        // Sanitize and fetch settings
        $ticket_price = isset( $_POST['_raffle_ticket_price'] ) ? floatval( wp_unslash( $_POST['_raffle_ticket_price'] ) ) : 0.0;
        $total_tickets = isset( $_POST['_raffle_total_tickets'] ) ? absint( wp_unslash( $_POST['_raffle_total_tickets'] ) ) : 0;
        $max_tickets_per_user = isset( $_POST['_raffle_max_tickets_per_user'] ) ? absint( wp_unslash( $_POST['_raffle_max_tickets_per_user'] ) ) : 100;
        
        $start_date = isset( $_POST['_raffle_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_raffle_start_date'] ) ) : '';
        $start_date = str_replace( 'T', ' ', $start_date ); // Clean datetime-local format
        if ( empty( $start_date ) ) {
            $start_date = null;
        } else {
            if ( strlen( $start_date ) === 16 ) { // YYYY-MM-DD HH:MM
                $start_date .= ':00';
            }
        }

        $draw_date = isset( $_POST['_raffle_draw_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_raffle_draw_date'] ) ) : '';
        $draw_date = str_replace( 'T', ' ', $draw_date ); // Clean datetime-local format
        if ( empty( $draw_date ) ) {
            $draw_date = null;
        } else {
            if ( strlen( $draw_date ) === 16 ) { // YYYY-MM-DD HH:MM
                $draw_date .= ':00';
            }
        }

        $status = isset( $_POST['_raffle_status'] ) ? sanitize_text_field( wp_unslash( $_POST['_raffle_status'] ) ) : 'active';
        $cash_alternative = isset( $_POST['_raffle_cash_alternative'] ) ? 1 : 0;
        $cash_alternative_amount = isset( $_POST['_raffle_cash_alternative_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['_raffle_cash_alternative_amount'] ) ) : '';

        $enable_question = isset( $_POST['_raffle_enable_question'] ) ? 1 : 0;
        $question_text = isset( $_POST['_raffle_question_text'] ) ? sanitize_text_field( wp_unslash( $_POST['_raffle_question_text'] ) ) : '';

        $answers = array(
            sanitize_text_field( wp_unslash( $_POST['_raffle_question_answer_0'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['_raffle_question_answer_1'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['_raffle_question_answer_2'] ?? '' ) ),
        );
        $question_answers = wp_json_encode( $answers );
        $correct_answer_index = isset( $_POST['_raffle_correct_answer_index'] ) ? absint( wp_unslash( $_POST['_raffle_correct_answer_index'] ) ) : 0;
        $postal_instructions = isset( $_POST['_raffle_postal_instructions'] ) ? wp_kses_post( wp_unslash( $_POST['_raffle_postal_instructions'] ) ) : '';

        // Sync WooCommerce product post_status to draft if status is draft to match native WC behavior
        $wc_status = $status === 'draft' ? 'draft' : 'publish';
        if ( get_post_status( $product_id ) !== $wc_status ) {
            $wpdb->update( $wpdb->posts, array( 'post_status' => $wc_status ), array( 'ID' => $product_id ) );
            clean_post_cache( $product_id );
        }

        // Update product meta for standard WC compatibility
        update_post_meta( $product_id, '_regular_price', $ticket_price );
        update_post_meta( $product_id, '_price', $ticket_price );
        update_post_meta( $product_id, '_virtual', 'yes' );
        update_post_meta( $product_id, '_sold_individually', 'no' );
        update_post_meta( $product_id, '_stock_status', 'instock' );

        $raffle_id = get_post_meta( $product_id, '_raffle_id', true );

        if ( $raffle_id ) {
            // Guard: total_tickets cannot be less than already sold tickets
            $sold_tickets = $wpdb->get_var( $wpdb->prepare(
                "SELECT sold_tickets FROM {$table} WHERE id = %d",
                $raffle_id
            ) );
            if ( $sold_tickets && $total_tickets < $sold_tickets ) {
                $total_tickets = $sold_tickets;
            }

            // Guard: total_tickets cannot be less than the highest ticket number assigned to an instant win
            $max_iw_ticket = $wpdb->get_var( $wpdb->prepare(
                "SELECT MAX(ticket_number) FROM {$wpdb->prefix}raffle_instant_wins WHERE raffle_id = %d",
                $raffle_id
            ) );
            if ( $max_iw_ticket && $total_tickets < $max_iw_ticket ) {
                $total_tickets = $max_iw_ticket;
            }
        }

        $data = array(
            'title'                   => get_the_title( $product_id ),
            'description'             => get_post( $product_id )->post_content,
            'prize'                   => get_the_title( $product_id ),
            'image'                   => get_post_thumbnail_id( $product_id ) ? wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) : '',
            'total_tickets'           => $total_tickets,
            'ticket_price'            => $ticket_price,
            'max_tickets_per_user'    => $max_tickets_per_user,
            'start_date'              => $start_date,
            'draw_date'               => $draw_date,
            'status'                  => $status,
            'enable_cash_alternative' => $cash_alternative,
            'cash_alternative_amount' => $cash_alternative_amount,
            'enable_question'         => $enable_question,
            'question_text'           => $question_text,
            'question_answers'        => $question_answers,
            'correct_answer_index'    => $correct_answer_index,
            'postal_instructions'     => $postal_instructions,
            'wc_product_id'           => $product_id,
        );

        // Build formats dynamically
        $formats = array();
        foreach ( $data as $key => $val ) {
            if ( is_null( $val ) ) {
                $formats[] = '%s';
            } elseif ( is_int( $val ) || is_bool( $val ) ) {
                $formats[] = '%d';
            } elseif ( is_float( $val ) ) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        if ( $raffle_id ) {
            $row_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $raffle_id ) );
            if ( $row_exists ) {
                $wpdb->update( $table, $data, array( 'id' => $raffle_id ), $formats, array( '%d' ) );
            } else {
                $wpdb->insert( $table, $data, $formats );
                $raffle_id = $wpdb->insert_id;
                update_post_meta( $product_id, '_raffle_id', $raffle_id );
            }
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $formats[]          = '%s';
            $wpdb->insert( $table, $data, $formats );
            $raffle_id = $wpdb->insert_id;
            update_post_meta( $product_id, '_raffle_id', $raffle_id );
        }
    }
}

/**
 * Define WC_Product_Raffle class globally on plugins_loaded to extend WC_Product.
 */
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WC_Product' ) && ! class_exists( 'WC_Product_Raffle' ) ) {
        class WC_Product_Raffle extends WC_Product {
            public function get_type() {
                return 'raffle';
            }
        }
    }
} );

