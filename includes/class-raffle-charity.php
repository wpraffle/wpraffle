<?php
/**
 * WPRaffle — Charity Feature
 *
 * Lets an operator attach a charity to a raffle and pledge a percentage of
 * net proceeds. Allocations are snapshotted (immutable) at draw time so that
 * post-draw refunds cannot silently change what was publicly promised.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Charity {

    public function __construct() {
        add_shortcode( 'raffle_charities', array( $this, 'render_charities_shortcode' ) );
        add_action( 'raffle_charity_allocations_refresh', array( __CLASS__, 'refresh_all_totals' ) );

        // Live polling for the charity grid.
        add_action( 'wp_ajax_raffle_charity_totals', array( __CLASS__, 'ajax_get_charity_totals' ) );
        add_action( 'wp_ajax_nopriv_raffle_charity_totals', array( __CLASS__, 'ajax_get_charity_totals' ) );
    }

    /* ===================================================================
       Data Access
       =================================================================== */

    public static function get_charity( $charity_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_charities WHERE id = %d",
            absint( $charity_id )
        ) );
    }

    /**
     * Get a charity by CPT post ID (used when charity_id refers to a post, not a DB row).
     */
    public static function get_charity_by_post( $post_id ) {
        $post = get_post( absint( $post_id ) );
        if ( ! $post || $post->post_type !== 'raffle_charity' ) {
            return null;
        }
        return (object) array(
            'id'                  => $post->ID,
            'name'                => $post->post_title,
            'slug'                => $post->post_name,
            'description'         => get_post_meta( $post->ID, '_charity_description', true ),
            'logo_url'            => get_post_meta( $post->ID, '_charity_logo_url', true ),
            'website'             => get_post_meta( $post->ID, '_charity_website', true ),
            'registration_number' => get_post_meta( $post->ID, '_charity_registration_number', true ),
        );
    }

    /**
     * Get all active charities. Falls back to the CPT if the DB table is empty.
     */
    public static function get_active_charities() {
        global $wpdb;
        $table = $wpdb->prefix . 'raffle_charities';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

        $charities = array();
        if ( $table_exists ) {
            $charities = $wpdb->get_results(
                "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY name ASC"
            );
        }

        if ( ! empty( $charities ) ) {
            foreach ( $charities as &$c ) {
                // live_raised is authoritative — always computed directly from
                // wp_raffles so it reflects current sales even if the cached
                // total_raised column is stale.
                $c->live_raised = self::calculate_total_raised_for_charity( (int) $c->id );
            }
            return $charities;
        }

        // Fallback: query the CPT directly (used when the DB table is empty or
        // missing — e.g. before the v10 backstop migration has run).
        $posts = get_posts( array(
            'post_type'      => 'raffle_charity',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => array(
                array(
                    'key'     => '_charity_is_active',
                    'value'   => '1',
                    'compare' => '=',
                ),
            ),
        ) );

        $result = array();
        foreach ( $posts as $p ) {
            $slug = $p->post_name ?: sanitize_title( $p->post_title );
            $db_row = $table_exists
                ? $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $slug ) )
                : null;
            $charity_id = $db_row ? (int) $db_row->id : (int) $p->ID;

            $result[] = (object) array(
                'id'                  => $charity_id,
                'name'                => $p->post_title,
                'slug'                => $slug,
                'description'         => get_post_meta( $p->ID, '_charity_description', true ),
                'logo_url'            => get_post_meta( $p->ID, '_charity_logo_url', true ),
                'website'             => get_post_meta( $p->ID, '_charity_website', true ),
                'registration_number' => get_post_meta( $p->ID, '_charity_registration_number', true ),
                'is_active'           => 1,
                'total_raised'        => 0,
                'live_raised'         => self::calculate_total_raised_for_charity( $charity_id ),
            );
        }

        return $result;
    }

    public static function get_raffle_charity( $raffle_id ) {
        global $wpdb;
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT charity_id, charity_mode, charity_percent FROM {$wpdb->prefix}raffles WHERE id = %d",
            absint( $raffle_id )
        ) );

        if ( ! $raffle || $raffle->charity_mode === 'none' || ! $raffle->charity_id ) {
            return null;
        }

        $charity = self::get_charity( $raffle->charity_id );
        if ( ! $charity ) {
            // Fallback: charity_id might be a CPT post ID (not a DB row)
            $charity = self::get_charity_by_post( $raffle->charity_id );
            if ( ! $charity ) {
                return null;
            }
        }

        return array(
            'charity' => $charity,
            'mode'    => $raffle->charity_mode,
            'percent' => (int) $raffle->charity_percent,
        );
    }

    public static function get_live_raised_estimate( $raffle ) {
        if ( ! $raffle || empty( $raffle->charity_id ) || $raffle->charity_mode === 'none' ) {
            return 0.0;
        }

        $gross = (float) $raffle->sold_tickets * (float) $raffle->ticket_price;
        $percent = (int) ( $raffle->charity_percent ?? 100 );
        return round( $gross * ( $percent / 100 ), 2 );
    }

    /**
     * Resolve a charity identifier into every ID that raffles.charity_id might
     * store for it. Charities can be referenced by either their
     * raffle_charities DB row id OR their raffle_charity CPT post id, depending
     * on which code path created the association. This helper returns both
     * (de-duplicated, truthy) so callers can `WHERE charity_id IN (...)`.
     *
     * @param int $charity_id  Either a DB row id or a CPT post id.
     * @return int[] De-duplicated list of IDs that should match this charity.
     */
    public static function resolve_charity_ids( $charity_id ) {
        global $wpdb;
        $charity_id = absint( $charity_id );
        if ( ! $charity_id ) {
            return array();
        }

        $ids = array( $charity_id );

        // Is it a DB row id? If so, find the matching CPT post id by slug.
        $charities_table = $wpdb->prefix . 'raffle_charities';
        $charities_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $charities_table ) ) === $charities_table;
        if ( $charities_exists ) {
            $db_charity = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, slug FROM {$charities_table} WHERE id = %d",
                $charity_id
            ) );
            if ( $db_charity && ! empty( $db_charity->slug ) ) {
                $post_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'raffle_charity' LIMIT 1",
                    $db_charity->slug
                ) );
                if ( $post_id ) {
                    $ids[] = $post_id;
                }
            }
        }

        // Is it a CPT post id? If so, find the matching DB row id by slug.
        $post = get_post( $charity_id );
        if ( $post && $post->post_type === 'raffle_charity' ) {
            $slug = $post->post_name ?: sanitize_title( $post->post_title );
            if ( $charities_exists ) {
                $db_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$charities_table} WHERE slug = %s",
                    $slug
                ) );
                if ( $db_id ) {
                    $ids[] = $db_id;
                }
            }
        }

        return array_values( array_unique( array_filter( $ids, 'absint' ) ) );
    }

    public static function calculate_total_raised_for_charity( $charity_id ) {
        global $wpdb;

        $ids = self::resolve_charity_ids( $charity_id );
        if ( empty( $ids ) ) {
            return 0.0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        // Table-existence guards so this function never fatal-errors when the
        // allocations table is missing (it returns 0 for the committed portion
        // and still computes the live portion from wp_raffles).
        $allocations_table = $wpdb->prefix . 'raffle_charity_allocations';
        $allocations_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $allocations_table ) ) === $allocations_table;

        // Committed allocations (from drawn raffles).
        $committed = 0.0;
        if ( $allocations_exists ) {
            $committed = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(a.allocated_amount), 0)
                 FROM {$allocations_table} a
                 WHERE a.charity_id IN ($placeholders)",
                ...$ids
            ) );
        }

        // Live estimates from raffles without committed allocations (excluding
        // drafts). Compute directly from wp_raffles so this is correct even
        // when the allocations table is missing or empty.
        if ( $allocations_exists ) {
            $active_raffles = $wpdb->get_results( $wpdb->prepare(
                "SELECT r.sold_tickets, r.ticket_price, r.charity_percent
                 FROM {$wpdb->prefix}raffles r
                 LEFT JOIN {$allocations_table} a ON r.id = a.raffle_id
                 WHERE r.charity_id IN ($placeholders)
                   AND r.charity_mode != 'none'
                   AND r.status != 'draft'
                   AND a.raffle_id IS NULL",
                ...$ids
            ) );
        } else {
            $active_raffles = $wpdb->get_results( $wpdb->prepare(
                "SELECT r.sold_tickets, r.ticket_price, r.charity_percent
                 FROM {$wpdb->prefix}raffles r
                 WHERE r.charity_id IN ($placeholders)
                   AND r.charity_mode != 'none'
                   AND r.status != 'draft'",
                ...$ids
            ) );
        }

        $live_total = 0.0;
        foreach ( $active_raffles as $r ) {
            $gross = (float) $r->sold_tickets * (float) $r->ticket_price;
            $pct   = (int) $r->charity_percent;
            $live_total += round( $gross * ( $pct / 100 ), 2 );
        }

        return $committed + $live_total;
    }

    public static function get_allocation( $raffle_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT a.*, c.name as charity_name, c.logo_url, c.registration_number
             FROM {$wpdb->prefix}raffle_charity_allocations a
             LEFT JOIN {$wpdb->prefix}raffle_charities c ON a.charity_id = c.id
             WHERE a.raffle_id = %d",
            absint( $raffle_id )
        ) );
    }

    /* ===================================================================
       Allocation Snapshot (called at draw time)
       =================================================================== */

    public static function snapshot_allocation( $raffle_id ) {
        global $wpdb;

        $existing = self::get_allocation( $raffle_id );
        if ( $existing ) {
            return $existing;
        }

        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d",
            absint( $raffle_id )
        ) );

        if ( ! $raffle || $raffle->charity_mode === 'none' || ! $raffle->charity_id ) {
            return false;
        }

        $gross = (float) $raffle->sold_tickets * (float) $raffle->ticket_price;
        $percent = (int) $raffle->charity_percent;
        $amount  = round( $gross * ( $percent / 100 ), 2 );

        $proof = hash_hmac( 'sha256', $raffle_id . '|' . $gross . '|' . $amount, wp_salt( 'auth' ) );

        $wpdb->insert(
            $wpdb->prefix . 'raffle_charity_allocations',
            array(
                'raffle_id'          => $raffle_id,
                'charity_id'         => $raffle->charity_id,
                'gross_revenue'      => $gross,
                'prize_value'        => $raffle->prize_value,
                'net_proceeds'       => $gross,
                'allocation_percent' => $percent,
                'allocated_amount'   => $amount,
                'status'             => 'pending',
                'fairness_proof'     => $proof,
                'created_at'         => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%f', '%f', '%f', '%d', '%f', '%s', '%s', '%s' )
        );

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( $raffle_id, 'charity_allocation_snapshot', array(
                'charity_id' => $raffle->charity_id,
                'amount'     => $amount,
                'gross'      => $gross,
                'percent'    => $percent,
            ), $proof );
        }

        // Recalculate charity total raised
        $live = self::calculate_total_raised_for_charity( $raffle->charity_id );
        // Resolve DB row ID to update DB table
        $db_charity = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}raffle_charities WHERE id = %d",
            absint( $raffle->charity_id )
        ) );
        if ( ! $db_charity ) {
            $post = get_post( absint( $raffle->charity_id ) );
            if ( $post && $post->post_type === 'raffle_charity' ) {
                $slug = $post->post_name ?: sanitize_title( $post->post_title );
                $db_charity = $wpdb->get_row( $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}raffle_charities WHERE slug = %s",
                    $slug
                ) );
            }
        }
        if ( $db_charity ) {
            $wpdb->update(
                $wpdb->prefix . 'raffle_charities',
                array( 'total_raised' => $live ),
                array( 'id' => $db_charity->id ),
                array( '%f' ),
                array( '%d' )
            );
        }

        return self::get_allocation( $raffle_id );
    }

    /* ===================================================================
       Disbursement (admin operator action)
       =================================================================== */

    public static function mark_disbursed( $raffle_id, $reference = '' ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'unauthorized', 'Unauthorized.' );
        }

        global $wpdb;
        $allocation = self::get_allocation( $raffle_id );
        if ( ! $allocation ) {
            return new WP_Error( 'no_allocation', 'No allocation found for this raffle.' );
        }
        if ( $allocation->status === 'disbursed' ) {
            return new WP_Error( 'already_disbursed', 'This allocation has already been disbursed.' );
        }

        $wpdb->update(
            $wpdb->prefix . 'raffle_charity_allocations',
            array(
                'status'                => 'disbursed',
                'disbursed_at'          => current_time( 'mysql' ),
                'disbursement_reference' => sanitize_text_field( $reference ),
            ),
            array( 'id' => $allocation->id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( class_exists( 'Raffle_Audit' ) ) {
            Raffle_Audit::log( $raffle_id, 'charity_disbursed', array(
                'charity_id' => $allocation->charity_id,
                'amount'     => $allocation->allocated_amount,
                'reference'  => $reference,
            ), get_current_user_id() );
        }

        return true;
    }

    /* ===================================================================
       Display helpers / shortcode
       =================================================================== */

    /**
     * Shortcode: [raffle_charities] — charity directory + live totals.
     * Totals are rendered server-side and refreshed client-side every 60s so a
     * new ticket purchase reflects on the grid without a page reload.
     */
    public function render_charities_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'columns' => '3',
        ), $atts, 'raffle_charities' );

        $cols = max( 1, min( 6, absint( $atts['columns'] ) ) );
        $charities = self::get_active_charities();

        if ( empty( $charities ) ) {
            return '<div class="raffle-charities-empty" style="text-align:center;padding:40px;color:var(--wpr-text-muted,#6b7280);">' . esc_html__( 'No charities found yet.', 'wpraffle' ) . '</div>';
        }

        // Localise the nonce + ajax url + currency symbol for the polling script.
        $currency = '';
        if ( function_exists( 'wpr_currency_symbol' ) ) {
            $currency = wpr_currency_symbol();
        }
        wp_localize_script( 'raffle-public', 'raffleCharities', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'raffle_charity_totals' ),
            'symbol'   => $currency,
        ) );

        ob_start();
        ?>
        <div class="raffle-charities-grid" data-live="1" style="display:grid;grid-template-columns:repeat(<?php echo esc_attr( $cols ); ?>,1fr);gap:20px;">
            <?php foreach ( $charities as $c ) :
                // live_raised is authoritative (computed directly from wp_raffles).
                $total = (float) ( $c->live_raised ?? ( $c->total_raised ?? 0 ) );
            ?>
                <div class="raffle-charity-card" data-charity-id="<?php echo esc_attr( (int) $c->id ); ?>" style="background:var(--wpr-bg-surface,#fff);border:1px solid var(--wpr-border-color,#e5e7eb);border-radius:var(--wpr-radius,12px);padding:var(--wpr-card-padding,20px);text-align:center;">
                    <?php if ( ! empty( $c->logo_url ) ) : ?>
                        <img src="<?php echo esc_url( $c->logo_url ); ?>" alt="<?php echo esc_attr( $c->name ); ?>" style="width:64px;height:64px;object-fit:contain;margin:0 auto 12px;border-radius:8px;">
                    <?php else : ?>
                        <div style="margin:0 auto 12px;color:var(--wpr-accent,#6c5ce7);"><?php echo wpr_get_icon( 'gift', 'wpr-icon--2xl' ); ?></div>
                    <?php endif; ?>
                    <h3 style="margin:0 0 4px;font-size:16px;color:var(--wpr-text-primary,#1f2937);"><?php echo esc_html( $c->name ); ?></h3>
                    <?php if ( ! empty( $c->registration_number ) ) : ?>
                        <div style="font-size:11px;color:var(--wpr-text-muted,#6b7280);margin-bottom:8px;">Reg: <?php echo esc_html( $c->registration_number ); ?></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $c->description ) ) : ?>
                        <p style="font-size:13px;color:var(--wpr-text-secondary,#4b5563);line-height:1.5;margin:8px 0;"><?php echo esc_html( wp_trim_words( $c->description, 20 ) ); ?></p>
                    <?php endif; ?>
                    <div class="raffle-charity-total-box" style="background:var(--wpr-accent-bg,#ecfdf5);border:1px solid var(--wpr-accent-border,#a7f3d0);border-radius:var(--wpr-radius-sm,8px);padding:8px;margin-top:10px;">
                        <div style="font-size:11px;color:var(--wpr-accent-text,#065f46);text-transform:uppercase;font-weight:600;letter-spacing:0.04em;"><?php esc_html_e( 'Total Raised', 'wpraffle' ); ?></div>
                        <div class="raffle-charity-total-amount" data-raw="<?php echo esc_attr( $total ); ?>" style="font-size:18px;font-weight:800;color:var(--wpr-accent-text-dark,#065f46);"><?php echo esc_html( wpr_price( $total, 0 ) ); ?></div>
                    </div>
                    <?php if ( ! empty( $c->website ) ) : ?>
                        <a href="<?php echo esc_url( $c->website ); ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:4px;margin-top:10px;font-size:12px;color:var(--wpr-accent,#059669);font-weight:600;"><?php echo wpr_get_icon( 'arrow-right', 'wpr-icon--xs' ); ?> <?php esc_html_e( 'Visit website', 'wpraffle' ); ?></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: return live charity totals for grid polling. Public (non-sensitive
     * data); a nonce is still verified when present for cache-busting.
     */
    public static function ajax_get_charity_totals() {
        check_ajax_referer( 'raffle_charity_totals', 'nonce' );

        $ids_param = isset( $_POST['charity_ids'] ) ? wp_unslash( $_POST['charity_ids'] ) : '';
        $ids = array();
        if ( is_array( $ids_param ) ) {
            foreach ( $ids_param as $i ) {
                $ids[] = absint( $i );
            }
        } elseif ( is_string( $ids_param ) && $ids_param !== '' ) {
            foreach ( explode( ',', $ids_param ) as $i ) {
                $ids[] = absint( $i );
            }
        }
        $ids = array_filter( array_unique( $ids ) );

        $out = array();
        foreach ( $ids as $cid ) {
            $out[ $cid ] = (float) self::calculate_total_raised_for_charity( $cid );
        }

        wp_send_json_success( array( 'totals' => $out ) );
    }

    /* ===================================================================
       Cron: refresh charity totals from allocations
       =================================================================== */

    public static function refresh_all_totals() {
        global $wpdb;

        // Guard: if the charity tables don't exist, there's nothing to refresh.
        // (The v10 backstop migration creates them, but never fatal on a
        // half-migrated install.)
        $charities_table = $wpdb->prefix . 'raffle_charities';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $charities_table ) ) !== $charities_table ) {
            return;
        }

        // Step 1: Force-snapshot any finished charity raffles that don't have allocations yet.
        self::force_snapshot_all();

        // Step 2: Recalculate totals directly from the raffles table (bypass ID resolution issues).
        // Get all distinct charity_ids from the raffles table.
        $charity_ids = $wpdb->get_col(
            "SELECT DISTINCT charity_id FROM {$wpdb->prefix}raffles WHERE charity_id IS NOT NULL AND charity_mode != 'none'"
        );

        // Build a map: DB table row ID → total raised
        $totals_by_db_id = array();

        foreach ( $charity_ids as $raffle_charity_id ) {
            $raffle_charity_id = absint( $raffle_charity_id );

            // Sum committed allocations for this charity_id
            $committed = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(a.allocated_amount), 0)
                 FROM {$wpdb->prefix}raffle_charity_allocations a
                 INNER JOIN {$wpdb->prefix}raffles r ON a.raffle_id = r.id
                 WHERE r.charity_id = %d",
                $raffle_charity_id
            ) );

            // Sum live estimates from raffles without allocations (excluding drafts)
            $live_raffles = $wpdb->get_results( $wpdb->prepare(
                "SELECT r.sold_tickets, r.ticket_price, r.charity_percent
                 FROM {$wpdb->prefix}raffles r
                 LEFT JOIN {$wpdb->prefix}raffle_charity_allocations a ON r.id = a.raffle_id
                 WHERE r.charity_id = %d
                   AND r.charity_mode != 'none'
                   AND r.status != 'draft'
                   AND a.raffle_id IS NULL",
                $raffle_charity_id
            ) );

            $live_total = 0.0;
            foreach ( $live_raffles as $r ) {
                $gross = (float) $r->sold_tickets * (float) $r->ticket_price;
                $pct   = (int) $r->charity_percent;
                $live_total += round( $gross * ( $pct / 100 ), 2 );
            }

            $total = $committed + $live_total;

            // Resolve which DB table row this charity_id maps to
            $db_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}raffle_charities WHERE id = %d",
                $raffle_charity_id
            ) );

            if ( ! $db_id ) {
                // Try finding by CPT post slug
                $post = get_post( $raffle_charity_id );
                if ( $post && $post->post_type === 'raffle_charity' ) {
                    $slug = $post->post_name ?: sanitize_title( $post->post_title );
                    $db_id = $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}raffle_charities WHERE slug = %s",
                        $slug
                    ) );
                }
            }

            if ( $db_id ) {
                $db_id = (int) $db_id;
                if ( ! isset( $totals_by_db_id[ $db_id ] ) ) {
                    $totals_by_db_id[ $db_id ] = 0.0;
                }
                $totals_by_db_id[ $db_id ] += $total;
            }
        }

        // Update DB table
        foreach ( $totals_by_db_id as $db_id => $total ) {
            $wpdb->update(
                $wpdb->prefix . 'raffle_charities',
                array( 'total_raised' => $total ),
                array( 'id' => $db_id ),
                array( '%f' ),
                array( '%d' )
            );
        }
    }

    /**
     * Force-create allocation snapshots for all finished charity raffles that are missing them.
     */
    public static function force_snapshot_all() {
        global $wpdb;

        // Bail if the allocations table doesn't exist (v10 backstop will
        // create it; once it does, this runs on the next cron tick).
        $allocations_table = $wpdb->prefix . 'raffle_charity_allocations';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $allocations_table ) ) !== $allocations_table ) {
            return 0;
        }

        $missing = $wpdb->get_col(
            "SELECT r.id
             FROM {$wpdb->prefix}raffles r
             LEFT JOIN {$allocations_table} a ON r.id = a.raffle_id
             WHERE r.charity_id IS NOT NULL
               AND r.charity_mode != 'none'
               AND r.status = 'finished'
               AND a.raffle_id IS NULL"
        );

        foreach ( $missing as $raffle_id ) {
            self::snapshot_allocation( (int) $raffle_id );
        }

        return count( $missing );
    }
}
