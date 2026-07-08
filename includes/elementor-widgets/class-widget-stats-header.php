<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Stats_Header extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_stats_header'; }
    public function get_title() { return 'Raffle Stats Header'; }
    public function get_icon() { return 'eicon-info-circle'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'stats', 'tickets', 'available', 'draw', 'date' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'show_max_tickets', array( 'label' => __( 'Show Max Tickets', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->add_control( 'show_available', array( 'label' => __( 'Show Available', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->add_control( 'show_draw_date', array( 'label' => __( 'Show Draw Date', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Box Style', 'wpraffle' ) ) );
        $this->add_control( 'box_bg', array(
            'label'     => __( 'Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array( '{{WRAPPER}} .raffle-stat-box' => 'background: {{VALUE}};' ),
        ) );
        $this->add_control( 'box_border', array(
            'label'     => __( 'Border Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#e5e7eb',
            'selectors' => array( '{{WRAPPER}} .raffle-stat-box' => 'border-color: {{VALUE}};' ),
        ) );
        $this->add_control( 'text_color', array(
            'label'     => __( 'Text Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#374151',
            'selectors' => array( '{{WRAPPER}} .raffle-stat-box' => 'color: {{VALUE}};' ),
        ) );
        $this->add_responsive_control( 'box_radius', array(
            'label'      => __( 'Border Radius', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
            'default'    => array( 'size' => 8 ),
            'selectors'  => array( '{{WRAPPER}} .raffle-stat-box' => 'border-radius: {{SIZE}}px;' ),
        ) );
        $this->add_responsive_control( 'box_padding', array(
            'label'      => __( 'Padding', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'default'    => array( 'top' => '12', 'right' => '16', 'bottom' => '12', 'left' => '16', 'unit' => 'px', 'isLinked' => false ),
            'selectors'  => array( '{{WRAPPER}} .raffle-stat-box' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle ) return;
        $ctx = Raffle_Elementor::get_raffle_context( $raffle );
        $s   = $this->get_settings_for_display();

        echo '<div class="raffle-stats-header">';
        if ( $s['show_max_tickets'] === 'yes' ) {
            echo '<div class="raffle-stat-box"><span class="raffle-stat-icon">';
            wpr_icon( 'ticket', 'wpr-icon--md' );
            echo '</span> Max ' . esc_html( $raffle->total_tickets ) . ' Tickets</div>';
        }
        if ( $s['show_available'] === 'yes' ) {
            echo '<div class="raffle-stat-box"><span class="raffle-stat-icon">';
            wpr_icon( 'check-circle', 'wpr-icon--md' );
            echo '</span> ' . esc_html( $ctx['remaining'] ) . ' Available</div>';
        }
        if ( $s['show_draw_date'] === 'yes' && $raffle->draw_date ) {
            echo '<div class="raffle-stat-box"><span class="raffle-stat-icon">';
            wpr_icon( 'calendar', 'wpr-icon--md' );
            echo '</span> Draw: ' . esc_html( date_i18n( 'M j, Y g:i A', strtotime( $raffle->draw_date ) ) ) . '</div>';
        }
        echo '</div>';
    }

    protected function content_template() {
        ?>
        <div class="raffle-stats-header" style="display:flex;gap:10px;flex-wrap:wrap;">
            <div class="raffle-stat-box" style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;color:#374151;">🎫 Max 1000 Tickets</div>
            <div class="raffle-stat-box" style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;color:#374151;">✓ 350 Available</div>
            <div class="raffle-stat-box" style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;color:#374151;">📅 Draw: Dec 31, 2026</div>
        </div>
        <?php
    }
}
