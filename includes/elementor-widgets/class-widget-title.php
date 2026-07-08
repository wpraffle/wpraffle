<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Title extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_title'; }
    public function get_title() { return 'Raffle Title'; }
    public function get_icon() { return 'eicon-heading'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'title', 'heading', 'name' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'html_tag', array(
            'label'   => __( 'HTML Tag', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4' ),
            'default' => 'h1',
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Style', 'wpraffle' ) ) );
        $this->add_control( 'color', array(
            'label'     => __( 'Text Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#111827',
            'selectors' => array( '{{WRAPPER}} .raffle-title-main' => 'color: {{VALUE}};' ),
        ) );
        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'typography',
            'label'    => __( 'Typography', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .raffle-title-main',
            'fields_options' => array(
                'font_size'   => array( 'default' => array( 'unit' => 'px', 'size' => 32 ) ),
                'font_weight' => array( 'default' => '800' ),
            ),
        ) );
        $this->add_responsive_control( 'align', array(
            'label'        => __( 'Alignment', 'wpraffle' ),
            'type'         => \Elementor\Controls_Manager::CHOOSE,
            'options'      => array(
                'left'   => array( 'title' => __( 'Left', 'wpraffle' ), 'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => __( 'Center', 'wpraffle' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => __( 'Right', 'wpraffle' ), 'icon' => 'eicon-text-align-right' ),
            ),
            'selectors'    => array( '{{WRAPPER}} .raffle-title-main' => 'text-align: {{VALUE}};' ),
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle ) return;
        $s = $this->get_settings_for_display();
        echo '<' . esc_html( $s['html_tag'] ) . ' class="raffle-title-main">' . esc_html( $raffle->title ) . '</' . esc_html( $s['html_tag'] ) . '>';
    }

    protected function content_template() {
        ?>
        <{{{ settings.html_tag || 'h1' }}} class="raffle-title-main" style="color:#111827;font-size:32px;font-weight:800;margin:0;">Sample Raffle Title</{{{ settings.html_tag || 'h1' }}}>
        <?php
    }
}
