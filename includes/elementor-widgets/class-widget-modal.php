<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Raffle_Widget_Modal extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_modal'; }
    public function get_title() { return 'Raffle Purchase Modal'; }
    public function get_icon() { return 'eicon-popup'; }
    public function get_categories() { return array( 'raffle-system' ); }
    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );
        $this->add_control( 'info', array( 'label' => 'This widget renders the purchase modal. Place it once on the page.', 'type' => \Elementor\Controls_Manager::RAW_HTML ) );
        $this->end_controls_section();
    }
    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle ) return;
        echo '<div id="raffle-modal-overlay" class="raffle-modal-overlay" style="display:none;">';
        echo '<div class="raffle-modal-box">';
        echo '<button type="button" class="raffle-modal-close" id="raffle-modal-close-btn">&times;</button>';
        echo '<h2 style="font-size:20px;font-weight:800;margin:0 0 20px;">Complete Your Entry</h2>';
        echo '<form id="raffle-purchase-form-elementor">';
        echo '<input type="hidden" name="raffle_id" value="' . esc_attr( $raffle->id ) . '">';
        echo '<input type="hidden" name="quantity" id="raffle-modal-qty" value="1">';
        echo '<div style="margin-bottom:12px;"><label style="font-weight:700;font-size:13px;display:block;margin-bottom:4px;">Full Name *</label><input type="text" name="buyer_name" required style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></div>';
        echo '<div style="margin-bottom:12px;"><label style="font-weight:700;font-size:13px;display:block;margin-bottom:4px;">Email *</label><input type="email" name="buyer_email" required style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></div>';
        echo '<div style="margin-bottom:12px;"><label style="font-weight:700;font-size:13px;display:block;margin-bottom:4px;">Phone</label><input type="tel" name="buyer_phone" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></div>';
        echo '<button type="submit" class="raffle-enter-comp-btn" style="margin-top:8px;">PROCEED TO PAYMENT</button>';
        echo '</form></div></div>';
    }
}
