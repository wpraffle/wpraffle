<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Raffle_Widget_Description extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_description'; }
    public function get_title() { return 'Raffle Description'; }
    public function get_icon() { return 'eicon-text'; }
    public function get_categories() { return array( 'raffle-system' ); }
    protected function register_controls() {
        $this->start_controls_section( 'style', array( 'label' => 'Style' ) );
        $this->add_control( 'text_color', array( 'label' => 'Text Color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#4b5563' ) );
        $this->add_control( 'font_size', array( 'label' => 'Font Size (px)', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 12, 'max' => 20 ) ), 'default' => array( 'size' => 14 ) ) );
        $this->end_controls_section();
    }
    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle || empty( $raffle->description ) ) return;
        $s = $this->get_settings_for_display();
        $size = $s['font_size']['size'] ?? 14;
        echo '<div class="raffle-description-card" style="background:#f9fafb;border:1px solid #f3f4f6;border-radius:10px;padding:16px;">';
        echo '<div style="color:' . esc_attr( $s['text_color'] ) . ';font-size:' . esc_attr( $size ) . 'px;line-height:1.7;">' . wp_kses_post( $raffle->description ) . '</div>';
        echo '</div>';
    }
}
