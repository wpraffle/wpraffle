<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Raffle Purchase Modal widget.
 *
 * Renders the shared purchase-modal partial (the same one used by the master
 * raffle-display.php template), so the JS hooks in public.js — which bind
 * #raffle-modal, #raffle-purchase-form and .raffle-modal-close — find the
 * expected elements. Place once per page.
 */
class Raffle_Widget_Modal extends \Elementor\Widget_Base {

    public function get_name() { return 'raffle_modal'; }
    public function get_title() { return 'Raffle Purchase Modal'; }
    public function get_icon() { return 'eicon-popup'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'purchase', 'modal', 'popup', 'checkout' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'info', array(
            'type'    => \Elementor\Controls_Manager::RAW_HTML,
            'raw'     => __( 'Renders the purchase modal for the selected raffle. Place it once on the page alongside the Enter Button widget.', 'wpraffle' ),
            'content_classes' => 'elementor-descriptor',
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle ) {
            return;
        }
        // Include the shared partial so the modal's ids/classes match what
        // public.js expects (#raffle-modal, #raffle-purchase-form).
        include RAFFLE_SYSTEM_PATH . 'public/views/widgets/purchase-modal.php';
    }

    /**
     * Editor preview — show a static mock so the widget isn't invisible on
     * the canvas (the real modal is display:none until opened).
     */
    protected function content_template() {
        ?>
        <div style="border:2px dashed #d4a017;border-radius:8px;padding:20px;text-align:center;background:#fffbeb;">
            <span style="font-size:24px;">🛒</span>
            <p style="margin:8px 0 0;font-weight:600;">Purchase Modal</p>
            <p style="margin:4px 0 0;font-size:12px;color:#6b7280;">Renders on the frontend when "Enter Competition" is clicked.</p>
        </div>
        <?php
    }
}
