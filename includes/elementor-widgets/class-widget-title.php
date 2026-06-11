<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Title extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_title'; }
    public function get_title() { return 'Raffle Title'; }
    public function get_icon() { return 'eicon-heading'; }
    public function get_categories() { return array( 'raffle-system' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );
        $this->add_control( 'html_tag', array(
            'label' => 'HTML Tag',
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4' ),
            'default' => 'h1',
        ) );
        $this->end_controls_section();
        $this->start_controls_section( 'style', array( 'label' => 'Style' ) );
        $this->add_control( 'color', array( 'label' => 'Text Color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#111827' ) );
        $this->add_control( 'font_size', array( 'label' => 'Font Size (px)', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 14, 'max' => 60 ) ), 'default' => array( 'size' => 24 ) ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle ) return;
        $s = $this->get_settings_for_display();
        $size = $s['font_size']['size'] ?? 24;
        echo '<' . esc_html( $s['html_tag'] ) . ' class="raffle-title-main" style="color:' . esc_attr( $s['color'] ) . ';font-size:' . esc_attr( $size ) . 'px;">' . esc_html( $raffle->title ) . '</' . esc_html( $s['html_tag'] ) . '>';
    }
}