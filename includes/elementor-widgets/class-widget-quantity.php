<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Raffle_Widget_Quantity extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_quantity'; }
    public function get_title() { return 'Raffle Quantity Selector'; }
    public function get_icon() { return 'eicon-slider-horizontal'; }
    public function get_categories() { return array( 'raffle-system' ); }
    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );
        $this->add_control( 'show_pills', array( 'label' => 'Show Quick Select Pills', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->add_control( 'show_slider', array( 'label' => 'Show Slider', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->add_control( 'show_manual', array( 'label' => 'Show Manual Input', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->end_controls_section();
    }
    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle ) return;
        $ctx = Raffle_Elementor::get_raffle_context( $raffle );
        $s = $this->get_settings_for_display();
        $max = min( $ctx['max_tickets'], $ctx['remaining'] );
        $pkgs = $ctx['packages'];
        $disp = array_filter( $pkgs, function( $q ) use ( $max ) { return $q >= 1 && $q <= $max; } );
        if ( empty( $disp ) ) $disp = array_filter( array( 5, 10, 15, 25 ), function( $q ) use ( $max ) { return $q >= 1 && $q <= $max; } );
        if ( $s['show_pills'] === 'yes' && ! empty( $disp ) ) {
            echo '<div class="raffle-quick-select-qty"><span class="raffle-qty-heading">QUICK SELECT QUANTITY</span><div class="raffle-qty-pills-row">';
            foreach ( $disp as $q ) echo '<button type="button" class="raffle-qty-pill" data-qty="' . esc_attr( $q ) . '">' . esc_html( $q ) . '</button>';
            echo '</div></div>';
        }
        if ( $s['show_slider'] === 'yes' ) {
            echo '<div class="raffle-slider-qty-selector"><div class="raffle-slider-controls">';
            echo '<button type="button" class="raffle-slider-btn minus">-</button>';
            echo '<div class="raffle-slider-track-wrap"><input type="range" id="raffle-qty-range-slider" min="1" max="' . esc_attr( $max ) . '" value="1"><div class="raffle-slider-tooltip" id="raffle-qty-slider-tooltip">1 TICKET</div></div>';
            echo '<button type="button" class="raffle-slider-btn plus">+</button></div>';
            if ( $s['show_manual'] === 'yes' ) echo '<div class="raffle-manual-qty-input-wrap"><input type="number" id="raffle-manual-qty-num" min="1" max="' . esc_attr( $max ) . '" value="1"></div>';
            echo '</div>';
        }
    }
}
