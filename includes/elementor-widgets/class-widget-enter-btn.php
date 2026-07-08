<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Enter Button widget.
 *
 * Renders the canonical "Enter Competition" CTA (or a SOLD OUT badge when no
 * tickets remain). Emits the same id (`raffle-enter-comp-submit-btn`) and class
 * (`raffle-enter-comp-btn`) as raffle-display.php so the existing public.js
 * click handler binds to it without modification.
 */
class Raffle_Widget_Enter_Btn extends \Elementor\Widget_Base {

    public function get_name() { return 'raffle_enter_btn'; }
    public function get_title() { return 'Raffle Enter Button'; }
    public function get_icon() { return 'eicon-button'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'enter', 'button', 'cta', 'competition' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'button_text', array(
            'label'   => __( 'Button Text', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'ENTER COMPETITION',
        ) );
        $this->add_control( 'sold_out_text', array(
            'label'   => __( 'Sold Out Text', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'SOLD OUT',
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Style', 'wpraffle' ) ) );

        $this->add_control( 'bg_color', array(
            'label'     => __( 'Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#d4a017',
            'selectors' => array( '{{WRAPPER}} .raffle-enter-comp-btn' => 'background: {{VALUE}};' ),
        ) );
        $this->add_control( 'text_color', array(
            'label'     => __( 'Text Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array( '{{WRAPPER}} .raffle-enter-comp-btn' => 'color: {{VALUE}} !important;' ),
        ) );
        $this->add_control( 'hover_bg_color', array(
            'label'     => __( 'Hover Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#b8860b',
            'selectors' => array( '{{WRAPPER}} .raffle-enter-comp-btn:hover' => 'background: {{VALUE}};' ),
        ) );
        $this->add_responsive_control( 'border_radius', array(
            'label'      => __( 'Border Radius', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
            'default'    => array( 'size' => 8 ),
            'selectors'  => array( '{{WRAPPER}} .raffle-enter-comp-btn' => 'border-radius: {{SIZE}}px;' ),
        ) );
        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'btn_typography',
            'label'    => __( 'Typography', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .raffle-enter-comp-btn',
            'fields_options' => array(
                'font_size'    => array( 'default' => array( 'unit' => 'px', 'size' => 18 ) ),
                'font_weight'  => array( 'default' => '800' ),
            ),
        ) );
        $this->add_responsive_control( 'padding', array(
            'label'      => __( 'Padding', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'default'    => array( 'top' => '14', 'right' => '28', 'bottom' => '14', 'left' => '28', 'unit' => 'px', 'isLinked' => false ),
            'selectors'  => array( '{{WRAPPER}} .raffle-enter-comp-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );

        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle ) {
            return;
        }
        $s   = $this->get_settings_for_display();
        $ctx = Raffle_Elementor::get_raffle_context( $raffle );

        echo '<div class="raffle-enter-action-wrapper">';
        if ( $ctx['remaining'] > 0 && $raffle->status === 'active' ) {
            // Canonical id/class so public.js binds the purchase handler.
            echo '<button type="button" class="raffle-enter-comp-btn" id="raffle-enter-comp-submit-btn">'
                . esc_html( $s['button_text'] )
                . '</button>';
        } else {
            echo '<div class="raffle-sold-out-badge">' . esc_html( $s['sold_out_text'] ) . '</div>';
        }
        echo '</div>';
    }

    /**
     * Editor canvas preview. Renders placeholder content so the widget isn't
     * blank in the Elementor editor when there is no current raffle.
     */
    protected function content_template() {
        ?>
        <div class="raffle-enter-action-wrapper">
            <button type="button" class="raffle-enter-comp-btn" style="background:#d4a017;color:#fff;border:none;border-radius:8px;padding:14px 28px;font-weight:800;font-size:18px;cursor:pointer;">
                {{{ settings.button_text || 'ENTER COMPETITION' }}}
            </button>
        </div>
        <?php
    }
}
