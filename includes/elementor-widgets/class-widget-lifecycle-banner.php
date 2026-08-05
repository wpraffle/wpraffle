<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Lifecycle Status banner widget.
 *
 * Renders a coloured status pill for a single raffle. The colour is read from a
 * per-state control (`color_active`, `color_upcoming`, ...) and inlined on the
 * banner because the status — and therefore which control applies — is dynamic
 * at render time. The wrapper carries a `wpr-lifecycle-banner--<status>` class
 * for targeted CSS overrides.
 */
class Raffle_Widget_Lifecycle_Banner extends \Elementor\Widget_Base {

    public function get_name() { return 'raffle_lifecycle_banner'; }
    public function get_title() { return 'Lifecycle Status'; }
    public function get_icon() { return 'eicon-info-circle'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'status', 'lifecycle', 'banner' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'show_icon', array(
            'label'        => __( 'Show Icon', 'wpraffle' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => 'yes',
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Style', 'wpraffle' ) ) );

        $this->add_control( 'color_active', array(
            'label'     => __( 'Active Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#16a34a',
        ) );
        $this->add_control( 'color_upcoming', array(
            'label'     => __( 'Upcoming Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#2563eb',
        ) );
        $this->add_control( 'color_drawing', array(
            'label'     => __( 'Drawing Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#d97706',
        ) );
        $this->add_control( 'color_ended', array(
            'label'     => __( 'Ended Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#6b7280',
        ) );
        $this->add_control( 'color_failed', array(
            'label'     => __( 'Failed Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#dc2626',
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'typography',
            'label'    => __( 'Typography', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .wpr-lifecycle-banner',
            'fields_options' => array(
                'font_size'   => array( 'default' => array( 'unit' => 'px', 'size' => 14 ) ),
                'font_weight' => array( 'default' => '700' ),
            ),
            'separator' => 'before',
        ) );

        $this->add_responsive_control( 'border_radius', array(
            'label'      => __( 'Border Radius', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
            'default'    => array( 'size' => 999 ),
            'selectors'  => array( '{{WRAPPER}} .wpr-lifecycle-banner' => 'border-radius: {{SIZE}}px;' ),
            'separator'  => 'before',
        ) );
        $this->add_responsive_control( 'padding', array(
            'label'      => __( 'Padding', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'default'    => array( 'top' => '6', 'right' => '16', 'bottom' => '6', 'left' => '16', 'unit' => 'px', 'isLinked' => false ),
            'selectors'  => array( '{{WRAPPER}} .wpr-lifecycle-banner' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );

        $this->end_controls_section();
    }

    /**
     * Map a raffle status to its human label.
     *
     * @param string $status
     * @return string
     */
    private function label_for( $status ) {
        $map = array(
            'active'   => __( 'Live now', 'wpraffle' ),
            'upcoming' => __( 'Coming soon', 'wpraffle' ),
            'drawing'  => __( 'Drawing', 'wpraffle' ),
            'ended'    => __( 'Ended', 'wpraffle' ),
            'failed'   => __( 'Failed', 'wpraffle' ),
        );
        return isset( $map[ $status ] ) ? $map[ $status ] : __( 'Unknown', 'wpraffle' );
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle ) {
            return;
        }

        $settings = $this->get_settings_for_display();
        $status   = $raffle->status;
        $label    = $this->label_for( $status );
        $color    = isset( $settings[ 'color_' . $status ] ) ? $settings[ 'color_' . $status ] : '#6b7280';
        $show_icon = isset( $settings['show_icon'] ) ? $settings['show_icon'] : 'yes';

        echo '<div class="wpr-lifecycle-banner wpr-lifecycle-banner--' . esc_attr( $status ) . '" style="display:inline-flex;align-items:center;gap:6px;background-color:' . esc_attr( $color ) . ';color:#fff;">';
        if ( $show_icon === 'yes' ) {
            echo '<span class="dashicons dashicons-flag" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span>';
        }
        echo '<span class="wpr-lifecycle-banner-label">' . esc_html( $label ) . '</span>';
        echo '</div>';
    }

    protected function content_template() {
        ?>
        <div class="wpr-lifecycle-banner wpr-lifecycle-banner--active" style="display:inline-flex;align-items:center;gap:6px;background-color:#16a34a;color:#fff;border-radius:999px;padding:6px 16px;font-size:14px;font-weight:700;">
            <# if ( settings.show_icon !== 'no' ) { #>
                <span class="dashicons dashicons-flag" style="font-size:16px;width:16px;height:16px;line-height:16px;"></span>
            <# } #>
            <span class="wpr-lifecycle-banner-label">Live now</span>
        </div>
        <?php
    }
}
