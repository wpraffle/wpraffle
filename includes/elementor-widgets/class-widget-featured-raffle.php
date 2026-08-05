<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Featured Raffle widget.
 *
 * Spotlight card for a single raffle: prize image, title, price, progress bar
 * and an Enter button linking to the raffle's WooCommerce product. Resolves the
 * raffle from the explicit `raffle_id` control, falling back to the most recent
 * `is_featured = 1` raffle (then the most recent active raffle) when the
 * "Featured fallback" toggle is enabled.
 */
class Raffle_Widget_Featured_Raffle extends \Elementor\Widget_Base {

    public function get_name() { return 'raffle_featured_raffle'; }
    public function get_title() { return 'Featured Raffle'; }
    public function get_icon() { return 'eicon-star'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'featured', 'spotlight', 'competition' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'featured_fallback', array(
            'label'        => __( 'Featured Fallback', 'wpraffle' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'description'  => __( 'When no raffle is picked, show the most recent featured raffle (then the most recent active one).', 'wpraffle' ),
        ) );
        $this->add_control( 'button_text', array(
            'label'   => __( 'Button Text', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'ENTER NOW',
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Style', 'wpraffle' ) ) );

        $this->add_control( 'card_bg', array(
            'label'     => __( 'Card Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array( '{{WRAPPER}} .wpr-featured-card' => 'background: {{VALUE}};' ),
        ) );
        $this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
            'name'     => 'card_border',
            'label'    => __( 'Card Border', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .wpr-featured-card',
        ) );
        $this->add_responsive_control( 'card_radius', array(
            'label'      => __( 'Card Border Radius', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
            'default'    => array( 'size' => 16 ),
            'selectors'  => array( '{{WRAPPER}} .wpr-featured-card' => 'border-radius: {{SIZE}}px;' ),
        ) );
        $this->add_responsive_control( 'card_padding', array(
            'label'      => __( 'Card Padding', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'default'    => array( 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => false ),
            'selectors'  => array( '{{WRAPPER}} .wpr-featured-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );
        $this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'card_shadow',
            'label'    => __( 'Card Box Shadow', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .wpr-featured-card',
        ) );

        $this->add_control( 'title_color', array(
            'label'     => __( 'Title Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#111827',
            'selectors' => array( '{{WRAPPER}} .wpr-featured-title' => 'color: {{VALUE}};' ),
            'separator' => 'before',
        ) );
        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'title_typography',
            'label'    => __( 'Title Typography', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .wpr-featured-title',
            'fields_options' => array(
                'font_size'   => array( 'default' => array( 'unit' => 'px', 'size' => 24 ) ),
                'font_weight' => array( 'default' => '800' ),
            ),
        ) );

        $this->add_control( 'price_color', array(
            'label'     => __( 'Price Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#d4a017',
            'selectors' => array( '{{WRAPPER}} .wpr-featured-price' => 'color: {{VALUE}};' ),
            'separator' => 'before',
        ) );

        $this->add_control( 'show_progress', array(
            'label'        => __( 'Show Progress Bar', 'wpraffle' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'separator'    => 'before',
        ) );
        $this->add_control( 'progress_color', array(
            'label'     => __( 'Progress Bar Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#10b981',
            'selectors' => array( '{{WRAPPER}} .wpr-featured-progress-inner' => 'background: {{VALUE}};' ),
        ) );

        $this->end_controls_section();
    }

    /**
     * Featured-fallback query. Returns the most recent `is_featured = 1` raffle,
     * or the most recent active raffle if none are flagged featured.
     *
     * @return object|false Raffle row object or false.
     */
    private function resolve_featured() {
        global $wpdb;
        $table = $wpdb->prefix . 'raffles';

        $featured = $wpdb->get_row(
            "SELECT * FROM {$table} WHERE is_featured = 1 ORDER BY id DESC LIMIT 1"
        );
        if ( $featured ) {
            return $featured;
        }

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT 1", 'active' )
        ) ?: false;
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $raffle   = Raffle_Elementor::get_raffle_for_widget( $this );

        // If no explicit raffle resolved and fallback is enabled, try featured.
        if ( ! $raffle && isset( $settings['featured_fallback'] ) && $settings['featured_fallback'] === 'yes' ) {
            $raffle = $this->resolve_featured();
        }

        if ( ! $raffle ) {
            return;
        }

        $ctx         = Raffle_Elementor::get_raffle_context( $raffle );
        $progress    = $ctx['progress'];
        $price_html  = function_exists( 'wpr_price' ) ? wpr_price( $raffle->ticket_price ) : esc_html( $raffle->ticket_price );
        $product_url = ! empty( $raffle->wc_product_id ) ? get_permalink( $raffle->wc_product_id ) : '#';
        $show_prog   = isset( $settings['show_progress'] ) ? $settings['show_progress'] : 'yes';

        echo '<div class="wpr-featured-card">';

        if ( ! empty( $raffle->prize_image ) ) {
            echo '<div class="wpr-featured-image"><img src="' . esc_url( $raffle->prize_image ) . '" alt="' . esc_attr( $raffle->title ) . '" style="width:100%;display:block;" /></div>';
        } else {
            echo '<div class="wpr-featured-image wpr-featured-image--placeholder" style="width:100%;aspect-ratio:16/9;background:linear-gradient(135deg,#6366f1,#8b5cf6);"></div>';
        }

        echo '<div class="wpr-featured-body" style="padding:20px;">';
        echo '<h3 class="wpr-featured-title" style="margin:0 0 8px;">' . esc_html( $raffle->title ) . '</h3>';
        echo '<div class="wpr-featured-price" style="font-size:22px;font-weight:800;margin-bottom:12px;">' . esc_html( $price_html ) . '</div>';

        if ( $show_prog === 'yes' ) {
            echo '<div class="wpr-featured-progress-wrap" style="background:#e5e7eb;height:8px;border-radius:4px;overflow:hidden;margin-bottom:16px;">';
            echo '<div class="wpr-featured-progress-inner" style="width:' . esc_attr( $progress ) . '%;height:100%;border-radius:4px;"></div>';
            echo '</div>';
        }

        if ( ! empty( $raffle->wc_product_id ) ) {
            echo '<a class="wpr-featured-enter" href="' . esc_url( $product_url ) . '" style="display:inline-block;background:#d4a017;color:#fff;padding:12px 24px;border-radius:8px;font-weight:800;text-decoration:none;">' . esc_html( $settings['button_text'] ) . '</a>';
        }

        echo '</div>'; // body
        echo '</div>'; // card
    }

    protected function content_template() {
        ?>
        <div class="wpr-featured-card" style="background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
            <div class="wpr-featured-image wpr-featured-image--placeholder" style="width:100%;aspect-ratio:16/9;background:linear-gradient(135deg,#6366f1,#8b5cf6);"></div>
            <div class="wpr-featured-body" style="padding:20px;">
                <h3 class="wpr-featured-title" style="color:#111827;font-size:24px;font-weight:800;margin:0 0 8px;">Featured Raffle Title</h3>
                <div class="wpr-featured-price" style="color:#d4a017;font-size:22px;font-weight:800;margin-bottom:12px;">£5.00</div>
                <# if ( settings.show_progress !== 'no' ) { #>
                <div class="wpr-featured-progress-wrap" style="background:#e5e7eb;height:8px;border-radius:4px;overflow:hidden;margin-bottom:16px;">
                    <div class="wpr-featured-progress-inner" style="width:65%;background:#10b981;height:100%;border-radius:4px;"></div>
                </div>
                <# } #>
                <a class="wpr-featured-enter" href="#" style="display:inline-block;background:#d4a017;color:#fff;padding:12px 24px;border-radius:8px;font-weight:800;text-decoration:none;">
                    {{{ settings.button_text || 'ENTER NOW' }}}
                </a>
            </div>
        </div>
        <?php
    }
}
