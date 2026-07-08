<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Full_Page extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_full_page'; }
    public function get_title() { return 'Raffle Full Page'; }
    public function get_icon() { return 'eicon-page-list'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'full', 'page', 'complete', 'template' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'info', array(
            'type'    => \Elementor\Controls_Manager::RAW_HTML,
            'raw'     => __( 'Renders the complete default raffle layout using the raffle-display.php template — image, price, stats, quantity, enter button, countdown and purchase modal in one block.', 'wpraffle' ),
            'content_classes' => 'elementor-descriptor',
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle ) {
            echo '<p>No raffle found for this product.</p>';
            return;
        }
        $template_path = RAFFLE_SYSTEM_PATH . 'public/views/raffle-display.php';
        if ( file_exists( $template_path ) ) {
            set_query_var( 'raffle', $raffle );
            include $template_path;
        }
    }

    protected function content_template() {
        ?>
        <div style="border:2px dashed #2271b1;border-radius:8px;padding:24px;text-align:center;background:#f0f6fc;">
            <span style="font-size:32px;">🎟️</span>
            <p style="margin:8px 0 0;font-weight:700;color:#2271b1;">Full Raffle Page</p>
            <p style="margin:4px 0 0;font-size:12px;color:#50575e;">Renders the complete raffle layout (image, price, entry, modal) on the live page.</p>
        </div>
        <?php
    }
}
