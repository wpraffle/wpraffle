<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Trust extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_trust'; }
    public function get_title() { return 'Raffle Trust Badges'; }
    public function get_icon() { return 'eicon-shield'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'trust', 'badges', 'secure', 'confidence' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        $this->add_control( 'show_secure', array( 'label' => __( 'Show Secure Purchase', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->add_control( 'show_confirmation', array( 'label' => __( 'Show Email Confirmation', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->add_control( 'show_random', array( 'label' => __( 'Show Random Draw', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Style', 'wpraffle' ) ) );
        $this->add_control( 'text_color', array(
            'label'     => __( 'Text Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#6b7280',
            'selectors' => array( '{{WRAPPER}} .raffle-trust-badge' => 'color: {{VALUE}};' ),
        ) );
        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'typography',
            'label'    => __( 'Typography', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .raffle-trust-badge',
            'fields_options' => array(
                'font_size'   => array( 'default' => array( 'unit' => 'px', 'size' => 12 ) ),
                'font_weight' => array( 'default' => '700' ),
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
            'default'      => 'center',
            'selectors'    => array( '{{WRAPPER}} .raffle-trust-row' => 'justify-content: {{VALUE}};' ),
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        echo '<div class="raffle-trust-row" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">';
        if ( $s['show_secure'] === 'yes' ) {
            echo '<span class="raffle-trust-badge" style="display:inline-flex;align-items:center;gap:6px;">';
            wpr_icon( 'lock', 'wpr-icon--sm' );
            echo ' Secure Purchase</span>';
        }
        if ( $s['show_confirmation'] === 'yes' ) {
            echo '<span class="raffle-trust-badge" style="display:inline-flex;align-items:center;gap:6px;">';
            wpr_icon( 'mail', 'wpr-icon--sm' );
            echo ' Email Confirmation</span>';
        }
        if ( $s['show_random'] === 'yes' ) {
            echo '<span class="raffle-trust-badge" style="display:inline-flex;align-items:center;gap:6px;">';
            wpr_icon( 'zap', 'wpr-icon--sm' );
            echo ' Random Draw</span>';
        }
        echo '</div>';
    }

    protected function content_template() {
        ?>
        <div class="raffle-trust-row" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;justify-content:center;">
            <span class="raffle-trust-badge" style="display:inline-flex;align-items:center;gap:6px;color:#6b7280;font-size:12px;font-weight:700;">🔒 Secure Purchase</span>
            <span class="raffle-trust-badge" style="display:inline-flex;align-items:center;gap:6px;color:#6b7280;font-size:12px;font-weight:700;">✉️ Email Confirmation</span>
            <span class="raffle-trust-badge" style="display:inline-flex;align-items:center;gap:6px;color:#6b7280;font-size:12px;font-weight:700;">⚡ Random Draw</span>
        </div>
        <?php
    }
}
