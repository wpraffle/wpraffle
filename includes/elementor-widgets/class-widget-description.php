<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Description extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_description'; }
    public function get_title() { return 'Raffle Description'; }
    public function get_icon() { return 'eicon-text'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'description', 'text', 'details' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Style', 'wpraffle' ) ) );
        $this->add_control( 'text_color', array(
            'label'     => __( 'Text Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#4b5563',
            'selectors' => array( '{{WRAPPER}} .raffle-description-text' => 'color: {{VALUE}};' ),
        ) );
        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'typography',
            'label'    => __( 'Typography', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .raffle-description-text',
            'fields_options' => array(
                'font_size'   => array( 'default' => array( 'unit' => 'px', 'size' => 14 ) ),
                'line_height' => array( 'default' => array( 'unit' => 'em', 'size' => 1.7 ) ),
            ),
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'card_style', array( 'label' => __( 'Card', 'wpraffle' ) ) );
        $this->add_control( 'card_bg', array(
            'label'     => __( 'Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#f9fafb',
            'selectors' => array( '{{WRAPPER}} .raffle-description-card' => 'background: {{VALUE}};' ),
        ) );
        $this->add_control( 'card_border', array(
            'label'     => __( 'Border Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#f3f4f6',
            'selectors' => array( '{{WRAPPER}} .raffle-description-card' => 'border-color: {{VALUE}};' ),
        ) );
        $this->add_responsive_control( 'card_radius', array(
            'label'      => __( 'Border Radius', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 24 ) ),
            'default'    => array( 'size' => 10 ),
            'selectors'  => array( '{{WRAPPER}} .raffle-description-card' => 'border-radius: {{SIZE}}px;' ),
        ) );
        $this->add_responsive_control( 'card_padding', array(
            'label'      => __( 'Padding', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'default'    => array( 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => true ),
            'selectors'  => array( '{{WRAPPER}} .raffle-description-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle || empty( $raffle->description ) ) return;

        echo '<div class="raffle-description-card" style="border:1px solid;">';
        echo '<div class="raffle-description-text">' . wp_kses_post( $raffle->description ) . '</div>';
        echo '</div>';
    }

    protected function content_template() {
        ?>
        <div class="raffle-description-card" style="background:#f9fafb;border:1px solid #f3f4f6;border-radius:10px;padding:16px;">
            <div class="raffle-description-text" style="color:#4b5563;font-size:14px;line-height:1.7;">
                Win the latest flagship smartphone in this exclusive competition. Each ticket gives you a chance to take home this incredible prize. Grab yours before they sell out!
            </div>
        </div>
        <?php
    }
}
