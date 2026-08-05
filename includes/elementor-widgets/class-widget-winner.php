<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Winner Announcement widget.
 *
 * Shows the winning buyer for a single raffle: an initial circle (first letter
 * of the buyer's name), a "WINNER" badge, the buyer name and the winning ticket
 * number. When the draw has not been held yet (`winner_ticket_id` empty) it
 * renders the configurable empty-state message instead.
 */
class Raffle_Widget_Winner extends \Elementor\Widget_Base {

    public function get_name() { return 'raffle_winner'; }
    public function get_title() { return 'Winner Announcement'; }
    public function get_icon() { return 'eicon-trophy'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'winner', 'announcement', 'result' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'show_avatar', array(
            'label'        => __( 'Show Avatar/Initial', 'wpraffle' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => 'yes',
        ) );
        $this->add_control( 'empty_text', array(
            'label'       => __( 'Empty State Text', 'wpraffle' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => 'Draw has not been held yet.',
            'description' => __( 'Shown when the raffle has no winner yet.', 'wpraffle' ),
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Style', 'wpraffle' ) ) );

        $this->add_control( 'card_bg', array(
            'label'     => __( 'Card Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array( '{{WRAPPER}} .wpr-winner-card' => 'background: {{VALUE}};' ),
        ) );
        $this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
            'name'     => 'card_border',
            'label'    => __( 'Card Border', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .wpr-winner-card',
        ) );
        $this->add_responsive_control( 'card_radius', array(
            'label'      => __( 'Card Border Radius', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
            'default'    => array( 'size' => 16 ),
            'selectors'  => array( '{{WRAPPER}} .wpr-winner-card' => 'border-radius: {{SIZE}}px;' ),
        ) );
        $this->add_responsive_control( 'card_padding', array(
            'label'      => __( 'Card Padding', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'default'    => array( 'top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'unit' => 'px', 'isLinked' => true ),
            'selectors'  => array( '{{WRAPPER}} .wpr-winner-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );

        $this->add_control( 'name_color', array(
            'label'     => __( 'Winner Name Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#111827',
            'selectors' => array( '{{WRAPPER}} .wpr-winner-name' => 'color: {{VALUE}};' ),
            'separator' => 'before',
        ) );
        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'name_typography',
            'label'    => __( 'Winner Name Typography', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .wpr-winner-name',
            'fields_options' => array(
                'font_size'   => array( 'default' => array( 'unit' => 'px', 'size' => 22 ) ),
                'font_weight' => array( 'default' => '800' ),
            ),
        ) );

        $this->add_control( 'ticket_color', array(
            'label'     => __( 'Ticket Number Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#6b7280',
            'selectors' => array( '{{WRAPPER}} .wpr-winner-ticket' => 'color: {{VALUE}};' ),
            'separator' => 'before',
        ) );

        $this->add_control( 'badge_bg', array(
            'label'     => __( 'Won Badge Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#f59e0b',
            'selectors' => array( '{{WRAPPER}} .wpr-winner-badge' => 'background: {{VALUE}};' ),
            'separator' => 'before',
        ) );

        $this->end_controls_section();
    }

    /**
     * Look up the winning ticket + buyer for a raffle.
     *
     * Joins `raffle_tickets` (id = winner_ticket_id) to `raffle_purchases`
     * (id = ticket.purchase_id) to resolve the buyer name and ticket number.
     *
     * @param object $raffle
     * @return object|false Row with buyer_name + ticket_number, or false.
     */
    private function lookup_winner( $raffle ) {
        if ( empty( $raffle->winner_ticket_id ) ) {
            return false;
        }

        global $wpdb;
        $tickets   = $wpdb->prefix . 'raffle_tickets';
        $purchases = $wpdb->prefix . 'raffle_purchases';

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT t.ticket_number, p.buyer_name
             FROM {$tickets} t
             LEFT JOIN {$purchases} p ON p.id = t.purchase_id
             WHERE t.id = %d
             LIMIT 1",
            $raffle->winner_ticket_id
        ) ) ?: false;
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle ) {
            return;
        }

        $settings = $this->get_settings_for_display();
        $winner   = $this->lookup_winner( $raffle );

        if ( ! $winner ) {
            echo '<div class="wpr-winner-card wpr-winner-card--empty" style="padding:24px;text-align:center;color:#6b7280;font-style:italic;">' . esc_html( $settings['empty_text'] ) . '</div>';
            return;
        }

        $show_avatar = isset( $settings['show_avatar'] ) ? $settings['show_avatar'] : 'yes';
        $initial     = function_exists( 'mb_substr' ) ? mb_substr( trim( $winner->buyer_name ), 0, 1 ) : substr( trim( $winner->buyer_name ), 0, 1 );
        $initial     = $initial ? strtoupper( $initial ) : '?';
        $buyer_name  = $winner->buyer_name ? $winner->buyer_name : __( 'Unknown buyer', 'wpraffle' );

        echo '<div class="wpr-winner-card" style="text-align:center;">';

        if ( $show_avatar === 'yes' ) {
            echo '<div class="wpr-winner-initial" style="width:64px;height:64px;border-radius:50%;background:#6366f1;color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;margin:0 auto 12px;">' . esc_html( $initial ) . '</div>';
        }

        echo '<span class="wpr-winner-badge" style="display:inline-block;color:#fff;background:#f59e0b;font-size:11px;font-weight:800;letter-spacing:0.08em;padding:4px 12px;border-radius:999px;margin-bottom:8px;">' . esc_html__( 'WINNER', 'wpraffle' ) . '</span>';
        echo '<div class="wpr-winner-name" style="margin:0 0 4px;">' . esc_html( $buyer_name ) . '</div>';
        echo '<div class="wpr-winner-ticket" style="font-size:14px;">' . esc_html__( 'Ticket', 'wpraffle' ) . ' #' . esc_html( $winner->ticket_number ) . '</div>';

        echo '</div>';
    }

    protected function content_template() {
        ?>
        <div class="wpr-winner-card" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;text-align:center;">
            <# if ( settings.show_avatar !== 'no' ) { #>
                <div class="wpr-winner-initial" style="width:64px;height:64px;border-radius:50%;background:#6366f1;color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;margin:0 auto 12px;">J</div>
            <# } #>
            <span class="wpr-winner-badge" style="display:inline-block;color:#fff;background:#f59e0b;font-size:11px;font-weight:800;letter-spacing:0.08em;padding:4px 12px;border-radius:999px;margin-bottom:8px;">WINNER</span>
            <div class="wpr-winner-name" style="color:#111827;font-size:22px;font-weight:800;margin:0 0 4px;">Jane Doe</div>
            <div class="wpr-winner-ticket" style="color:#6b7280;font-size:14px;">Ticket #0427</div>
        </div>
        <?php
    }
}
