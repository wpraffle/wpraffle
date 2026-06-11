<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Raffle_Widget_Progress extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_progress'; }
    public function get_title() { return 'Raffle Progress'; }
    public function get_icon() { return 'eicon-skill-bar'; }
    public function get_categories() { return array( 'raffle-system' ); }
    protected function register_controls() {
        $this->start_controls_section( 'style', array( 'label' => 'Style' ) );
        $this->add_control( 'bar_color', array( 'label' => 'Bar Color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#10b981' ) );
        $this->add_control( 'show_labels', array( 'label' => 'Show Labels', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->end_controls_section();
    }
    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle ) return;
        $ctx = Raffle_Elementor::get_raffle_context( $raffle );
        $s = $this->get_settings_for_display();
        $p = $ctx['progress'];
        echo '<div class="raffle-progress-box-custom">';
        if ( $s['show_labels'] === 'yes' ) {
            echo '<div class="raffle-progress-meta-row"><span class="raffle-progress-label-percent" style="color:' . esc_attr( $s['bar_color'] ) . ';">Sold: ' . esc_html( $p ) . '%</span><span class="raffle-progress-label-numbers">' . esc_html( $raffle->sold_tickets ) . ' of ' . esc_html( $raffle->total_tickets ) . '</span></div>';
        }
        echo '<div class="raffle-progress-bar-wrap"><div class="raffle-progress-bar-inner" style="width:' . esc_attr( $p ) . '%;background:' . esc_attr( $s['bar_color'] ) . ';"></div></div></div>';
    }
}
