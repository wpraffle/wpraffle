<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Countdown extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_countdown'; }
    public function get_title() { return 'Raffle Countdown'; }
    public function get_icon() { return 'eicon-countdown'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'countdown', 'timer', 'draw', 'date' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'show_seconds', array(
            'label'        => __( 'Show Seconds', 'wpraffle' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'description'  => __( 'Hide for a calmer display once under a day remains.', 'wpraffle' ),
        ) );
        $this->add_control( 'days_label', array( 'label' => __( 'Days Label', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'DAYS' ) );
        $this->add_control( 'hours_label', array( 'label' => __( 'Hours Label', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'HRS' ) );
        $this->add_control( 'minutes_label', array( 'label' => __( 'Minutes Label', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'MINS' ) );
        $this->add_control( 'seconds_label', array( 'label' => __( 'Seconds Label', 'wpraffle' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'SECS' ) );
        $this->add_control( 'expired_text', array(
            'label'   => __( 'Expired Message', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'The draw has closed.',
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Style', 'wpraffle' ) ) );
        $this->add_control( 'bg_color', array(
            'label'     => __( 'Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#111827',
            'selectors' => array( '{{WRAPPER}} .raffle-countdown-timer-inline' => 'background: {{VALUE}};' ),
        ) );
        $this->add_control( 'number_color', array(
            'label'     => __( 'Number Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array( '{{WRAPPER}} .raffle-cd-num' => 'color: {{VALUE}};' ),
        ) );
        $this->add_control( 'label_color', array(
            'label'     => __( 'Label Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#9ca3af',
            'selectors' => array( '{{WRAPPER}} .raffle-cd-lbl' => 'color: {{VALUE}};' ),
        ) );
        $this->add_responsive_control( 'box_size', array(
            'label'      => __( 'Box Size', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 40, 'max' => 120 ) ),
            'default'    => array( 'size' => 70 ),
            'selectors'  => array( '{{WRAPPER}} .raffle-cd-box' => 'min-width: {{SIZE}}px; padding: {{SIZE}}px 0;' ),
        ) );
        $this->add_responsive_control( 'gap', array(
            'label'      => __( 'Gap', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
            'default'    => array( 'size' => 8 ),
            'selectors'  => array( '{{WRAPPER}} .raffle-countdown-timer-inline' => 'gap: {{SIZE}}px;' ),
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle || ! $raffle->draw_date ) {
            echo '<p style="color:#6b7280;text-align:center;">No draw date set.</p>';
            return;
        }
        $s = $this->get_settings_for_display();

        $units = array(
            'days'    => $s['days_label'],
            'hours'   => $s['hours_label'],
            'minutes' => $s['minutes_label'],
        );
        if ( $s['show_seconds'] === 'yes' ) {
            $units['seconds'] = $s['seconds_label'];
        }

        echo '<div class="raffle-countdown-timer-inline" id="raffle-countdown-inline" data-draw-date="' . esc_attr( gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $raffle->draw_date ) ) ) . '">';
        foreach ( $units as $k => $lbl ) {
            echo '<div class="raffle-cd-box"><span class="raffle-cd-num" id="cd-inline-' . esc_attr( $k ) . '">00</span><span class="raffle-cd-lbl">' . esc_html( $lbl ) . '</span></div>';
        }
        echo '</div>';
        // Expired state — toggled by public.js when the timer hits zero.
        echo '<div class="raffle-countdown-expired-inline" id="raffle-countdown-expired-inline" style="display:none;margin-top:12px;text-align:center;font-weight:700;">' . esc_html( $s['expired_text'] ) . '</div>';
    }

    protected function content_template() {
        ?>
        <div class="raffle-countdown-timer-inline" style="display:flex;gap:8px;background:#111827;border-radius:8px;padding:8px;">
            <div class="raffle-cd-box" style="text-align:center;padding:12px;">
                <div class="raffle-cd-num" style="color:#fff;font-size:24px;font-weight:800;">07</div>
                <div class="raffle-cd-lbl" style="color:#9ca3af;font-size:11px;">DAYS</div>
            </div>
            <div class="raffle-cd-box" style="text-align:center;padding:12px;">
                <div class="raffle-cd-num" style="color:#fff;font-size:24px;font-weight:800;">12</div>
                <div class="raffle-cd-lbl" style="color:#9ca3af;font-size:11px;">HRS</div>
            </div>
            <div class="raffle-cd-box" style="text-align:center;padding:12px;">
                <div class="raffle-cd-num" style="color:#fff;font-size:24px;font-weight:800;">45</div>
                <div class="raffle-cd-lbl" style="color:#9ca3af;font-size:11px;">MINS</div>
            </div>
        </div>
        <?php
    }
}
