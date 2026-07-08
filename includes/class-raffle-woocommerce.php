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

        // Revert allocated tickets + instant-win prizes when an order is
        // cancelled / refunded / failed. Previously there was no reversion
        // path: allocated tickets and won prizes persisted even after the
        // sale was undone. (Prereq A, 1.3.0.)
        add_action( 'woocommerce_order_status_cancelled', array( $this, 'on_order_cancelled' ) );
        add_action( 'woocommerce_order_status_failed', array( $this, 'on_order_failed' ) );
        add_action( 'woocommerce_order_fully_refunded', array( $this, 'on_order_refunded' ) );
        add_action( 'woocommerce_order_partially_refunded', array( $this, 'on_order_refunded' ) );
        add_action( 'woocommerce_order_status_refunded', array( $this, 'on_order_refunded' ) );

        // Custom thank-you page content for raffle orders
        add_action( 'woocommerce_thankyou', array( $this, 'thankyou_raffle_tickets' ) );

        // Show raffle info in admin order details
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_order_meta' ) );

        // Cart Integration Hooks
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_price' ), 20, 1 );
        // Show charity badge on single product page
        add_action( 'woocommerce_single_product_summary', array( $this, 'show_charity_badge' ), 15 );
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
        $new_items = array();
        foreach ( $items as $key => $label ) {
            // Insert My Raffles right after Dashboard, before Orders
            if ( 'dashboard' === $key ) {
                $new_items[ $key ] = $label;
                $new_items['my-raffles'] = 'My Raffles';
            } elseif ( ! isset( $new_items['my-raffles'] ) && 'orders' === $key ) {
                // If there's no dashboard (some setups), insert before orders
                $new_items['my-raffles'] = 'My Raffles';
                $new_items[ $key ] = $label;
            } else {
                $new_items[ $key ] = $label;
            }
        }
        // If we still haven't added it (no dashboard or orders key), prepend
        if ( ! isset( $new_items['my-raffles'] ) ) {
            $new_items = array_merge( array( 'my-raffles' => 'My Raffles' ), $new_items );
        }
        return $new_items;
    }

    public function my_raffles_endpoint_content() {
        // Output icon CSS so SVGs render correctly in all tabs.
        echo '<style>.wpr-icon{display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;vertical-align:middle;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;width:18px;height:18px}.wpr-icon--xs{width:12px;height:12px}.wpr-icon--sm{width:14px;height:14px}.wpr-icon--md{width:18px;height:18px}.wpr-icon--lg{width:22px;height:22px}.wpr-icon--xl{width:28px;height:28px}</style>';

        // Unified My Raffles page — now shows internal tabs for Tickets, Wins, RG, GDPR.
        $sub = isset( $_GET['sub'] ) ? sanitize_text_field( wp_unslash( $_GET['sub'] ) ) : '';
        $raffle_tabs = array(
            ''                   => 'Tickets',
            'wins'               => 'Wins',
            'my-coupons'         => 'My Coupons',
            'responsible-gambling' => 'Responsible Gambling',
            'data-privacy'       => 'Data & Privacy',
        );

        echo '<div class="wpraffle-account-tabs" style="margin-bottom:20px;border-bottom:2px solid var(--wpr-border-color);display:flex;gap:0;">';
        $base_url = wc_get_endpoint_url( 'my-raffles', '', wc_get_page_permalink( 'myaccount' ) );
        foreach ( $raffle_tabs as $key => $label ) {
            $active = ( $sub === $key ) ? 'border-bottom:3px solid var(--wpr-accent);color:var(--wpr-accent);font-weight:700;' : 'color:var(--wpr-text-muted);';
            $url = $key === '' ? $base_url : add_query_arg( 'sub', $key, $base_url );
            echo '<a href="' . esc_url( $url ) . '" style="padding:10px 16px;text-decoration:none;font-size:14px;' . esc_attr( $active ) . 'margin-bottom:-2px;">' . esc_html( $label ) . '</a>';
        }
        echo '</div>';

        switch ( $sub ) {
            case 'wins':
                include RAFFLE_SYSTEM_PATH . 'public/views/account/wins.php';
                break;
            case 'my-coupons':
                include RAFFLE_SYSTEM_PATH . 'public/views/account/my-coupons.php';
                break;
            case 'responsible-gambling':
                include RAFFLE_SYSTEM_PATH . 'public/views/account/responsible-gambling.php';
                break;
            case 'data-privacy':
                include RAFFLE_SYSTEM_PATH . 'public/views/account/gdpr.php';
                break;
            default:
                include RAFFLE_SYSTEM_PATH . 'public/views/account/tickets.php';
                break;
        }
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
        $bundle_price     = isset( $_POST['bundle_price'] ) ? (float) $_POST['bundle_price'] : 0.0;

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

        // Validate bundle price server-side (same pattern as ajax_add_to_cart).
        $bundle_unit_price = 0.0;
        if ( $bundle_price > 0 && function_exists( 'wpraffle_normalise_packages' ) ) {
            $configured = wpraffle_normalise_packages( $raffle->packages, (float) $raffle->ticket_price );
            foreach ( $configured as $b ) {
                if ( (int) $b['qty'] === $quantity && (float) $b['price'] > 0 ) {
                    if ( abs( (float) $b['price'] - $bundle_price ) < 0.01 ) {
                        $bundle_unit_price = (float) $b['price'] / $quantity;
                        break;
                    }
                }
            }
            if ( $bundle_unit_price <= 0 ) {
                wp_send_json_error( array( 'message' => 'This bundle is no longer available.' ) );
            }
        }

        $unit_price  = $bundle_unit_price > 0 ? $bundle_unit_price : (float) $raffle->ticket_price;
        $total_amount = $quantity * $unit_price;

        // Responsible-gambling gate: enforce self-exclusion, operator locks,
        // and spend limits (keyed on user_id for logged-in buyers, on email
        // for guests) before any cart entry is created.
        $rg = apply_filters( 'raffle_pre_purchase_check', true, get_current_user_id(), (float) $total_amount, $buyer_email );
        if ( is_wp_error( $rg ) ) {
            wp_send_json_error( array( 'message' => $rg->get_error_message() ) );
        }

        $product_id = (int) $raffle->wc_product_id;
        if ( ! $product_id || ! get_post( $product_id ) ) {
            $product_id = get_option( 'raffle_system_wc_product_id' );
        }
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => 'System error: Raffle product not configured.' ) );
        }

        $cart_item_data = array(
            'raffle_id'         => $raffle_id,
            'raffle_quantity'   => $quantity,
            'raffle_price'      => $total_amount,
            'raffle_unit_price' => $unit_price,
            'raffle_title'      => $raffle->title,
            'buyer_name'        => $buyer_name,
            'buyer_email'       => $buyer_email,
            'selected_numbers'  => $selected_numbers,
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
        $bundle_price     = isset( $_POST['bundle_price'] ) ? (float) $_POST['bundle_price'] : 0.0;

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

        // Responsible-gambling gate. buyer_email falls back to the logged-in
        // user's email (cart path has no POSTed email); guests are checked by
        // email once they provide one at checkout.
        $rg_user_id   = get_current_user_id();
        $rg_buyer_eml = $rg_user_id ? (string) wp_get_current_user()->user_email : '';
        $rg_amount    = $quantity * (float) $raffle->ticket_price;
        $rg = apply_filters( 'raffle_pre_purchase_check', true, $rg_user_id, $rg_amount, $rg_buyer_eml );
        if ( is_wp_error( $rg ) ) {
            wp_send_json_error( array( 'message' => $rg->get_error_message() ) );
        }

        $product_id = (int) $raffle->wc_product_id;
        if ( ! $product_id || ! get_post( $product_id ) ) {
            wp_send_json_error( array( 'message' => 'System error: WooCommerce product is not configured.' ) );
        }

        // Validate the bundle price server-side. The client may claim a
        // bundle_price (a fixed total for this quantity) but it MUST match a
        // bundle the operator configured for this raffle — otherwise reject
        // and fall back to standard pricing.
        $bundle_unit_price = 0.0;
        if ( $bundle_price > 0 && function_exists( 'wpraffle_normalise_packages' ) ) {
            $configured = wpraffle_normalise_packages( $raffle->packages, (float) $raffle->ticket_price );
            foreach ( $configured as $b ) {
                if ( (int) $b['qty'] === $quantity && (float) $b['price'] > 0 ) {
                    if ( abs( (float) $b['price'] - $bundle_price ) < 0.01 ) {
                        $bundle_unit_price = (float) $b['price'] / $quantity;
                        break;
                    }
                }
            }
            if ( $bundle_unit_price <= 0 ) {
                wp_send_json_error( array( 'message' => 'This bundle is no longer available.' ) );
            }
        }

        // Cart item data — include buyer_email (logged-in) and raffle_price
        // so the cumulative per-user limit + price fallback checks fire
        // correctly downstream (enforce_cart_quantity_limits / validate_checkout).
        $unit_price  = $bundle_unit_price > 0 ? $bundle_unit_price : (float) $raffle->ticket_price;
        $total_price = $quantity * $unit_price;
        $cart_item_data = array(
            'raffle_id'        => $raffle_id,
            'raffle_quantity'  => $quantity,
            'raffle_price'     => $total_price,
            'raffle_unit_price'=> $unit_price,
            'buyer_email'      => $rg_buyer_eml,
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
            if ( isset( $value['raffle_id'] ) ) {
                // SEC-A5 FIX: Always recalculate price from the database
                // instead of trusting the session-stored raffle_price value
                global $wpdb;
                $raffle = $wpdb->get_row( $wpdb->prepare(
                    "SELECT ticket_price FROM {$wpdb->prefix}raffles WHERE id = %d",
                    (int) $value['raffle_id']
                ) );
                if ( $raffle ) {
                    $qty = isset( $value['raffle_quantity'] ) ? (int) $value['raffle_quantity'] : 1;
                    // If this cart item came from a validated bundle, re-verify
                    // the bundle still exists and its price still matches the
                    // operator-configured value (defence-in-depth — the client
                    // can't tamper because we look it up from the DB again).
                    $unit_price = (float) $raffle->ticket_price;
                    if ( ! empty( $value['raffle_unit_price'] ) && function_exists( 'wpraffle_normalise_packages' ) ) {
                        $configured = wpraffle_normalise_packages( '', (float) $raffle->ticket_price );
                        // Re-fetch the full raffle row for packages JSON.
                        $full_raffle = $wpdb->get_row( $wpdb->prepare( "SELECT ticket_price, packages FROM {$wpdb->prefix}raffles WHERE id = %d", (int) $value['raffle_id'] ) );
                        if ( $full_raffle ) {
                            $configured = wpraffle_normalise_packages( $full_raffle->packages, (float) $full_raffle->ticket_price );
                            foreach ( $configured as $b ) {
                                if ( (int) $b['qty'] === $qty && (float) $b['price'] > 0 ) {
                                    $expected_unit = (float) $b['price'] / $qty;
                                    if ( abs( $expected_unit - (float) $value['raffle_unit_price'] ) < 0.01 ) {
                                        $unit_price = $expected_unit;
                                    }
                                    break;
                                }
                            }
                        }
                    }
                    $value['data']->set_price( $unit_price );
                } else {
                    // Raffle no longer exists — DO NOT honour the client-stored
                    // raffle_price (it lives in WC session data and can be
                    // tampered). Zero the price; enforce_cart_quantity_limits
                    // will remove the stale item from the cart.
                    $value['data']->set_price( 0 );
                }
                if ( isset( $value['raffle_title'] ) && isset( $value['raffle_quantity'] ) ) {
                    $value['data']->set_name( 'Raffle Tickets — ' . $value['raffle_title'] . ' (x' . $value['raffle_quantity'] . ')' );
                }
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
        // WooCommerce validates the checkout nonce before this hook fires.
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

            // Responsible-gambling gate (final defense-in-depth at payment
            // completion). If RG blocks this buyer, refund the line and skip
            // ticket generation rather than issuing tickets to an excluded /
            // locked / over-limit buyer.
            $rg_user_id = (int) $order->get_user_id();
            $rg_amount  = (float) $item->get_total();
            $rg = apply_filters( 'raffle_pre_purchase_check', true, $rg_user_id, $rg_amount, $buyer_email );
            if ( is_wp_error( $rg ) ) {
                $order->add_order_note( sprintf(
                    'RG: Blocked ticket generation for raffle #%d (%s). Refunding line.',
                    $raffle_id, $rg->get_error_code()
                ) );
                if ( class_exists( 'Raffle_Audit' ) ) {
                    Raffle_Audit::log( $raffle_id, 'rg_block_at_payment', array(
                        'order'   => $order_id,
                        'reason'  => $rg->get_error_code(),
                        'message' => $rg->get_error_message(),
                    ), 'system' );
                }
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

            // Assign the actual prize artefacts (coupons / gift products /
            // credit) for any wins, now that the transaction is committed.
            // Done after COMMIT because prize assignment may create a coupon,
            // add a gift line item, or write to the credits ledger — none of
            // which should be inside the ticket-allocation transaction.
            if ( ! empty( $instant_wins ) && class_exists( 'Raffle_Instant_Win_Prize_Types' ) ) {
                $rg_user = (int) $order->get_user_id();
                Raffle_Instant_Wins::assign_winning_prizes( $instant_wins, $order, array(
                    'user_id' => $rg_user,
                    'name'    => $buyer_name,
                    'email'   => $buyer_email,
                ) );
                // Standalone instant-win email (1.3.0) — separate from the
                // purchase confirmation, if enabled.
                if ( class_exists( 'Raffle_Email' ) ) {
                    $raffle_for_email = isset( $raffle ) ? $raffle : null;
                    if ( ! $raffle_for_email ) {
                        $raffle_for_email = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_raffles} WHERE id = %d", $raffle_id ) );
                    }
                    Raffle_Email::send_instant_win( $buyer_email, $buyer_name, $raffle_for_email, $instant_wins );
                }
            }

            // Virality: if a referral code was captured in the wpraffle_ref
            // cookie (set by the ?ref= query param), attribute the bonus to
            // the referrer now that this buyer has a genuine paid purchase.
            // track_referral re-verifies the paid-purchase gate server-side.
            if ( class_exists( 'Raffle_Referrals' ) && ! empty( $_COOKIE['wpraffle_ref'] ) ) {
                $ref_code = preg_replace( '/[^A-Za-z0-9\-]/', '', sanitize_text_field( wp_unslash( $_COOKIE['wpraffle_ref'] ) ) );
                if ( $ref_code && get_transient( 'wpraffle_ref_done_' . $raffle_id . '_' . md5( strtolower( $buyer_email ) ) ) === false ) {
                    $ref_result = Raffle_Referrals::track_referral( $raffle_id, $ref_code, $buyer_email );
                    // Mark done for 24h so we don't retry on every order item.
                    set_transient( 'wpraffle_ref_done_' . $raffle_id . '_' . md5( strtolower( $buyer_email ) ), 1, DAY_IN_SECONDS );
                    if ( class_exists( 'Raffle_Audit' ) ) {
                        Raffle_Audit::log( $raffle_id, 'referral_attributed', array(
                            'code'    => $ref_code,
                            'buyer'   => $buyer_email,
                            'outcome' => is_wp_error( $ref_result ) ? $ref_result->get_error_code() : 'ok',
                        ), 'system' );
                    }
                }
            }

            // Audit log for WC purchase
            if ( class_exists( 'Raffle_Audit' ) ) {
                Raffle_Audit::log( $raffle_id, 'purchase', "WooCommerce purchase: {$quantity} ticket(s) by {$buyer_email} ({$buyer_name}). Order #{$order_id}, Purchase #{$purchase_id}.", '' );
            }

            // 1.3.0 — Admin sale notification (gated by per-email toggle).
            if ( class_exists( 'Raffle_Email' ) ) {
                $raffle_for_admin = isset( $raffle ) ? $raffle : null;
                if ( ! $raffle_for_admin ) {
                    $raffle_for_admin = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_raffles} WHERE id = %d", $raffle_id ) );
                }
                Raffle_Email::send_admin_sale( $raffle_for_admin, $buyer_name, $quantity );
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

    /* ===================================================================
       Order reversion (cancel / refund / fail) — Prereq A, 1.3.0
       =================================================================== */

    /**
     * Revert allocated tickets and instant-win prizes when an order leaves the
     * paid state. Idempotent: guarded by the _raffle_tickets_generated flag so
     * it only runs for orders that actually had tickets allocated, and a
     * _raffle_tickets_reverted flag so a repeated status change is a no-op.
     *
     * @param int      $order_id
     * @param WC_Order $order    Optional preloaded order.
     */
    public function revert_order_items( $order_id, $order = null ) {
        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }
        if ( ! $order ) {
            return;
        }

        // Only orders that have raffle items AND were already processed.
        if ( $order->get_meta( '_has_raffle_items' ) !== 'yes' ) {
            return;
        }
        if ( $order->get_meta( '_raffle_tickets_generated' ) !== 'yes' ) {
            return;
        }
        // Idempotency: don't revert twice.
        if ( $order->get_meta( '_raffle_tickets_reverted' ) === 'yes' ) {
            return;
        }

        global $wpdb;
        $table_purchases = $wpdb->prefix . 'raffle_purchases';
        $table_tickets   = $wpdb->prefix . 'raffle_tickets';
        $table_raffles   = $wpdb->prefix . 'raffles';

        // Reverse instant-win prizes attached to this order first (deletes
        // generated coupons, removes gift line items, reverses credits).
        if ( class_exists( 'Raffle_Instant_Wins' ) ) {
            Raffle_Instant_Wins::reverse_for_order( $order_id );
        }

        // Find every purchase row created for this order and revert each one.
        $purchases = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, raffle_id, quantity FROM {$table_purchases} WHERE wc_order_id = %d",
            $order_id
        ) );

        foreach ( $purchases as $purchase ) {
            $qty         = (int) $purchase->quantity;
            $raffle_id   = (int) $purchase->raffle_id;
            $purchase_id = (int) $purchase->id;

            $wpdb->query( 'START TRANSACTION' );

            // Lock the raffle row so sold_tickets decrement is atomic.
            $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT sold_tickets FROM {$table_raffles} WHERE id = %d FOR UPDATE", $raffle_id ) );
            if ( ! $raffle ) {
                $wpdb->query( 'ROLLBACK' );
                continue;
            }

            // Delete the allocated tickets for this purchase.
            $wpdb->delete( $table_tickets, array( 'purchase_id' => $purchase_id ), array( '%d' ) );

            // Decrement sold_tickets, clamped at 0, and reopen the raffle if it
            // had been auto-closed by the sellout path.
            $new_sold = max( 0, (int) $raffle->sold_tickets - $qty );
            $wpdb->update(
                $table_raffles,
                array(
                    'sold_tickets' => $new_sold,
                    'status'       => 'active', // reopening; safe since draws set 'finished' explicitly.
                ),
                array( 'id' => $raffle_id ),
                array( '%d', '%s' ),
                array( '%d' )
            );

            // Delete the purchase row.
            $wpdb->delete( $table_purchases, array( 'id' => $purchase_id ), array( '%d' ) );

            $wpdb->query( 'COMMIT' );

            if ( function_exists( 'wpraffle_flush_raffle_cache' ) ) {
                wpraffle_flush_raffle_cache( $raffle_id );
            }
            if ( class_exists( 'Raffle_Audit' ) ) {
                Raffle_Audit::log( $raffle_id, 'tickets_reverted', array(
                    'order'      => $order_id,
                    'purchase'   => $purchase_id,
                    'quantity'   => $qty,
                ), 'system' );
            }
        }

        // Mark reverted so a subsequent status transition (e.g. refund after
        // cancel) is a no-op, and clear the generated flag so a re-payment
        // could re-issue tickets idempotently.
        $order->update_meta_data( '_raffle_tickets_reverted', 'yes' );
        $order->update_meta_data( '_raffle_tickets_generated', 'no' );
        $order->save();

        $order->add_order_note( __( 'Raffle tickets and instant-win prizes reverted for this order.', 'wpraffle' ) );
    }

    /**
     * Hooks for the individual status transitions — each delegates to the
     * shared revert_order_items() method.
     */
    public function on_order_cancelled( $order_id ) {
        $this->revert_order_items( $order_id );
    }

    public function on_order_failed( $order_id ) {
        $this->revert_order_items( $order_id );
    }

    public function on_order_refunded( $order_id, $refund_id = null ) {
        $this->revert_order_items( $order_id );
    }


    /**
     * Show raffle tickets on the WooCommerce thank-you page.
     *
     * Renders a polished, on-brand ticket summary that matches the plugin's
     * confirmation modal + card design language (semantic classes in
     * public.css, the --wpr-* token system, SVG icons — no emojis, no
     * hardcoded gradients).
     */
    public function thankyou_raffle_tickets( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order || $order->get_meta( '_has_raffle_items' ) !== 'yes' ) {
            return;
        }

        $tickets = $order->get_meta( '_raffle_ticket_numbers' );

        if ( ! empty( $tickets ) && is_array( $tickets ) ) {
            $ticket_count = count( $tickets );
            ?>
            <section class="raffle-thankyou">
                <div class="raffle-thankyou__header">
                    <span class="raffle-thankyou__icon"><?php wpr_icon( 'ticket', 'wpr-icon--2xl wpr-icon--white', 'Tickets' ); ?></span>
                    <h2 class="raffle-thankyou__title"><?php esc_html_e( 'Your Raffle Tickets', 'wpraffle' ); ?></h2>
                    <p class="raffle-thankyou__count">
                        <?php
                        printf(
                            /* translators: number of tickets */
                            esc_html( _n( '%d ticket secured', '%d tickets secured', $ticket_count, 'wpraffle' ) ),
                            (int) $ticket_count
                        );
                        ?>
                    </p>
                </div>

                <div class="raffle-thankyou__tickets">
                    <?php foreach ( $tickets as $ticket ) : ?>
                        <span class="raffle-thankyou__ticket"><?php echo esc_html( $ticket ); ?></span>
                    <?php endforeach; ?>
                </div>

                <?php
                // Show instant wins (if any) in a distinct, celebratory card.
                $wins_html     = '';
                $has_wins_html = false;
                foreach ( $order->get_items() as $item ) {
                    $iw_meta = $item->get_meta( '_raffle_instant_wins' );
                    if ( ! $iw_meta ) {
                        continue;
                    }
                    $wins = json_decode( $iw_meta );
                    if ( empty( $wins ) ) {
                        continue;
                    }
                    if ( ! $has_wins_html ) {
                        $wins_html .= '<div class="raffle-thankyou__wins">';
                        $wins_html .= '<div class="raffle-thankyou__wins-header">';
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpr_get_icon returns internally-escaped SVG; appended to a buffer echoed with a phpcs:ignore below.
                        $wins_html .= wpr_get_icon( 'gift', 'wpr-icon--md', 'Instant Win' );
                        $wins_html .= '<h3>' . esc_html__( 'You found instant wins!', 'wpraffle' ) . '</h3>';
                        $wins_html .= '</div><ul class="raffle-thankyou__wins-list">';
                        $has_wins_html = true;
                    }
                    foreach ( $wins as $w ) {
                        $wins_html .= '<li>';
                        $wins_html .= '<span class="raffle-thankyou__wins-ticket">#' . esc_html( $w->ticket_number ) . '</span>';
                        $wins_html .= '<span class="raffle-thankyou__wins-prize">' . esc_html( $w->prize_name ) . '</span>';
                        $wins_html .= '</li>';
                    }
                }
                if ( $has_wins_html ) {
                    $wins_html .= '</ul></div>';
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped fragments above.
                    echo $wins_html;
                }
                ?>

                <p class="raffle-thankyou__footer">
                    <?php wpr_icon( 'mail', 'wpr-icon--xs' ); ?>
                    <?php esc_html_e( 'A confirmation email with your numbers has also been sent.', 'wpraffle' ); ?>
                </p>
            </section>
            <?php
        } else {
            // Payment may still be processing — tickets not yet allocated.
            $status = $order->get_status();
            if ( in_array( $status, array( 'pending', 'on-hold' ), true ) ) {
                ?>
                <section class="raffle-thankyou raffle-thankyou--pending">
                    <span class="raffle-thankyou__pending-icon"><?php wpr_icon( 'clock', 'wpr-icon--lg', 'Processing' ); ?></span>
                    <h2 class="raffle-thankyou__pending-title"><?php esc_html_e( 'Payment processing', 'wpraffle' ); ?></h2>
                    <p class="raffle-thankyou__pending-text"><?php esc_html_e( 'Your payment is being processed. You will receive your ticket numbers by email once it is confirmed.', 'wpraffle' ); ?></p>
                </section>
                <?php
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

        // Use the cached reader (1 DB hit per raffle per request across all loops).
        $raffle = function_exists( 'wpraffle_get_raffle' )
            ? wpraffle_get_raffle( (int) $raffle_id )
            : $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( "SELECT * FROM {$GLOBALS['wpdb']->prefix}raffles WHERE id = %d", (int) $raffle_id ) );
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

        // No batching is possible in the per-product WC loop, so let the card
        // run its own single indexed IW-count query. Unset any stale value from
        // the previous product in the loop.
        unset( $iw_count );

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
     * Enqueue public assets on shop/archive pages that display raffle products,
     * and on the order-received (thank-you) page so the ticket summary renders
     * with the plugin's own stylesheet.
     */
    public function enqueue_shop_assets() {
        if ( is_shop() || is_product_taxonomy() ) {
            wp_enqueue_style( 'raffle-public', RAFFLE_SYSTEM_URL . 'assets/css/public.css', array( 'wpraffle-icons' ), RAFFLE_SYSTEM_VERSION );
            wp_enqueue_script( 'raffle-shop-countdown', RAFFLE_SYSTEM_URL . 'assets/js/shop-countdown.js', array( 'jquery' ), RAFFLE_SYSTEM_VERSION, true );
        }

        // Order-received / thank-you page: load the icon + public stylesheets
        // so the raffle ticket summary renders with the plugin's design tokens.
        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
            wp_enqueue_style( 'wpraffle-icons', RAFFLE_SYSTEM_URL . 'assets/css/icons.css', array(), RAFFLE_SYSTEM_VERSION );
            wp_enqueue_style( 'raffle-public', RAFFLE_SYSTEM_URL . 'assets/css/public.css', array( 'wpraffle-icons' ), RAFFLE_SYSTEM_VERSION );
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
                    $cart_item['data']->set_price( (float) $raffle->ticket_price );
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
        // WooCommerce validates its own checkout nonce before this hook fires.
        if ( ! isset( $_POST['woocommerce-process-checkout-nonce'] ) && ! isset( $_POST['_wpnonce'] ) ) {
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

            // Responsible-gambling gate at checkout (final pre-payment check).
            // user_id from the order/customer; email from cart item or the
            // POSTed billing email so guests are protected too.
            $rg_user_id = get_current_user_id();
            $rg_email   = $check_email;
            $rg_amount  = $qty * (float) $raffle->ticket_price;
            $rg = apply_filters( 'raffle_pre_purchase_check', true, $rg_user_id, $rg_amount, $rg_email );
            if ( is_wp_error( $rg ) ) {
                wc_add_notice( sprintf( 'For "%s": %s', $raffle->title, $rg->get_error_message() ), 'error' );
            }
        }
    }

    /**
     * Add "Raffle Product" to the WooCommerce product type selector.
     */
    public function add_raffle_product_type( $types ) {
        $types['raffle'] = __( 'Raffle Product', 'wpraffle' );
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
            'label'    => __( 'Raffle Settings', 'wpraffle' ),
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
                    'label'       => __( 'Ticket Price ($)', 'wpraffle' ),
                    'value'       => $ticket_price,
                    'placeholder' => 'e.g. 5.99',
                    'desc_tip'    => true,
                    'description' => __( 'Price per ticket.', 'wpraffle' ),
                    'type'        => 'text',
                ) );

                // Total Tickets
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_total_tickets',
                    'label'       => __( 'Total Tickets', 'wpraffle' ),
                    'value'       => $total_tickets,
                    'placeholder' => 'e.g. 1000',
                    'desc_tip'    => true,
                    'description' => __( 'Maximum number of tickets available.', 'wpraffle' ),
                    'type'        => 'number',
                    'custom_attributes' => array( 'min' => 1 ),
                ) );

                // Max Tickets Per User
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_max_tickets_per_user',
                    'label'       => __( 'Max Tickets Per User', 'wpraffle' ),
                    'value'       => $max_tickets_per_user,
                    'placeholder' => 'e.g. 50',
                    'desc_tip'    => true,
                    'description' => __( 'Maximum tickets a single user can buy.', 'wpraffle' ),
                    'type'        => 'number',
                    'custom_attributes' => array( 'min' => 1 ),
                ) );

                // Start Date
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_start_date',
                    'label'       => __( 'Start Date & Time', 'wpraffle' ),
                    'value'       => ! empty( $start_date ) ? str_replace( ' ', 'T', $start_date ) : '',
                    'type'         => 'datetime-local',
                    'desc_tip'    => true,
                    'description' => __( 'Date and time the raffle starts/goes live.', 'wpraffle' ),
                ) );

                // Draw Date
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_draw_date',
                    'label'       => __( 'Draw Date & Time', 'wpraffle' ),
                    'value'       => ! empty( $draw_date ) ? str_replace( ' ', 'T', $draw_date ) : '',
                    'type'         => 'datetime-local',
                    'desc_tip'    => true,
                    'description' => __( 'Date and time of the draw.', 'wpraffle' ),
                ) );

                // Status dropdown
                woocommerce_wp_select( array(
                    'id'          => '_raffle_status',
                    'label'       => __( 'Raffle Status', 'wpraffle' ),
                    'value'       => $status,
                    'options'     => array(
                        'draft'    => __( 'Draft', 'wpraffle' ),
                        'active'   => __( 'Active', 'wpraffle' ),
                        'finished' => __( 'Finished', 'wpraffle' ),
                    ),
                ) );
                ?>
            </div>

            <div class="options_group">
                <?php
                // Cash Alternative Toggle
                woocommerce_wp_checkbox( array(
                    'id'            => '_raffle_cash_alternative',
                    'label'         => __( 'Enable Cash Alternative', 'wpraffle' ),
                    'value'         => $cash_alternative ? 'yes' : 'no',
                    'cbvalue'       => 'yes',
                    'description'   => __( 'Toggle whether a cash alternative is offered.', 'wpraffle' ),
                ) );

                // Cash Alternative Amount
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_cash_alternative_amount',
                    'label'       => __( 'Cash Alternative Amount ($)', 'wpraffle' ),
                    'value'       => $cash_alternative_amount,
                    'placeholder' => 'e.g. 5000',
                    'desc_tip'    => true,
                    'description' => __( 'Cash prize amount if selected.', 'wpraffle' ),
                    'type'        => 'text',
                ) );
                ?>
            </div>

            <div class="options_group">
                <?php
                // Skill Question Toggle
                woocommerce_wp_checkbox( array(
                    'id'            => '_raffle_enable_question',
                    'label'         => __( 'Enable Skill Question (UK Compliance)', 'wpraffle' ),
                    'value'         => $enable_question ? 'yes' : 'no',
                    'cbvalue'       => 'yes',
                    'description'   => __( 'Toggle multiple choice question validation.', 'wpraffle' ),
                ) );

                // Question Text
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_question_text',
                    'label'       => __( 'Question Text', 'wpraffle' ),
                    'value'       => $question_text,
                    'placeholder' => 'e.g. What is the capital of the UK?',
                    'type'        => 'text',
                ) );

                // Option 1
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_question_answer_0',
                    'label'       => __( 'Option 1', 'wpraffle' ),
                    'value'       => $answers[0],
                    'type'        => 'text',
                ) );

                // Option 2
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_question_answer_1',
                    'label'       => __( 'Option 2', 'wpraffle' ),
                    'value'       => $answers[1],
                    'type'        => 'text',
                ) );

                // Option 3
                woocommerce_wp_text_input( array(
                    'id'          => '_raffle_question_answer_2',
                    'label'       => __( 'Option 3', 'wpraffle' ),
                    'value'       => $answers[2],
                    'type'        => 'text',
                ) );

                // Correct Answer index
                woocommerce_wp_select( array(
                    'id'          => '_raffle_correct_answer_index',
                    'label'       => __( 'Correct Option', 'wpraffle' ),
                    'value'       => $correct_answer_index,
                    'options'     => array(
                        '0' => __( 'Option 1', 'wpraffle' ),
                        '1' => __( 'Option 2', 'wpraffle' ),
                        '2' => __( 'Option 3', 'wpraffle' ),
                    ),
                ) );
                ?>
            </div>

            <div class="options_group">
                <?php
                // Postal instructions
                woocommerce_wp_textarea_input( array(
                    'id'          => '_raffle_postal_instructions',
                    'label'       => __( 'Postal Entry Instructions', 'wpraffle' ),
                    'value'       => $postal_instructions,
                    'placeholder' => 'Describe how postal entries should be submitted...',
                ) );
                ?>
            </div>

            <?php if ( $raffle_id ) : ?>
                <div class="options_group" style="padding: 12px 20px 20px;">
                    <h3><?php esc_html_e( 'Configure Instant Wins', 'wpraffle' ); ?></h3>
                    <div class="rs-instant-wins-config">
                        <div style="display:flex;gap:10px;margin-bottom:15px;align-items:flex-end;">
                            <div style="flex:1;">
                                <label style="display:block;margin-bottom:5px;"><?php esc_html_e( 'Prize Name', 'wpraffle' ); ?></label>
                                <input type="text" id="new-instant-prize-name" style="width:100%;" placeholder="e.g. £50 Cash">
                            </div>
                            <div style="width:150px;">
                                <label style="display:block;margin-bottom:5px;"><?php esc_html_e( 'Ticket Number', 'wpraffle' ); ?></label>
                                <input type="number" id="new-instant-ticket-number" style="width:100%;" placeholder="Random">
                            </div>
                            <button type="button" class="button button-primary" id="btn-add-instant-win" data-raffle-id="<?php echo esc_attr( $raffle_id ); ?>">
                                <?php esc_html_e( 'Add Prize', 'wpraffle' ); ?>
                            </button>
                        </div>

                        <table class="wp-list-table widefat fixed striped" id="table-instant-wins">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Ticket #', 'wpraffle' ); ?></th>
                                    <th><?php esc_html_e( 'Prize Name', 'wpraffle' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'wpraffle' ); ?></th>
                                    <th><?php esc_html_e( 'Winner', 'wpraffle' ); ?></th>
                                    <th style="width:70px;"><?php esc_html_e( 'Action', 'wpraffle' ); ?></th>
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
                                                    <?php esc_html_e( 'Delete', 'wpraffle' ); ?>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php
                                    endforeach;
                                else :
                                    ?>
                                    <tr class="no-items"><td colspan="5" align="center"><?php esc_html_e( 'No instant wins configured.', 'wpraffle' ); ?></td></tr>
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

        // SEC-A2 FIX: Independent capability + nonce verification
        if ( ! current_user_can( 'edit_product', $product_id ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // WooCommerce validates its own product-save nonce (woocommerce_save_products).
        if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
            return;
        }

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
        // SEC-L1: Use wp_update_post() instead of a raw UPDATE on wp_posts so that
        // post-status transition hooks (and security plugins watching them) fire.
        $wc_status = $status === 'draft' ? 'draft' : 'publish';
        if ( get_post_status( $product_id ) !== $wc_status ) {
            wp_update_post( array(
                'ID'          => $product_id,
                'post_status' => $wc_status,
            ) );
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
    public function show_charity_badge() {
        global $product;
        if ( ! $product ) return;
        $raffle_id = get_post_meta( $product->get_id(), '_raffle_id', true );
        if ( ! $raffle_id || ! class_exists( 'Raffle_Charity' ) ) return;

        $charity_info = Raffle_Charity::get_raffle_charity( $raffle_id );
        if ( ! $charity_info ) return;

        $c = $charity_info['charity'];
        $pct = $charity_info['percent'];
        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare( "SELECT sold_tickets, ticket_price, prize_value FROM {$wpdb->prefix}raffles WHERE id = %d", $raffle_id ) );
        $raised = $raffle ? Raffle_Charity::get_live_raised_estimate( $raffle ) : 0;

        echo '<details class="wpr-charity-details-dropdown" style="margin: 15px 0; border: 1px solid var(--wpr-accent-border, #a7f3d0); border-radius: 12px; background: var(--wpr-accent-bg, #ecfdf5); overflow: hidden; font-family: inherit; width: 100%; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">';
        echo '<summary style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; cursor: pointer; list-style: none; outline: none; font-weight: 700; color: var(--wpr-accent-text, #065f46); font-size: 14px; user-select: none;">';
        echo '<div style="display: flex; align-items: center; gap: 8px;">';
        echo '<svg class="wpr-icon wpr-icon--sm" style="color: var(--wpr-accent, #059669); flex-shrink: 0;"><use href="#wpr-gift"></use></svg>';
        echo '<span>' . esc_html( $pct ) . '% to ' . esc_html( $c->name ) . '</span>';
        if ( $raised > 0 ) {
            echo '<span style="font-size: 12px; color: var(--wpr-accent-text-dark, #047857); font-weight: 600; margin-left: 6px;">(' . esc_html( wpr_price( $raised, 0 ) ) . ' raised so far)</span>';
        }
        echo '</div>';
        echo '<svg class="wpr-icon wpr-icon--xs wpr-dropdown-arrow" style="transition: transform 0.2s; flex-shrink: 0;"><use href="#wpr-chevron-down"></use></svg>';
        echo '</summary>';

        echo '<div style="padding: 16px; border-top: 1px solid var(--wpr-accent-border, #a7f3d0); background: var(--wpr-bg-surface, #ffffff); display: flex; flex-direction: column; gap: 12px; text-align: left;">';
        echo '<div style="display: flex; gap: 12px; align-items: flex-start;">';
        if ( ! empty( $c->logo_url ) ) {
            echo '<img src="' . esc_url( $c->logo_url ) . '" alt="' . esc_attr( $c->name ) . '" style="width: 50px; height: 50px; object-fit: contain; border-radius: 8px; border: 1px solid var(--wpr-border-color, #e5e7eb); padding: 4px; background: #fff; flex-shrink: 0;">';
        }
        echo '<div style="flex-grow: 1;">';
        echo '<h4 style="margin: 0 0 4px; font-size: 14px; font-weight: 700; color: var(--wpr-text-primary, #1f2937);">' . esc_html( $c->name ) . '</h4>';
        if ( ! empty( $c->registration_number ) ) {
            echo '<div style="font-size: 11px; color: var(--wpr-text-muted, #6b7280); font-weight: 600;">Registered Charity No. ' . esc_html( $c->registration_number ) . '</div>';
        }
        echo '</div>';
        echo '</div>';

        if ( ! empty( $c->description ) ) {
            echo '<p style="margin: 0; font-size: 13px; line-height: 1.5; color: var(--wpr-text-secondary, #4b5563);">' . esc_html( $c->description ) . '</p>';
        }

        if ( ! empty( $c->website ) ) {
            echo '<div style="margin-top: 4px;">';
            echo '<a href="' . esc_url( $c->website ) . '" target="_blank" rel="noopener noreferrer" class="button alt" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 12px; font-weight: 600; text-decoration: none; border-radius: 6px; background: var(--wpr-accent, #6c5ce7); color: #fff;">Visit Website →</a>';
            echo '</div>';
        }
        echo '</div>';
        echo '</details>';
    }

}

/**
 * Define WC_Product_Raffle class globally on plugins_loaded to extend WC_Product.
 */
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'WC_Product_Raffle' ) ) {
        class WC_Product_Raffle extends WC_Product_Simple {
            public function __construct( $product = 0 ) {
                parent::__construct( $product );
            }
        }
    }
}, 20 );
