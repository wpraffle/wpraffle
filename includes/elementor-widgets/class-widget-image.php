<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Image extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_image'; }
    public function get_title() { return 'Raffle Image'; }
    public function get_icon() { return 'eicon-image'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'image', 'prize', 'photo' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'show_cash_badge', array(
            'label'   => __( 'Show Cash Alternative Badge', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Image Style', 'wpraffle' ) ) );
        $this->add_responsive_control( 'border_radius', array(
            'label'      => __( 'Border Radius', 'wpraffle' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
            'default'    => array( 'size' => 12 ),
            'selectors'  => array( '{{WRAPPER}} .raffle-image-card' => 'border-radius: {{SIZE}}px;', '{{WRAPPER}} .raffle-image-card img' => 'border-radius: {{SIZE}}px;' ),
        ) );
        $this->add_control( 'aspect_ratio', array(
            'label'   => __( 'Aspect Ratio', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'native',
            'options' => array(
                'native'  => __( 'Native', 'wpraffle' ),
                'square'  => __( 'Square (1:1)', 'wpraffle' ),
                '43'      => __( '4:3', 'wpraffle' ),
                '169'     => __( '16:9', 'wpraffle' ),
            ),
        ) );
        $this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
            'name'     => 'border',
            'label'    => __( 'Border', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .raffle-image-card',
        ) );
        $this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'shadow',
            'label'    => __( 'Box Shadow', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .raffle-image-card',
        ) );

        $this->add_control( 'badge_heading', array(
            'label'     => __( 'Badge', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::HEADING,
            'separator' => 'before',
        ) );
        $this->add_control( 'overlay_bg', array(
            'label'     => __( 'Badge Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#111827',
            'selectors' => array( '{{WRAPPER}} .raffle-image-overlay-badge' => 'background: {{VALUE}};' ),
        ) );
        $this->add_control( 'overlay_color', array(
            'label'     => __( 'Badge Text Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#fbbf24',
            'selectors' => array( '{{WRAPPER}} .raffle-image-overlay-badge' => 'color: {{VALUE}};' ),
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle || ! $raffle->prize_image ) {
            echo '<p style="color:#6b7280;text-align:center;padding:20px;">No raffle image available.</p>';
            return;
        }
        $settings   = $this->get_settings_for_display();
        $show_badge = $settings['show_cash_badge'] === 'yes' && ! empty( $raffle->enable_cash_alternative );

        $ratio_style = '';
        $img_class   = '';
        if ( $settings['aspect_ratio'] !== 'native' ) {
            $paddings = array( 'square' => '100%', '43' => '75%', '169' => '56.25%' );
            $pad = isset( $paddings[ $settings['aspect_ratio'] ] ) ? $paddings[ $settings['aspect_ratio'] ] : '100%';
            $ratio_style = ' style="position:relative;width:100%;padding-top:' . esc_attr( $pad ) . ';"';
            $img_class   = ' raffle-image-fit';
        }
        ?>
        <div class="raffle-image-card"<?php echo $ratio_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php if ( $settings['aspect_ratio'] !== 'native' ) : ?>
                <img class="raffle-image-fit" src="<?php echo esc_url( $raffle->prize_image ); ?>" alt="<?php echo esc_attr( $raffle->title ); ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
            <?php else : ?>
                <img src="<?php echo esc_url( $raffle->prize_image ); ?>" alt="<?php echo esc_attr( $raffle->title ); ?>" style="width:100%;display:block;">
            <?php endif; ?>
            <?php if ( $show_badge ) : ?>
                <div class="raffle-image-overlay-badge">+ Cash Alternative Available</div>
            <?php endif; ?>
        </div>
        <?php
    }

    protected function content_template() {
        ?>
        <div class="raffle-image-card" style="border-radius:12px;overflow:hidden;background:#f3f4f6;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;">
            <span style="font-size:40px;">🖼️</span>
        </div>
        <?php
    }
}
