<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Raffle_Widget_Instant_Wins extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_instant_wins'; }
    public function get_title() { return 'Raffle Instant Wins'; }
    public function get_icon() { return 'eicon-star'; }
    public function get_categories() { return array( 'raffle-system' ); }
    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );
        $this->add_control( 'columns', array( 'label' => 'Grid Columns', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( '2' => '2', '3' => '3', '4' => '4' ), 'default' => '3' ) );
        $this->add_control( 'show_ticket_numbers', array( 'label' => 'Show Ticket Numbers', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->end_controls_section();
    }
    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle ) return;
        $s = $this->get_settings_for_display();
        global $wpdb;
        $prizes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffle_instant_wins WHERE raffle_id = %d ORDER BY ticket_number ASC", $raffle->id ) );
        if ( empty( $prizes ) ) { echo '<p style="color:#6b7280;text-align:center;">' . wpr_get_icon( 'gift', 'wpr-icon--sm' ) . ' No instant win prizes.</p>'; return; }
        $cols = $s['columns'];
        // Group by prize_name for quantity display
        $grouped = array();
        foreach ( $prizes as $prize ) {
            $key = $prize->prize_name;
            if ( ! isset( $grouped[ $key ] ) ) {
                $grouped[ $key ] = array( 'total' => 0, 'won' => 0, 'available' => 0 );
            }
            $grouped[ $key ]['total']++;
            if ( $prize->status === 'won' || $prize->status === 'claimed' ) {
                $grouped[ $key ]['won']++;
            } else {
                $grouped[ $key ]['available']++;
            }
        }
        echo '<div style="display:grid;grid-template-columns:repeat(' . esc_attr( $cols ) . ',1fr);gap:10px;">';
        foreach ( $grouped as $prize_name => $group ) {
            $all_claimed = $group['available'] === 0;
            $remaining = $group['total'] > 1 ? $group['available'] . ' of ' . $group['total'] . ' left' : '';
            echo '<div style="background:' . ( $all_claimed ? '#f9fafb' : '#fff' ) . ';border:2px solid ' . ( $all_claimed ? '#e5e7eb' : '#fbbf24' ) . ';border-radius:8px;padding:12px;text-align:center;">';
            echo '<div style="font-weight:800;font-size:13px;color:' . ( $all_claimed ? '#9ca3af' : '#111827' ) . ';">' . esc_html( $prize_name ) . '</div>';
            if ( $remaining ) {
                echo '<div style="font-size:11px;color:' . ( $all_claimed ? '#9ca3af' : '#ea580c' ) . ';margin-top:4px;font-weight:600;">' . esc_html( $remaining ) . '</div>';
            }
            if ( $all_claimed ) echo '<div style="font-size:10px;color:#9ca3af;margin-top:4px;font-weight:700;">ALL CLAIMED</div>';
            echo '</div>';
        }
        echo '</div>';
    }
}
