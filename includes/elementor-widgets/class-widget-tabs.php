<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Raffle_Widget_Tabs extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_tabs'; }
    public function get_title() { return 'Raffle Entry Tabs'; }
    public function get_icon() { return 'eicon-tabs'; }
    public function get_categories() { return array( 'raffle-system' ); }
    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );
        $this->add_control( 'online_label', array( 'label' => 'Online Tab Label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'ONLINE ENTRY' ) );
        $this->add_control( 'postal_label', array( 'label' => 'Postal Tab Label', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'POSTAL ENTRY' ) );
        $this->add_control( 'postal_address', array( 'label' => 'Postal Address', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => 'Send entries to: P.O. Box 123, City, Postcode' ) );
        $this->end_controls_section();
    }
    protected function render() {
        $s = $this->get_settings_for_display();
        echo '<div class="raffle-entry-tabs">';
        echo '<button type="button" class="raffle-tab-btn active" data-tab="online">' . esc_html( $s['online_label'] ) . '</button>';
        echo '<button type="button" class="raffle-tab-btn" data-tab="postal">' . esc_html( $s['postal_label'] ) . '</button>';
        echo '</div>';
        echo '<div class="raffle-tab-contents">';
        echo '<div class="raffle-tab-pane" data-pane="postal" style="display:none;">';
        echo '<div class="raffle-postal-info-card">' . nl2br( esc_html( $s['postal_address'] ) ) . '</div>';
        echo '</div></div>';
    }
}
