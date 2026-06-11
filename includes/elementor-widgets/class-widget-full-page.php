<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Raffle_Widget_Full_Page extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_full_page'; }
    public function get_title() { return 'Raffle Full Page'; }
    public function get_icon() { return 'eicon-page-list'; }
    public function get_categories() { return array( 'raffle-system' ); }
    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );
        $this->add_control( 'info', array( 'label' => 'Renders the complete default raffle layout using the raffle-display.php template.', 'type' => \Elementor\Controls_Manager::RAW_HTML ) );
        $this->end_controls_section();
    }
    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle ) { echo '<p>No raffle found for this product.</p>'; return; }
        $template_path = RAFFLE_SYSTEM_PATH . 'public/views/raffle-display.php';
        if ( file_exists( $template_path ) ) {
            set_query_var( 'raffle', $raffle );
            include $template_path;
        }
    }
}
