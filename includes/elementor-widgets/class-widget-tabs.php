<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Raffle Entry Tabs widget.
 *
 * Renders the Online / Postal entry tabs and BOTH tab panes using the
 * canonical classes/ids that public.js toggles: clicking a .raffle-tab-btn
 * hides all .raffle-tab-content and shows #tab-<name>. The online pane is
 * where operators drop the Quantity, Question, Countdown, Enter Button and
 * Modal widgets; the postal pane renders the configured address.
 */
class Raffle_Widget_Tabs extends \Elementor\Widget_Base {

    public function get_name() { return 'raffle_tabs'; }
    public function get_title() { return 'Raffle Entry Tabs'; }
    public function get_icon() { return 'eicon-tabs'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'tabs', 'online', 'postal', 'entry' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        $this->add_control( 'online_label', array(
            'label'   => __( 'Online Tab Label', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'ONLINE ENTRY',
        ) );
        $this->add_control( 'postal_label', array(
            'label'   => __( 'Postal Tab Label', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'POSTAL ENTRY',
        ) );
        $this->add_control( 'postal_address', array(
            'label'   => __( 'Postal Address / Instructions', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::TEXTAREA,
            'default' => 'To enter this competition for free by post, send your name, address, email and the correct answer to the skill question on a postcard to: P.O. Box 123, City, Postcode. Postal entries must be received before the draw date to be eligible.',
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Tab Style', 'wpraffle' ) ) );
        $this->add_control( 'active_bg', array(
            'label'     => __( 'Active Tab Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#d4a017',
            'selectors' => array( '{{WRAPPER}} .raffle-tab-btn.active' => 'background: {{VALUE}};' ),
        ) );
        $this->add_control( 'active_color', array(
            'label'     => __( 'Active Tab Text', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array( '{{WRAPPER}} .raffle-tab-btn.active' => 'color: {{VALUE}};' ),
        ) );
        $this->add_control( 'inactive_bg', array(
            'label'     => __( 'Inactive Tab Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#f3f4f6',
            'selectors' => array( '{{WRAPPER}} .raffle-tab-btn:not(.active)' => 'background: {{VALUE}};' ),
        ) );
        $this->add_control( 'inactive_color', array(
            'label'     => __( 'Inactive Tab Text', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#374151',
            'selectors' => array( '{{WRAPPER}} .raffle-tab-btn:not(.active)' => 'color: {{VALUE}};' ),
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        ?>
        <div class="raffle-entry-tabs">
            <button type="button" class="raffle-tab-btn active" data-tab="online"><?php echo esc_html( $s['online_label'] ); ?></button>
            <button type="button" class="raffle-tab-btn" data-tab="postal"><?php echo esc_html( $s['postal_label'] ); ?></button>
        </div>

        <div class="raffle-tab-contents">
            <!-- Online pane: empty container that other widgets (Quantity,
                 Question, Enter Button, etc.) populate on the page. -->
            <div class="raffle-tab-content active" id="tab-online">
                <!-- Online entry widgets render here -->
            </div>

            <!-- Postal pane: rendered from the configured address. -->
            <div class="raffle-tab-content" id="tab-postal" style="display:none;">
                <div class="raffle-postal-info-card"><?php echo nl2br( esc_html( $s['postal_address'] ) ); ?></div>
            </div>
        </div>
        <?php
    }

    protected function content_template() {
        ?>
        <div class="raffle-entry-tabs">
            <button type="button" class="raffle-tab-btn active" style="background:#d4a017;color:#fff;border:none;padding:10px 20px;font-weight:700;cursor:pointer;">ONLINE ENTRY</button>
            <button type="button" class="raffle-tab-btn" style="background:#f3f4f6;color:#374151;border:none;padding:10px 20px;font-weight:700;cursor:pointer;">POSTAL ENTRY</button>
        </div>
        <div style="margin-top:12px;padding:16px;background:#f9fafb;border-radius:8px;color:#6b7280;font-size:13px;">
            Tab panes appear here on the live page.
        </div>
        <?php
    }
}
