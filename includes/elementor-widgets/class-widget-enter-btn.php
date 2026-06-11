<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Raffle_Widget_Enter_Btn extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_enter_btn'; }
    public function get_title() { return 'Raffle Enter Button'; }
    public function get_icon() { return 'eicon-button'; }
    public function get_categories() { return array( 'raffle-system' ); }
    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );
        $this->add_control( 'button_text', array( 'label' => 'Button Text', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'ENTER COMPETITION' ) );
        $this->end_controls_section();
        $this->start_controls_section( 'style', array( 'label' => 'Style' ) );
        $this->add_control( 'bg_color', array( 'label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#d4a017' ) );
        $this->add_control( 'text_color', array( 'label' => 'Text Color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#ffffff' ) );
        $this->add_control( 'border_radius', array( 'label' => 'Border Radius', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ), 'default' => array( 'size' => 8 ) ) );
        $this->end_controls_section();
    }
    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle ) return;
        $ctx = Raffle_Elementor::get_raffle_context( $raffle );
        $s = $this->get_settings_for_display();
        $r = $s['border_radius']['size'] ?? 8;
        if ( $ctx['remaining'] <= 0 || $raffle->status !== 'active' ) {
            echo '<div class="raffle-sold-out-badge">SOLD OUT</div>';
            return;
        }
        echo '<div class="raffle-enter-action-wrapper">';
        echo '<button type="button" class="raffle-enter-comp-btn" style="background:linear-gradient(135deg,' . esc_attr( $s['bg_color'] ) . ' 0%,#b8860b 100%);color:' . esc_attr( $s['text_color'] ) . '!important;border-radius:' . esc_attr( $r ) . 'px;">' . esc_html( $s['button_text'] ) . '</button>';
        echo '</div>';
    }
}
