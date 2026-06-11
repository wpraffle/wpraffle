<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Admin {

    public function __construct() {
        $this->run_migrations();
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
        add_action( 'admin_post_wpraffle_save_email_settings', array( $this, 'save_email_settings' ) );
        add_action( 'admin_post_wpraffle_send_test_email', array( $this, 'handle_test_email' ) );
        add_action( 'admin_post_wpraffle_save_general_settings', array( $this, 'save_general_settings' ) );
        add_action( 'admin_post_wpraffle_save_legal_settings', array( $this, 'save_legal_settings' ) );
        add_action( 'admin_post_wpraffle_save_advanced_settings', array( $this, 'save_advanced_settings' ) );
        add_action( 'admin_post_wpraffle_save_update_settings', array( $this, 'save_update_settings' ) );
        add_action( 'admin_post_wpraffle_create_page', array( $this, 'handle_create_page' ) );
        add_action( 'admin_post_wpraffle_save_pages', array( $this, 'save_pages' ) );

        // Schedule draw reminder cron if not already scheduled
        if ( ! wp_next_scheduled( 'raffle_draw_reminder_cron' ) ) {
            wp_schedule_event( time(), 'hourly', 'raffle_draw_reminder_cron' );
        }
    }

    public function run_migrations() {
        global $wpdb;
        $table = $wpdb->prefix . 'raffles';

        // v2 migration
        if ( ! get_option( 'raffle_system_db_migrated_v2' ) ) {
            $col_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'enable_question'" );
            if ( empty( $col_exists ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN enable_question tinyint(1) NOT NULL DEFAULT 0" );
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN question_text text" );
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN question_answers text" );
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN correct_answer_index int(11) NOT NULL DEFAULT 0" );
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN postal_instructions text" );
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN max_tickets_per_user int(11) NOT NULL DEFAULT 100" );
            }
            update_option( 'raffle_system_db_migrated_v2', 1 );
        }

        // v3 migration: add reminder_sent column
        $col_v3 = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'reminder_sent'" );
        if ( empty( $col_v3 ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN reminder_sent tinyint(1) NOT NULL DEFAULT 0" );
        }

        // v4 migration: new feature columns + tables
        if ( ! get_option( 'raffle_system_db_migrated_v4' ) ) {
            $v4_cols = array(
                'multi_winner'            => "ALTER TABLE {$table} ADD COLUMN multi_winner tinyint(1) NOT NULL DEFAULT 0",
                'number_of_winners'       => "ALTER TABLE {$table} ADD COLUMN number_of_winners int(11) NOT NULL DEFAULT 2",
                'allow_free_entry'        => "ALTER TABLE {$table} ADD COLUMN allow_free_entry tinyint(1) NOT NULL DEFAULT 0",
                'free_entry_question'     => "ALTER TABLE {$table} ADD COLUMN free_entry_question text",
                'free_entry_answers'      => "ALTER TABLE {$table} ADD COLUMN free_entry_answers text",
                'free_entry_correct_index' => "ALTER TABLE {$table} ADD COLUMN free_entry_correct_index int(11) NOT NULL DEFAULT 0",
                'geo_restricted'          => "ALTER TABLE {$table} ADD COLUMN geo_restricted tinyint(1) NOT NULL DEFAULT 0",
                'geo_allowed_countries'   => "ALTER TABLE {$table} ADD COLUMN geo_allowed_countries text",
                'allow_referrals'         => "ALTER TABLE {$table} ADD COLUMN allow_referrals tinyint(1) NOT NULL DEFAULT 0",
                'referral_bonus_entries'  => "ALTER TABLE {$table} ADD COLUMN referral_bonus_entries int(11) NOT NULL DEFAULT 1",
                'template_id'             => "ALTER TABLE {$table} ADD COLUMN template_id int(11) DEFAULT NULL",
            );
            foreach ( $v4_cols as $col => $sql ) {
                $exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE '{$col}'" );
                if ( empty( $exists ) ) {
                    $wpdb->query( $sql );
                }
            }

            // v4: new tables
            $charset = $wpdb->get_charset_collate();

            $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_prizes (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                raffle_id bigint(20) NOT NULL,
                position int(11) NOT NULL DEFAULT 0,
                prize_name varchar(255) NOT NULL,
                prize_value decimal(10,2) DEFAULT 0,
                winner_ticket_id bigint(20) DEFAULT NULL,
                PRIMARY KEY (id),
                KEY raffle_id (raffle_id)
            ) {$charset}" );

            $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_referrals (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                raffle_id bigint(20) NOT NULL,
                user_id bigint(20) DEFAULT NULL,
                referral_code varchar(32) NOT NULL,
                referred_email varchar(255) DEFAULT NULL,
                bonus_entries int(11) NOT NULL DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY referral_code (referral_code),
                KEY raffle_id (raffle_id)
            ) {$charset}" );

            $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_reservations (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                raffle_id bigint(20) NOT NULL,
                session_id varchar(128) NOT NULL,
                ticket_numbers text NOT NULL,
                reserved_at datetime DEFAULT CURRENT_TIMESTAMP,
                expires_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY session_raffle (session_id, raffle_id)
            ) {$charset}" );

            $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_audit_log (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                raffle_id bigint(20) NOT NULL,
                action_type varchar(50) NOT NULL,
                user_id bigint(20) DEFAULT NULL,
                details longtext,
                fairness_proof varchar(128) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY raffle_id (raffle_id)
            ) {$charset}" );

            $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_templates (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                config longtext NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset}" );

            $wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}raffle_free_entries (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                raffle_id bigint(20) NOT NULL,
                user_email varchar(255) NOT NULL,
                ticket_number int(11) NOT NULL,
                answer_index int(11) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY raffle_email (raffle_id, user_email)
            ) {$charset}" );

            // Add new columns to purchases and tickets tables
            $purchases_table = $wpdb->prefix . 'raffle_purchases';
            $tickets_table = $wpdb->prefix . 'raffle_tickets';

            $purch_cols = array(
                'referral_code' => "ALTER TABLE {$purchases_table} ADD COLUMN referral_code varchar(32) DEFAULT NULL",
                'entry_type'    => "ALTER TABLE {$purchases_table} ADD COLUMN entry_type varchar(20) DEFAULT 'purchase'",
            );
            foreach ( $purch_cols as $col => $sql ) {
                $exists = $wpdb->get_results( "SHOW COLUMNS FROM {$purchases_table} LIKE '{$col}'" );
                if ( empty( $exists ) ) {
                    $wpdb->query( $sql );
                }
            }

            $ticket_cols = array(
                'is_reserved' => "ALTER TABLE {$tickets_table} ADD COLUMN is_reserved tinyint(1) NOT NULL DEFAULT 0",
                'reserved_at' => "ALTER TABLE {$tickets_table} ADD COLUMN reserved_at datetime DEFAULT NULL",
            );
            foreach ( $ticket_cols as $col => $sql ) {
                $exists = $wpdb->get_results( "SHOW COLUMNS FROM {$tickets_table} LIKE '{$col}'" );
                if ( empty( $exists ) ) {
                    $wpdb->query( $sql );
                }
            }

            update_option( 'raffle_system_db_migrated_v4', 1 );
        }

        // v5 migration: draw_video_url + verified_result
        if ( ! get_option( 'raffle_system_db_migrated_v5' ) ) {
            $v5_cols = array(
                'draw_video_url'  => "ALTER TABLE {$table} ADD COLUMN draw_video_url varchar(500) DEFAULT ''",
                'verified_result' => "ALTER TABLE {$table} ADD COLUMN verified_result text",
            );
            foreach ( $v5_cols as $col => $sql ) {
                $exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE '{$col}'" );
                if ( empty( $exists ) ) {
                    $wpdb->query( $sql );
                }
            }
            update_option( 'raffle_system_db_migrated_v5', 1 );
        }
    }

    public function add_menu() {
        // Top-level — clicking "Raffles" goes to Dashboard
        add_menu_page(
            'Raffles',
            'Raffles',
            'manage_options',
            'raffle-system',
            array( $this, 'render_dashboard_page' ),
            'dashicons-tickets-alt',
            30
        );

        // 1. Dashboard (same slug as parent — replaces top-level callback)
        add_submenu_page(
            'raffle-system',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'raffle-system',
            array( $this, 'render_dashboard_page' )
        );

        // 2. All Raffles
        add_submenu_page(
            'raffle-system',
            'All Raffles',
            'All Raffles',
            'manage_options',
            'raffle-list',
            array( $this, 'render_list_page' )
        );

        // 3. New Raffle
        add_submenu_page(
            'raffle-system',
            'New Raffle',
            'New Raffle',
            'manage_options',
            'raffle-new',
            array( $this, 'render_form_page' )
        );

        // 4. Audit Log
        add_submenu_page(
            'raffle-system',
            'Audit Log',
            'Audit Log',
            'manage_options',
            'raffle-audit',
            array( $this, 'render_audit_page' )
        );

        // 5. Settings
        add_submenu_page(
            'raffle-system',
            'Settings',
            'Settings',
            'manage_options',
            'wpraffle-settings',
            array( $this, 'render_settings_page' )
        );

    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'raffle' ) === false ) {
            return;
        }

        wp_enqueue_media();

        // Google Fonts — Inter
        wp_enqueue_style( 'raffle-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
            array(), null
        );

        // WPRaffle Icon System
        wp_enqueue_style( 'wpraffle-icons', RAFFLE_SYSTEM_URL . 'assets/css/icons.css', array(), RAFFLE_SYSTEM_VERSION );

        wp_enqueue_style( 'raffle-admin', RAFFLE_SYSTEM_URL . 'assets/css/admin.css', array( 'raffle-google-fonts', 'wpraffle-icons' ), RAFFLE_SYSTEM_VERSION );
        wp_enqueue_script( 'raffle-admin', RAFFLE_SYSTEM_URL . 'assets/js/admin.js', array( 'jquery' ), RAFFLE_SYSTEM_VERSION, true );
        wp_localize_script( 'raffle-admin', 'raffleAdmin', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'draw_nonce' => wp_create_nonce( 'raffle_draw_nonce' ),
            'clone_nonce' => wp_create_nonce( 'raffle_clone_nonce' ),
        ) );

        // Dashboard page — Chart.js + dashboard.js (hook is toplevel_page_raffle-system)
        if ( strpos( $hook, 'toplevel_page_raffle-system' ) !== false ) {
            wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js', array(), '4.4.7', true );
            wp_enqueue_script( 'raffle-dashboard', RAFFLE_SYSTEM_URL . 'assets/js/dashboard.js', array( 'jquery', 'chartjs' ), RAFFLE_SYSTEM_VERSION, true );
            wp_localize_script( 'raffle-dashboard', 'raffleDashboard', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'raffle_analytics_nonce' ),
                'curSym'   => wpr_currency_symbol(),
            ) );
        }
    }

    public function handle_form_submission() {
        if ( ! isset( $_POST['raffle_form_submit'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! isset( $_POST['raffle_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['raffle_nonce'] ) ), 'raffle_save' ) ) {
            wp_die( 'Security error.' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'raffles';

        $data = array(
            'title'                   => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
            'description'             => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'prize_value'             => floatval( $_POST['prize_value'] ?? 0 ),
            'prize_image'             => esc_url_raw( wp_unslash( $_POST['prize_image'] ?? '' ) ),
            'total_tickets'           => absint( $_POST['total_tickets'] ?? 0 ),
            'ticket_price'            => floatval( $_POST['ticket_price'] ?? 0 ),
            'start_date'              => sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) ),
            'draw_date'               => sanitize_text_field( wp_unslash( $_POST['draw_date'] ?? '' ) ),
            'status'                  => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
            'ticket_selection'        => sanitize_text_field( wp_unslash( $_POST['ticket_selection'] ?? 'random' ) ),
            'draw_type'               => sanitize_text_field( wp_unslash( $_POST['draw_type'] ?? 'manual' ) ),
            'live_draw_url'           => esc_url_raw( wp_unslash( $_POST['live_draw_url'] ?? '' ) ),
            'jackpot_type'            => sanitize_text_field( wp_unslash( $_POST['jackpot_type'] ?? 'fixed' ) ),
            'jackpot_percent'         => absint( $_POST['jackpot_percent'] ?? 50 ),
            'enable_cash_alternative' => (int) ( $_POST['enable_cash_alternative'] ?? 0 ),
            'cash_alternative_amount' => floatval( $_POST['cash_alternative_amount'] ?? 0 ),
            'enable_question'         => (int) ( $_POST['enable_question'] ?? 0 ),
            'question_text'           => sanitize_text_field( wp_unslash( $_POST['question_text'] ?? '' ) ),
            'correct_answer_index'    => (int) ( $_POST['correct_answer_index'] ?? 0 ),
            'postal_instructions'     => sanitize_textarea_field( wp_unslash( $_POST['postal_instructions'] ?? '' ) ),
            'max_tickets_per_user'    => (int) ( $_POST['max_tickets_per_user'] ?? 100 ),
            'multi_winner'            => (int) ( $_POST['multi_winner'] ?? 0 ),
            'number_of_winners'       => (int) ( $_POST['number_of_winners'] ?? 2 ),
            'allow_free_entry'        => (int) ( $_POST['allow_free_entry'] ?? 0 ),
            'geo_restricted'          => (int) ( $_POST['geo_restricted'] ?? 0 ),
            'geo_allowed_countries'   => wp_json_encode( array_map( 'sanitize_text_field', (array) ( $_POST['geo_allowed_countries'] ?? array() ) ) ),
            'allow_referrals'         => (int) ( $_POST['allow_referrals'] ?? 0 ),
            'referral_bonus_entries'  => (int) ( $_POST['referral_bonus_entries'] ?? 1 ),
            'draw_video_url'          => esc_url_raw( wp_unslash( $_POST['draw_video_url'] ?? '' ) ),
            'verified_result'         => sanitize_textarea_field( wp_unslash( $_POST['verified_result'] ?? '' ) ),
        );

        // Convert empty start_date to NULL for DB compatibility
        if ( empty( $data['start_date'] ) ) {
            $data['start_date'] = null;
        } else {
            // Convert datetime-local format (T) to MySQL format
            $data['start_date'] = str_replace( 'T', ' ', $data['start_date'] );
            if ( strlen( $data['start_date'] ) === 16 ) { // YYYY-MM-DD HH:MM
                $data['start_date'] .= ':00';
            }
        }

        // Convert empty draw_date to NULL for DB compatibility
        if ( empty( $data['draw_date'] ) ) {
            $data['draw_date'] = null;
        } else {
            // Convert datetime-local format (T) to MySQL format
            $data['draw_date'] = str_replace( 'T', ' ', $data['draw_date'] );
            if ( strlen( $data['draw_date'] ) === 16 ) { // YYYY-MM-DD HH:MM
                $data['draw_date'] .= ':00';
            }
        }

        // Packages
        $packages_raw = sanitize_text_field( wp_unslash( $_POST['packages'] ?? '' ) );
        $packages     = array_values( array_filter( array_map( 'absint', explode( ',', $packages_raw ) ) ) );
        $data['packages'] = wp_json_encode( $packages );

        // Answers
        $answers = array(
            sanitize_text_field( wp_unslash( $_POST['question_answer_0'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['question_answer_1'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['question_answer_2'] ?? '' ) ),
        );
        $data['question_answers'] = wp_json_encode( $answers );

        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;

        // Sync WooCommerce Product
        $wc_product_id = 0;
        if ( class_exists( 'WooCommerce' ) ) {
            $wc_status = $data['status'] === 'draft' ? 'draft' : 'publish';
            $product_data = array(
                'post_title'   => $data['title'],
                'post_content' => $data['description'],
                'post_status'  => $wc_status,
                'post_type'    => 'product',
                'post_author'  => 1,
            );

            $existing_product_id = 0;
            if ( $raffle_id ) {
                $existing_product_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT wc_product_id FROM {$table} WHERE id = %d",
                    $raffle_id
                ) );
            }

            if ( $existing_product_id && get_post( $existing_product_id ) ) {
                $product_data['ID'] = $existing_product_id;
                wp_update_post( $product_data );
                $wc_product_id = $existing_product_id;
            } else {
                $wc_product_id = wp_insert_post( $product_data );
                if ( ! is_wp_error( $wc_product_id ) ) {
                    wp_set_object_terms( $wc_product_id, 'simple', 'product_type' );
                }
            }

            if ( ! is_wp_error( $wc_product_id ) && $wc_product_id ) {
                update_post_meta( $wc_product_id, '_visibility', 'visible' );
                update_post_meta( $wc_product_id, '_stock_status', 'instock' );
                update_post_meta( $wc_product_id, '_regular_price', $data['ticket_price'] );
                update_post_meta( $wc_product_id, '_price', $data['ticket_price'] );
                update_post_meta( $wc_product_id, '_virtual', 'yes' );
                update_post_meta( $wc_product_id, '_sold_individually', 'no' );
                update_post_meta( $wc_product_id, '_raffle_id', $raffle_id ? $raffle_id : 'temp' );
                update_post_meta( $wc_product_id, '_raffle_start_date', $data['start_date'] );
                update_post_meta( $wc_product_id, '_raffle_draw_date', $data['draw_date'] );
                update_post_meta( $wc_product_id, '_raffle_status', $data['status'] );
                
                // Clear catalog visibility exclusion
                wp_remove_object_terms( $wc_product_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility' );

                // Apply WooCommerce product categories
                if ( isset( $_POST['product_categories'] ) && is_array( $_POST['product_categories'] ) ) {
                    $cat_ids = array_map( 'absint', $_POST['product_categories'] );
                    wp_set_object_terms( $wc_product_id, $cat_ids, 'product_cat' );
                } else {
                    wp_set_object_terms( $wc_product_id, array(), 'product_cat' );
                }

                // Apply WooCommerce product tags
                if ( isset( $_POST['product_tags'] ) && is_array( $_POST['product_tags'] ) ) {
                    $tag_ids = array_map( 'absint', $_POST['product_tags'] );
                    wp_set_object_terms( $wc_product_id, $tag_ids, 'product_tag' );
                } else {
                    wp_set_object_terms( $wc_product_id, array(), 'product_tag' );
                }
            }
        }

        $data['wc_product_id'] = $wc_product_id;

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
            $result = $wpdb->update( $table, $data, array( 'id' => $raffle_id ), $formats, array( '%d' ) );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $formats[]          = '%s';
            $result = $wpdb->insert( $table, $data, $formats );
            $new_raffle_id = $wpdb->insert_id;
            if ( $wc_product_id && $new_raffle_id ) {
                update_post_meta( $wc_product_id, '_raffle_id', $new_raffle_id );
            }
        }

        if ( false === $result ) {
            error_log( 'WPRaffle: Error saving raffle: ' . $wpdb->last_error );
            wp_die( 'Error saving raffle. Please check the error logs for details.' );
        }

        // Audit log
        if ( class_exists( 'Raffle_Audit' ) ) {
            $action = $raffle_id ? 'admin_update' : 'admin_create';
            $rid = $raffle_id ? $raffle_id : ( $new_raffle_id ?? 0 );
            Raffle_Audit::log( $rid, $action, "Raffle '{$data['title']}' " . ( $raffle_id ? 'updated' : 'created' ) . " by admin. Status: {$data['status']}.", '' );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=raffle-list&message=saved' ) );
        exit;
    }

    public function render_list_page() {
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'view' && isset( $_GET['id'] ) ) {
            $this->render_details_page();
            return;
        }
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
            $this->render_form_page();
            return;
        }
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
            $this->delete_raffle();
            return;
        }

        include RAFFLE_SYSTEM_PATH . 'admin/views/raffle-list.php';
    }

    public function render_dashboard_page() {
        include RAFFLE_SYSTEM_PATH . 'admin/views/dashboard.php';
    }

    public function render_form_page() {
        $raffle = null;
        if ( isset( $_GET['id'] ) ) {
            global $wpdb;
            $raffle = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
                absint( $_GET['id'] )
            ) );
        }
        include RAFFLE_SYSTEM_PATH . 'admin/views/raffle-form.php';
    }

    private function render_details_page() {
        global $wpdb;
        $raffle_id = absint( $_GET['id'] );
        $raffle    = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            echo '<div class="wrap"><h1>Raffle not found</h1></div>';
            return;
        }

        $purchases = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_purchases WHERE raffle_id = %d ORDER BY purchase_date DESC",
            $raffle_id
        ) );

        $winner = null;
        if ( $raffle->winner_ticket_id ) {
            $winner = $wpdb->get_row( $wpdb->prepare(
                "SELECT t.*, p.buyer_name
                 FROM {$wpdb->prefix}raffle_tickets t
                 JOIN {$wpdb->prefix}raffle_purchases p ON t.purchase_id = p.id
                 WHERE t.id = %d",
                $raffle->winner_ticket_id
            ) );
        }

        include RAFFLE_SYSTEM_PATH . 'admin/views/raffle-details.php';
    }

    private function delete_raffle() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_raffle' ) ) {
            wp_die( 'Security error.' );
        }

        global $wpdb;
        $raffle_id = absint( $_GET['id'] );

        // Delete associated WooCommerce product if exists
        if ( class_exists( 'WooCommerce' ) ) {
            $wc_product_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT wc_product_id FROM {$wpdb->prefix}raffles WHERE id = %d",
                $raffle_id
            ) );
            if ( $wc_product_id ) {
                wp_delete_post( $wc_product_id, true );
            }
        }

        // ATOMIC TRANSACTION: delete everything or nothing
        $wpdb->query( 'START TRANSACTION' );
        $wpdb->delete( $wpdb->prefix . 'raffle_tickets', array( 'raffle_id' => $raffle_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'raffle_purchases', array( 'raffle_id' => $raffle_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'raffles', array( 'id' => $raffle_id ), array( '%d' ) );
        $wpdb->query( 'COMMIT' );

        wp_safe_redirect( admin_url( 'admin.php?page=raffle-list&message=deleted' ) );
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Unified Settings Page
    // ─────────────────────────────────────────────────────────────────────

    public function render_audit_page() {
        include RAFFLE_SYSTEM_PATH . 'admin/views/audit-log.php';
    }

    public function render_settings_page() {
        include RAFFLE_SYSTEM_PATH . 'admin/views/settings.php';
    }

    /** @deprecated Use render_settings_page() with tab=email */
    public function render_email_settings_page() {
        include RAFFLE_SYSTEM_PATH . 'admin/views/settings.php';
    }

    public function save_general_settings() {
        $this->verify_settings_nonce();
        $settings = array(
            'company_name'              => sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ),
            'company_address'           => sanitize_textarea_field( wp_unslash( $_POST['company_address'] ?? '' ) ),
            'currency_code'             => sanitize_text_field( wp_unslash( $_POST['currency_code'] ?? 'GBP' ) ),
            'logo_url'                  => esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) ),
            'max_tickets_default'       => absint( $_POST['max_tickets_default'] ?? 100 ),
            'winners_show_instant_wins' => isset( $_POST['winners_show_instant_wins'] ) ? 1 : 0,
        );
        update_option( 'wpraffle_general_settings', $settings );
        $this->redirect_settings( 'general' );
    }

    public function save_email_settings() {
        $this->verify_email_nonce();
        $settings = array(
            'from_name'    => sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) ),
            'from_email'   => sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) ),
            'accent_color' => sanitize_hex_color( wp_unslash( $_POST['accent_color'] ?? '#6c5ce7' ) ),
            'logo_url'     => esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) ),
            'footer_text'  => sanitize_textarea_field( wp_unslash( $_POST['footer_text'] ?? '' ) ),
        );
        update_option( 'wpraffle_email_settings', $settings );
        $this->redirect_settings( 'email' );
    }

    public function save_legal_settings() {
        $this->verify_settings_nonce();
        $settings = array(
            'rules_template' => wp_kses_post( wp_unslash( $_POST['rules_template'] ?? '' ) ),
            'faq_template'   => wp_kses_post( wp_unslash( $_POST['faq_template'] ?? '' ) ),
            'faq_items'      => (function() {
                $questions = $_POST['faq_questions'] ?? array();
                $answers   = $_POST['faq_answers'] ?? array();
                $items = array();
                for ( $i = 0; $i < max( count( $questions ), count( $answers ) ); $i++ ) {
                    $q = sanitize_text_field( wp_unslash( $questions[ $i ] ?? '' ) );
                    $a = wp_kses_post( wp_unslash( $answers[ $i ] ?? '' ) );
                    if ( $q || $a ) {
                        $items[] = array( 'q' => $q, 'a' => $a );
                    }
                }
                return wp_json_encode( $items );
            })(),
        );
        update_option( 'wpraffle_legal_settings', $settings );
        $this->redirect_settings( 'legal' );
    }

    public function save_advanced_settings() {
        $this->verify_settings_nonce();
        $settings = array(
            'auto_fix_duplicates'   => absint( $_POST['auto_fix_duplicates'] ?? 0 ),
            'rate_limit_per_minute' => absint( $_POST['rate_limit_per_minute'] ?? 5 ),
            'audit_log_days'        => absint( $_POST['audit_log_days'] ?? 90 ),
            'enable_audit'          => absint( $_POST['enable_audit'] ?? 1 ),
        );
        update_option( 'wpraffle_advanced_settings', $settings );
        $this->redirect_settings( 'advanced' );
    }

    public function save_update_settings() {
        $this->verify_settings_nonce();
        $settings = array(
            'github_repo'     => sanitize_text_field( wp_unslash( $_POST['github_repo'] ?? 'wpraffle/wpraffle' ) ),
            'auto_update'     => absint( $_POST['auto_update'] ?? 0 ),
            'update_channel'  => sanitize_text_field( wp_unslash( $_POST['update_channel'] ?? 'stable' ) ),
        );
        update_option( 'wpraffle_update_settings', $settings );
        // Clear cache so next check uses new repo
        delete_transient( 'wpraffle_release_info' );
        delete_transient( 'wpraffle_latest_version' );
        $this->redirect_settings( 'updates' );
    }

    public function handle_create_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorised.' );
        }
        if ( ! isset( $_POST['wpraffle_page_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpraffle_page_nonce'] ) ), 'wpraffle_create_page' ) ) {
            wp_die( 'Security check failed.' );
        }

        $key = sanitize_text_field( wp_unslash( $_POST['page_key'] ?? '' ) );
        $pages = get_option( 'wpraffle_pages', array() );

        $configs = array(
            'raffles'     => array( 'title' => 'Raffles',      'content' => '[raffle_list]' ),
            'ended'       => array( 'title' => 'Past Raffles', 'content' => '[raffle_ended_list]' ),
            'entry_list'  => array( 'title' => 'Entry Lists',  'content' => '[raffle_entry_list]' ),
            'live_draw'   => array( 'title' => 'Live Draw',    'content' => '[raffle_live_draw]' ),
            'my_raffles'  => array( 'title' => 'My Raffles',   'content' => '' ),
        );

        if ( ! isset( $configs[ $key ] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=wpraffle-settings&tab=pages' ) );
            exit;
        }

        $cfg = $configs[ $key ];
        $page_id = wp_insert_post( array(
            'post_title'   => $cfg['title'],
            'post_content' => $cfg['content'],
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id(),
        ) );

        if ( ! is_wp_error( $page_id ) ) {
            $pages[ $key ] = $page_id;
            update_option( 'wpraffle_pages', $pages );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=wpraffle-settings&tab=pages&saved=1' ) );
        exit;
    }

    public function save_pages() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorised.' );
        }
        if ( ! isset( $_POST['wpraffle_pages_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpraffle_pages_nonce'] ) ), 'wpraffle_save_pages' ) ) {
            wp_die( 'Security check failed.' );
        }

        $keys = array( 'raffles', 'ended', 'entry_list', 'live_draw', 'my_raffles' );
        $pages = get_option( 'wpraffle_pages', array() );

        foreach ( $keys as $key ) {
            $field = 'wpraffle_page_' . $key;
            $pages[ $key ] = isset( $_POST[ $field ] ) ? absint( $_POST[ $field ] ) : 0;
        }

        update_option( 'wpraffle_pages', $pages );
        $this->redirect_settings( 'pages' );
    }

    private function verify_settings_nonce() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorised.' );
        }
        if ( ! isset( $_POST['wpraffle_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpraffle_settings_nonce'] ) ), 'wpraffle_save_settings' ) ) {
            wp_die( 'Security check failed.' );
        }
    }

    private function verify_email_nonce() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorised.' );
        }
        if ( ! isset( $_POST['wpraffle_email_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpraffle_email_nonce'] ) ), 'wpraffle_save_email_settings' ) ) {
            wp_die( 'Security check failed.' );
        }
    }

    private function redirect_settings( $tab ) {
        wp_safe_redirect( admin_url( 'admin.php?page=wpraffle-settings&tab=' . $tab . '&saved=1' ) );
        exit;
    }

    public function handle_test_email() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorised.' );
        }
        if ( ! isset( $_POST['wpraffle_test_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpraffle_test_nonce'] ) ), 'wpraffle_test_email' ) ) {
            wp_die( 'Security check failed.' );
        }

        $to     = sanitize_email( wp_unslash( $_POST['test_email_to'] ?? get_option( 'admin_email' ) ) );
        $result = Raffle_Email::send_test_email( $to );

        $status = $result ? 'sent' : 'failed';
        wp_safe_redirect( admin_url( 'admin.php?page=wpraffle-settings&tab=email&test=' . $status ) );
        exit;
    }
}
