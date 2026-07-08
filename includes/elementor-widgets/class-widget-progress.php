<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Progress extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_progress'; }
    public function get_title() { return 'Raffle Progress'; }
    public function get_icon() { return 'eicon-skill-bar'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'progress', 'bar', 'sold', 'tickets' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'show_labels', array(
            'label'   => __( 'Show Labels', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Style', 'wpraffle' ) ) );
        $this->add_control( 'bar_color', array(
            'label'     => __( 'Bar Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#10b981',
            'selectors' => array(
                '{{WRAPPER}} .raffle-progress-bar-inner' => 'background: {{VALUE}};',
                '{{WRAPPER}} .raffle-progress-label-percent' => 'color: {{VALUE}};',
            ),
        ) );
        $this->add_control( 'track_color', array(
            'label'     => __( 'Track Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#e5e7eb',
            'selectors' => array( '{{WRAPPER}} .raffle-progress-bar-wrap' => 'background: {{VALUE}};' ),
        ) );
        $this->add_responsive_control( 'bar_height', array(
            'label'      => __( 'Bar Height', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 4, 'max' => 30 ) ),
            'default'    => array( 'size' => 8 ),
            'selectors'  => array( '{{WRAPPER}} .raffle-progress-bar-wrap' => 'height: {{SIZE}}px;' ),
        ) );
        $this->add_responsive_control( 'bar_radius', array(
            'label'      => __( 'Border Radius', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
            'default'    => array( 'size' => 4 ),
            'selectors'  => array(
                '{{WRAPPER}} .raffle-progress-bar-wrap' => 'border-radius: {{SIZE}}px;',
                '{{WRAPPER}} .raffle-progress-bar-inner' => 'border-radius: {{SIZE}}px;',
            ),
        ) );
        $this->add_control( 'label_color', array(
            'label'     => __( 'Numbers Label Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#6b7280',
            'selectors' => array( '{{WRAPPER}} .raffle-progress-label-numbers' => 'color: {{VALUE}};' ),
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle ) return;
        $ctx = Raffle_Elementor::get_raffle_context( $raffle );
        $s   = $this->get_settings_for_display();
        $p   = $ctx['progress'];

        echo '<div class="raffle-progress-box-custom">';
        if ( $s['show_labels'] === 'yes' ) {
            echo '<div class="raffle-progress-meta-row">'
                . '<span class="raffle-progress-label-percent">Sold: ' . esc_html( $p ) . '%</span>'
                . '<span class="raffle-progress-label-numbers">' . esc_html( $raffle->sold_tickets ) . ' of ' . esc_html( $raffle->total_tickets ) . '</span>'
                . '</div>';
        }
        echo '<div class="raffle-progress-bar-wrap"><div class="raffle-progress-bar-inner" style="width:' . esc_attr( $p ) . '%;"></div></div></div>';
    }

    protected function content_template() {
        ?>
        <div class="raffle-progress-box-custom">
            <div class="raffle-progress-meta-row" style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;">
                <span class="raffle-progress-label-percent" style="color:#10b981;font-weight:600;">Sold: 65%</span>
                <span class="raffle-progress-label-numbers" style="color:#6b7280;">650 of 1000</span>
            </div>
            <div class="raffle-progress-bar-wrap" style="background:#e5e7eb;height:8px;border-radius:4px;">
                <div class="raffle-progress-bar-inner" style="width:65%;background:#10b981;height:8px;border-radius:4px;"></div>
            </div>
        </div>
        <?php
    }
}
