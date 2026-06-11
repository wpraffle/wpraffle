<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Raffle_Widget_Stats_Header extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_stats_header'; }
    public function get_title() { return 'Raffle Stats Header'; }
    public function get_icon() { return 'eicon-info-circle'; }
    public function get_categories() { return array( 'raffle-system' ); }
    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );
        $this->add_control( 'show_max_tickets', array( 'label' => 'Show Max Tickets', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->add_control( 'show_available', array( 'label' => 'Show Available', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->add_control( 'show_draw_date', array( 'label' => 'Show Draw Date', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->end_controls_section();
    }
    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle ) return;
        $ctx = Raffle_Elementor::get_raffle_context( $raffle );
        $s = $this->get_settings_for_display();
        echo '<div class="raffle-stats-header">';
        if ( $s['show_max_tickets'] === 'yes' ) {
            echo '<div class="raffle-stat-box"><span class="raffle-stat-icon">&#127915;</span> Max ' . esc_html( $raffle->total_tickets ) . ' Tickets</div>';
        }
        if ( $s['show_available'] === 'yes' ) {
            echo '<div class="raffle-stat-box"><span class="raffle-stat-icon">&#9989;</span> ' . esc_html( $ctx['remaining'] ) . ' Available</div>';
        }
        if ( $s['show_draw_date'] === 'yes' && $raffle->draw_date ) {
            echo '<div class="raffle-stat-box"><span class="raffle-stat-icon">&#128197;</span> Draw: ' . esc_html( date( 'M j, Y g:i A', strtotime( $raffle->draw_date ) ) ) . '</div>';
        }
        echo '</div>';
    }
}
