<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Price extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_price'; }
    public function get_title() { return 'Raffle Price'; }
    public function get_icon() { return 'eicon-price-tag'; }
    public function get_categories() { return array( 'raffle-system' ); }

    protected function register_controls() {
        $this->start_controls_section( 'style', array( 'label' => 'Style' ) );
        $this->add_control( 'price_color', array( 'label' => 'Price Color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#111827' ) );
        $this->add_control( 'label_color', array( 'label' => 'Label Color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#6b7280' ) );
        $this->add_control( 'label_text', array( 'label' => 'Label Text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'PER ENTRY' ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle ) return;
        $s = $this->get_settings_for_display();
        echo '<div class="raffle-price-row">';
        echo '<span class="raffle-price-value" style="color:' . esc_attr( $s['price_color'] ) . ';">' . esc_html( wpr_price( $raffle->ticket_price ) ) . '</span>';
        echo '<span class="raffle-price-label" style="color:' . esc_attr( $s['label_color'] ) . ';">' . esc_html( $s['label_text'] ) . '</span>';
        echo '</div>';
    }
}