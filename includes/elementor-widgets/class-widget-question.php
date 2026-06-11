<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Raffle_Widget_Question extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_question'; }
    public function get_title() { return 'Raffle Skill Question'; }
    public function get_icon() { return 'eicon-form-horizontal'; }
    public function get_categories() { return array( 'raffle-system' ); }
    protected function register_controls() {
        $this->start_controls_section( 'style', array( 'label' => 'Style' ) );
        $this->add_control( 'show_question', array( 'label' => 'Show Question', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->end_controls_section();
    }
    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle || empty( $raffle->question ) ) return;
        $s = $this->get_settings_for_display();
        if ( $s['show_question'] !== 'yes' ) return;
        $options = json_decode( $raffle->options, true );
        if ( ! $options ) $options = array();
        echo '<div class="raffle-question-wrapper">';
        echo '<h3 class="raffle-question-title">Skill Question</h3>';
        echo '<p style="font-weight:600;margin-bottom:12px;">' . esc_html( $raffle->question ) . '</p>';
        echo '<div class="raffle-question-options">';
        foreach ( $options as $i => $opt ) {
            echo '<label class="raffle-question-option-card"><input type="radio" name="raffle_answer" value="' . esc_attr( $i ) . '"><span class="raffle-question-option-dot"></span><span class="raffle-question-option-text">' . esc_html( $opt ) . '</span></label>';
        }
        echo '</div></div>';
    }
}
