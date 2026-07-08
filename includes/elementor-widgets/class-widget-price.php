<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Price extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_price'; }
    public function get_title() { return 'Raffle Price'; }
    public function get_icon() { return 'eicon-price-tag'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'price', 'cost', 'ticket' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'label_text', array(
            'label'   => __( 'Label Text', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'PER ENTRY',
        ) );
        $this->add_control( 'show_prize_value', array(
            'label'        => __( 'Show Prize Value Instead', 'wpraffle' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => '',
            'description'  => __( 'Display the prize value rather than the ticket price.', 'wpraffle' ),
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Style', 'wpraffle' ) ) );
        $this->add_control( 'price_color', array(
            'label'     => __( 'Price Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#111827',
            'selectors' => array( '{{WRAPPER}} .raffle-price-value' => 'color: {{VALUE}};' ),
        ) );
        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'price_typography',
            'label'    => __( 'Price Typography', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .raffle-price-value',
            'fields_options' => array(
                'font_size'   => array( 'default' => array( 'unit' => 'px', 'size' => 28 ) ),
                'font_weight' => array( 'default' => '800' ),
            ),
        ) );
        $this->add_control( 'label_color', array(
            'label'     => __( 'Label Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#6b7280',
            'selectors' => array( '{{WRAPPER}} .raffle-price-label' => 'color: {{VALUE}};' ),
        ) );
        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'label_typography',
            'label'    => __( 'Label Typography', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .raffle-price-label',
            'fields_options' => array(
                'font_size'   => array( 'default' => array( 'unit' => 'px', 'size' => 12 ) ),
                'font_weight' => array( 'default' => '600' ),
            ),
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle ) return;
        $s = $this->get_settings_for_display();

        $value = ( $s['show_prize_value'] === 'yes' )
            ? wpr_price( $raffle->prize_value )
            : wpr_price( $raffle->ticket_price );

        echo '<div class="raffle-price-row">';
        echo '<span class="raffle-price-value">' . esc_html( $value ) . '</span>';
        echo '<span class="raffle-price-label">' . esc_html( $s['label_text'] ) . '</span>';
        echo '</div>';
    }

    protected function content_template() {
        ?>
        <div class="raffle-price-row">
            <span class="raffle-price-value" style="color:#111827;font-size:28px;font-weight:800;">£5.00</span>
            <span class="raffle-price-label" style="color:#6b7280;font-size:12px;font-weight:600;">PER ENTRY</span>
        </div>
        <?php
    }
}
