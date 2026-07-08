<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Instant_Wins extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_instant_wins'; }
    public function get_title() { return 'Raffle Instant Wins'; }
    public function get_icon() { return 'eicon-star'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'instant', 'wins', 'prizes', 'rewards' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_responsive_control( 'columns', array(
            'label'   => __( 'Grid Columns', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
            'default' => '3',
            'selectors' => array( '{{WRAPPER}} .raffle-iw-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);' ),
        ) );
        $this->add_control( 'show_ticket_numbers', array(
            'label'       => __( 'Show Ticket Numbers', 'wpraffle' ),
            'type'        => \Elementor\Controls_Manager::SWITCHER,
            'default'     => 'no',
            'description' => __( 'Display the ticket numbers that trigger each instant win.', 'wpraffle' ),
        ) );
        $this->add_control( 'show_images', array(
            'label'       => __( 'Show Prize Images', 'wpraffle' ),
            'type'        => \Elementor\Controls_Manager::SWITCHER,
            'default'     => 'yes',
            'description' => __( 'Display the image attached to each prize / prize group.', 'wpraffle' ),
        ) );
        $this->add_control( 'group_by_prize_group', array(
            'label'       => __( 'Group by Prize Group', 'wpraffle' ),
            'type'        => \Elementor\Controls_Manager::SWITCHER,
            'default'     => 'no',
            'description' => __( 'Render prizes under their prize-group headings when groups are defined.', 'wpraffle' ),
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Card Style', 'wpraffle' ) ) );
        $this->add_control( 'card_bg', array(
            'label'     => __( 'Available Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array( '{{WRAPPER}} .raffle-iw-card' => 'background: {{VALUE}};' ),
        ) );
        $this->add_control( 'card_border', array(
            'label'     => __( 'Available Border', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#fbbf24',
            'selectors' => array( '{{WRAPPER}} .raffle-iw-card' => 'border-color: {{VALUE}};' ),
        ) );
        $this->add_control( 'claimed_bg', array(
            'label'     => __( 'Claimed Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#f9fafb',
            'selectors' => array( '{{WRAPPER}} .raffle-iw-card--claimed' => 'background: {{VALUE}};' ),
        ) );
        $this->add_control( 'claimed_border', array(
            'label'     => __( 'Claimed Border', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#e5e7eb',
            'selectors' => array( '{{WRAPPER}} .raffle-iw-card--claimed' => 'border-color: {{VALUE}};' ),
        ) );
        $this->add_responsive_control( 'card_radius', array(
            'label'      => __( 'Border Radius', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
            'default'    => array( 'size' => 8 ),
            'selectors'  => array( '{{WRAPPER}} .raffle-iw-card' => 'border-radius: {{SIZE}}px;' ),
        ) );
        $this->add_responsive_control( 'gap', array(
            'label'      => __( 'Gap', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
            'default'    => array( 'size' => 10 ),
            'selectors'  => array( '{{WRAPPER}} .raffle-iw-grid' => 'gap: {{SIZE}}px;' ),
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle ) return;
        $s = $this->get_settings_for_display();
        $show_images = ( $s['show_images'] !== 'no' );

        global $wpdb;
        $prizes = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}raffle_instant_wins WHERE raffle_id = %d ORDER BY ticket_number ASC",
            $raffle->id
        ) );

        if ( empty( $prizes ) ) {
            echo '<p style="color:#6b7280;text-align:center;">';
            wpr_icon( 'gift', 'wpr-icon--sm' );
            echo ' No instant win prizes.</p>';
            return;
        }

        // Load prize groups if grouping is enabled.
        $groups = array();
        if ( $s['group_by_prize_group'] === 'yes' ) {
            $group_rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}raffle_instant_win_groups WHERE raffle_id = %d ORDER BY created_at ASC",
                $raffle->id
            ) );
            foreach ( $group_rows as $gr ) {
                $groups[ (int) $gr->id ] = $gr;
            }
        }

        // Group by prize_name for quantity display (and by prize_group_id if enabled).
        $grouped = array();
        foreach ( $prizes as $prize ) {
            $bucket_key = ( $s['group_by_prize_group'] === 'yes' && ! empty( $prize->prize_group_id ) )
                ? 'grp_' . $prize->prize_group_id
                : 'name_' . $prize->prize_name;
            if ( ! isset( $grouped[ $bucket_key ] ) ) {
                $grouped[ $bucket_key ] = array(
                    'total'     => 0,
                    'won'       => 0,
                    'available' => 0,
                    'tickets'   => array(),
                    'name'      => $prize->prize_name,
                    'image_id'  => isset( $prize->image_id ) ? (int) $prize->image_id : 0,
                    'group_id'  => isset( $prize->prize_group_id ) ? (int) $prize->prize_group_id : 0,
                );
            }
            $grouped[ $bucket_key ]['total']++;
            // Prefer per-prize image if set.
            if ( empty( $grouped[ $bucket_key ]['image_id'] ) && ! empty( $prize->image_id ) ) {
                $grouped[ $bucket_key ]['image_id'] = (int) $prize->image_id;
            }
            if ( $prize->status === 'won' || $prize->status === 'claimed' ) {
                $grouped[ $bucket_key ]['won']++;
            } else {
                $grouped[ $bucket_key ]['available']++;
                $grouped[ $bucket_key ]['tickets'][] = $prize->ticket_number;
            }
        }

        // If grouping, render a heading per group.
        if ( $s['group_by_prize_group'] === 'yes' && ! empty( $groups ) ) {
            foreach ( $groups as $gid => $gr ) {
                echo '<h4 class="raffle-iw-group-title" style="margin:16px 0 8px;font-size:14px;font-weight:800;">' . esc_html( $gr->name ) . '</h4>';
                $this->render_group_grid( $grouped, 'grp_' . $gid, $s, $show_images, $gr );
            }
            // Any prizes not attached to a group.
            $ungrouped = array();
            foreach ( $grouped as $k => $g ) {
                if ( strpos( $k, 'grp_' ) !== 0 ) {
                    $ungrouped[ $k ] = $g;
                }
            }
            if ( ! empty( $ungrouped ) ) {
                echo '<h4 class="raffle-iw-group-title" style="margin:16px 0 8px;font-size:14px;font-weight:800;">Other prizes</h4>';
                $this->render_group_grid( $ungrouped, null, $s, $show_images, null );
            }
        } else {
            echo '<div class="raffle-iw-grid" style="display:grid;">';
            foreach ( $grouped as $group ) {
                $this->render_card( $group, $s, $show_images );
            }
            echo '</div>';
        }
    }

    /**
     * Render a grid of cards for one prize-group bucket.
     */
    private function render_group_grid( $grouped, $prefix, $s, $show_images, $group_row ) {
        echo '<div class="raffle-iw-grid" style="display:grid;">';
        foreach ( $grouped as $key => $group ) {
            if ( null !== $prefix && strpos( $key, $prefix ) !== 0 ) {
                continue;
            }
            // Inherit the group image if the prize doesn't have one.
            if ( empty( $group['image_id'] ) && $group_row && ! empty( $group_row->image_id ) ) {
                $group['image_id'] = (int) $group_row->image_id;
            }
            $this->render_card( $group, $s, $show_images );
        }
        echo '</div>';
    }

    /**
     * Render a single prize card.
     */
    private function render_card( $group, $s, $show_images ) {
        $all_claimed = $group['available'] === 0;
        $class       = $all_claimed ? 'raffle-iw-card raffle-iw-card--claimed' : 'raffle-iw-card';
        $remaining   = $group['total'] > 1 ? $group['available'] . ' of ' . $group['total'] . ' left' : '';

        echo '<div class="' . esc_attr( $class ) . '" style="border:2px solid;padding:12px;text-align:center;">';
        if ( $show_images && ! empty( $group['image_id'] ) ) {
            echo wp_get_attachment_image( (int) $group['image_id'], 'thumbnail', false, array( 'style' => 'width:60px;height:60px;object-fit:cover;border-radius:8px;margin:0 auto 8px;display:block;' ) );
        }
        echo '<div class="raffle-iw-name" style="font-weight:800;font-size:13px;">' . esc_html( $group['name'] ) . '</div>';
        if ( $remaining ) {
            echo '<div class="raffle-iw-remaining" style="font-size:11px;color:#ea580c;margin-top:4px;font-weight:600;">' . esc_html( $remaining ) . '</div>';
        }
        if ( $s['show_ticket_numbers'] === 'yes' && ! empty( $group['tickets'] ) ) {
            echo '<div class="raffle-iw-tickets" style="font-size:10px;color:#6b7280;margin-top:4px;">#' . esc_html( implode( ', #', $group['tickets'] ) ) . '</div>';
        }
        if ( $all_claimed ) {
            echo '<div style="font-size:10px;color:#9ca3af;margin-top:4px;font-weight:700;">ALL CLAIMED</div>';
        }
        echo '</div>';
    }

    protected function content_template() {
        ?>
        <div class="raffle-iw-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
            <div class="raffle-iw-card" style="background:#fff;border:2px solid #fbbf24;border-radius:8px;padding:12px;text-align:center;">
                <div style="font-weight:800;font-size:13px;color:#111827;">$50 Gift Card</div>
                <div style="font-size:11px;color:#ea580c;margin-top:4px;font-weight:600;">2 of 3 left</div>
            </div>
            <div class="raffle-iw-card" style="background:#fff;border:2px solid #fbbf24;border-radius:8px;padding:12px;text-align:center;">
                <div style="font-weight:800;font-size:13px;color:#111827;">Free Entry</div>
                <div style="font-size:11px;color:#ea580c;margin-top:4px;font-weight:600;">1 of 1 left</div>
            </div>
            <div class="raffle-iw-card raffle-iw-card--claimed" style="background:#f9fafb;border:2px solid #e5e7eb;border-radius:8px;padding:12px;text-align:center;">
                <div style="font-weight:800;font-size:13px;color:#9ca3af;">$10 Voucher</div>
                <div style="font-size:10px;color:#9ca3af;margin-top:4px;font-weight:700;">ALL CLAIMED</div>
            </div>
        </div>
        <?php
    }
}
