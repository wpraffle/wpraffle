<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Raffle_Widget_Countdown extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_countdown'; }
    public function get_title() { return 'Raffle Countdown'; }
    public function get_icon() { return 'eicon-countdown'; }
    public function get_categories() { return array( 'raffle-system' ); }
    protected function register_controls() {
        $this->start_controls_section( 'style', array( 'label' => 'Style' ) );
        $this->add_control( 'bg_color', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#111827' ) );
        $this->add_control( 'number_color', array( 'label' => 'Number Color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff' ) );
        $this->add_control( 'label_color', array( 'label' => 'Label Color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#9ca3af' ) );
        $this->end_controls_section();
    }
    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle || ! $raffle->draw_date ) { echo '<p style="color:#6b7280;text-align:center;">No draw date set.</p>'; return; }
        $s = $this->get_settings_for_display();
        echo '<div class="raffle-countdown-timer-inline" id="raffle-countdown-inline" data-draw-date="' . esc_attr( gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $raffle->draw_date ) ) ) . '" style="background:' . esc_attr( $s['bg_color'] ) . ';">';
        foreach ( array( 'days' => 'DAYS', 'hours' => 'HRS', 'minutes' => 'MINS', 'seconds' => 'SECS' ) as $k => $lbl ) {
            echo '<div class="raffle-cd-box"><span class="raffle-cd-num" id="cd-inline-' . esc_attr( $k ) . '" style="color:' . esc_attr( $s['number_color'] ) . ';">00</span><span class="raffle-cd-lbl" style="color:' . esc_attr( $s['label_color'] ) . ';">' . esc_html( $lbl ) . '</span></div>';
        }
        echo '</div>';
    }
}
