<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Raffle_Widget_Trust extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_trust'; }
    public function get_title() { return 'Raffle Trust Badges'; }
    public function get_icon() { return 'eicon-shield'; }
    public function get_categories() { return array( 'raffle-system' ); }
    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );
        $this->add_control( 'show_secure', array( 'label' => 'Show Secure Purchase', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->add_control( 'show_confirmation', array( 'label' => 'Show Email Confirmation', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->add_control( 'show_random', array( 'label' => 'Show Random Draw', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->end_controls_section();
    }
    protected function render() {
        $s = $this->get_settings_for_display();
        echo '<div style="display:flex;justify-content:center;align-items:center;gap:16px;flex-wrap:wrap;">';
        if ( $s['show_secure'] === 'yes' ) echo '<span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:var(--wpr-text-muted,#6b7280);">' . wpr_get_icon( 'lock', 'wpr-icon--sm' ) . ' Secure Purchase</span>';
        if ( $s['show_confirmation'] === 'yes' ) echo '<span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:var(--wpr-text-muted,#6b7280);">' . wpr_get_icon( 'mail', 'wpr-icon--sm' ) . ' Email Confirmation</span>';
        if ( $s['show_random'] === 'yes' ) echo '<span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:var(--wpr-text-muted,#6b7280);">' . wpr_get_icon( 'zap', 'wpr-icon--sm' ) . ' Random Draw</span>';
        echo '</div>';
    }
}
