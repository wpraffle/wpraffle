<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Quantity extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_quantity'; }
    public function get_title() { return 'Raffle Quantity Selector'; }
    public function get_icon() { return 'eicon-slider-horizontal'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'quantity', 'tickets', 'slider', 'select' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'show_pills', array( 'label' => __( 'Show Quick Select Pills', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->add_control( 'show_slider', array( 'label' => __( 'Show Slider', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->add_control( 'show_manual', array( 'label' => __( 'Show Manual Input', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Style', 'wpraffle' ) ) );

        // Quick-select heading.
        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'heading_typography',
            'label'    => __( 'Heading Typography', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .raffle-qty-heading',
            'fields_options' => array(
                'font_size'   => array( 'default' => array( 'unit' => 'px', 'size' => 11 ) ),
                'font_weight' => array( 'default' => '700' ),
            ),
        ) );

        // Quick-select pills.
        $this->add_control( 'pill_bg', array(
            'label'     => __( 'Pill Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array( '{{WRAPPER}} .raffle-qty-pill' => 'background: {{VALUE}};' ),
        ) );
        $this->add_control( 'pill_color', array(
            'label'     => __( 'Pill Text Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#111827',
            'selectors' => array( '{{WRAPPER}} .raffle-qty-pill' => 'color: {{VALUE}};' ),
        ) );
        $this->add_responsive_control( 'pill_radius', array(
            'label'      => __( 'Pill Border Radius', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
            'default'    => array( 'size' => 6 ),
            'selectors'  => array( '{{WRAPPER}} .raffle-qty-pill' => 'border-radius: {{SIZE}}px;' ),
        ) );
        $this->add_control( 'pill_active_bg', array(
            'label'     => __( 'Active Pill Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#d4a017',
            'selectors' => array(
                '{{WRAPPER}} .raffle-qty-pill.active'                 => 'background: {{VALUE}};',
                '{{WRAPPER}} .raffle-qty-pill[aria-pressed="true"]'   => 'background: {{VALUE}};',
            ),
        ) );

        // Range slider.
        $this->add_control( 'slider_track', array(
            'label'     => __( 'Slider Track Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#e5e7eb',
            'selectors' => array( '{{WRAPPER}} #raffle-qty-range-slider' => 'background: {{VALUE}};' ),
        ) );
        $this->add_control( 'slider_thumb', array(
            'label'     => __( 'Slider Thumb Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#d4a017',
            'selectors' => array(
                '{{WRAPPER}} #raffle-qty-range-slider::-webkit-slider-thumb' => 'background: {{VALUE}};',
                '{{WRAPPER}} #raffle-qty-range-slider::-moz-range-thumb'     => 'background: {{VALUE}};',
            ),
        ) );

        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle ) return;
        $ctx = Raffle_Elementor::get_raffle_context( $raffle );
        $s   = $this->get_settings_for_display();
        $max = min( $ctx['max_tickets'], $ctx['remaining'] );

        // Use the normalised packages helper so bundle metadata (labels/badges)
        // is preserved when present, matching raffle-display.php.
        $pkgs = function_exists( 'wpraffle_normalise_packages' )
            ? wpraffle_normalise_packages( $raffle->packages )
            : json_decode( $raffle->packages, true );

        // Extract bare quantities for pill display.
        $qtys = array();
        if ( is_array( $pkgs ) ) {
            foreach ( $pkgs as $p ) {
                $q = is_array( $p ) ? ( isset( $p['qty'] ) ? (int) $p['qty'] : 0 ) : (int) $p;
                if ( $q >= 1 && $q <= $max ) {
                    $qtys[] = $q;
                }
            }
        }
        if ( empty( $qtys ) ) {
            $qtys = array_filter( array( 5, 10, 15, 25 ), function ( $q ) use ( $max ) { return $q >= 1 && $q <= $max; } );
        }

        if ( $s['show_pills'] === 'yes' && ! empty( $qtys ) ) {
            echo '<div class="raffle-quick-select-qty"><span class="raffle-qty-heading">QUICK SELECT QUANTITY</span><div class="raffle-qty-pills-row">';
            foreach ( $qtys as $q ) {
                echo '<button type="button" class="raffle-qty-pill" data-qty="' . esc_attr( $q ) . '" aria-pressed="false">' . esc_html( $q ) . '</button>';
            }
            echo '</div></div>';
        }
        if ( $s['show_slider'] === 'yes' ) {
            echo '<div class="raffle-slider-qty-selector"><div class="raffle-slider-controls">';
            echo '<button type="button" class="raffle-slider-btn minus">-</button>';
            echo '<div class="raffle-slider-track-wrap"><input type="range" id="raffle-qty-range-slider" min="1" max="' . esc_attr( $max ) . '" value="1" aria-label="' . esc_attr__( 'Quantity', 'wpraffle' ) . '"><div class="raffle-slider-tooltip" id="raffle-qty-slider-tooltip">1 TICKET</div></div>';
            echo '<button type="button" class="raffle-slider-btn plus">+</button></div>';
            if ( $s['show_manual'] === 'yes' ) {
                echo '<div class="raffle-manual-qty-input-wrap"><input type="number" id="raffle-manual-qty-num" min="1" max="' . esc_attr( $max ) . '" value="1"></div>';
            }
            echo '</div>';
        }
    }

    protected function content_template() {
        ?>
        <div class="raffle-quick-select-qty">
            <span class="raffle-qty-heading" style="display:block;font-size:11px;font-weight:700;color:#6b7280;margin-bottom:8px;">QUICK SELECT QUANTITY</span>
            <div class="raffle-qty-pills-row" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                <button type="button" class="raffle-qty-pill" style="padding:8px 16px;border:1px solid #d4a017;background:#fff;border-radius:6px;font-weight:700;cursor:pointer;">5</button>
                <button type="button" class="raffle-qty-pill" style="padding:8px 16px;border:1px solid #d4a017;background:#fff;border-radius:6px;font-weight:700;cursor:pointer;">10</button>
                <button type="button" class="raffle-qty-pill" style="padding:8px 16px;border:1px solid #d4a017;background:#fff;border-radius:6px;font-weight:700;cursor:pointer;">15</button>
                <button type="button" class="raffle-qty-pill" style="padding:8px 16px;border:1px solid #d4a017;background:#fff;border-radius:6px;font-weight:700;cursor:pointer;">25</button>
            </div>
        </div>
        <?php
    }
}
