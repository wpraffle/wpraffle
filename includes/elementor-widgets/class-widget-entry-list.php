<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Entry_List extends \Elementor\Widget_Base {

    public function get_name() { return 'raffle_entry_list'; }
    public function get_title() { return 'Entry List Downloads'; }
    public function get_icon() { return 'eicon-download-button'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'entry', 'list', 'download', 'csv' ); }

    protected function register_controls() {

        // Content Section
        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );

        $this->add_control( 'button_text', array(
            'label'   => 'Button Text',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Download Entry List',
        ) );

        $this->add_control( 'layout', array(
            'label'   => 'Layout',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => array( 'grid' => 'Grid', 'list' => 'List' ),
            'default' => 'grid',
        ) );

        $this->add_control( 'columns', array(
            'label'     => 'Grid Columns',
            'type'      => \Elementor\Controls_Manager::SELECT,
            'options'   => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
            'default'   => '2',
            'condition' => array( 'layout' => 'grid' ),
        ) );

        $this->add_control( 'show_image', array(
            'label'   => 'Show Prize Image',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );

        $this->end_controls_section();

        // Button Style Section
        $this->start_controls_section( 'button_style', array( 'label' => 'Button Style' ) );

        $this->add_control( 'button_bg_color', array(
            'label'     => 'Background Color',
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#1e40af',
            'selectors' => array(
                '{{WRAPPER}} .raffle-entry-list-download-btn' => 'background: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'button_text_color', array(
            'label'     => 'Text Color',
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array(
                '{{WRAPPER}} .raffle-entry-list-download-btn' => 'color: {{VALUE}} !important;',
            ),
        ) );

        $this->add_control( 'button_hover_bg_color', array(
            'label'     => 'Hover Background Color',
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#1d3a8a',
            'selectors' => array(
                '{{WRAPPER}} .raffle-entry-list-download-btn:hover' => 'background: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'button_border_radius', array(
            'label'      => 'Border Radius',
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
            'default'    => array( 'size' => 8 ),
            'selectors'  => array(
                '{{WRAPPER}} .raffle-entry-list-download-btn' => 'border-radius: {{SIZE}}px;',
            ),
        ) );

        $this->add_responsive_control( 'button_padding', array(
            'label'      => 'Padding',
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'default'    => array( 'top' => '12', 'right' => '16', 'bottom' => '12', 'left' => '16', 'unit' => 'px', 'isLinked' => false ),
            'selectors'  => array(
                '{{WRAPPER}} .raffle-entry-list-download-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'button_typography',
            'label'    => 'Typography',
            'selector' => '{{WRAPPER}} .raffle-entry-list-download-btn',
            'fields_options' => array(
                'font_size' => array( 'default' => array( 'unit' => 'px', 'size' => 14 ) ),
                'font_weight' => array( 'default' => '700' ),
            ),
        ) );

        $this->end_controls_section();

        // Card Style Section
        $this->start_controls_section( 'card_style', array( 'label' => 'Card Style' ) );

        $this->add_control( 'card_bg_color', array(
            'label'     => 'Card Background',
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array(
                '{{WRAPPER}} .raffle-entry-list-card' => 'background: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'card_border_color', array(
            'label'     => 'Card Border Color',
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#e5e7eb',
            'selectors' => array(
                '{{WRAPPER}} .raffle-entry-list-card' => 'border-color: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'card_border_radius', array(
            'label'      => 'Card Border Radius',
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
            'default'    => array( 'size' => 16 ),
            'selectors'  => array(
                '{{WRAPPER}} .raffle-entry-list-card' => 'border-radius: {{SIZE}}px;',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'card_shadow',
            'selector' => '{{WRAPPER}} .raffle-entry-list-card',
        ) );

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();

        echo do_shortcode( sprintf(
            '[raffle_entry_list button_text="%s" button_bg="%s" button_color="%s" button_radius="%s" show_image="%s" columns="%s" layout="%s"]',
            esc_attr( $s['button_text'] ),
            esc_attr( $s['button_bg_color'] ),
            esc_attr( $s['button_text_color'] ),
            isset( $s['button_border_radius']['size'] ) ? intval( $s['button_border_radius']['size'] ) : 8,
            $s['show_image'] === 'yes' ? 'yes' : 'no',
            esc_attr( $s['columns'] ),
            esc_attr( $s['layout'] )
        ) );
    }
}