<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Image extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_image'; }
    public function get_title() { return 'Raffle Image'; }
    public function get_icon() { return 'eicon-image'; }
    public function get_categories() { return array( 'raffle-system' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );
        $this->add_control( 'show_cash_badge', array(
            'label' => 'Show Cash Alternative Badge',
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );
        $this->add_control( 'border_radius', array(
            'label' => 'Border Radius (px)',
            'type' => \Elementor\Controls_Manager::SLIDER,
            'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
            'default' => array( 'size' => 12 ),
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => 'Style' ) );
        $this->add_control( 'overlay_bg', array(
            'label' => 'Badge Background',
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#111827',
        ) );
        $this->add_control( 'overlay_color', array(
            'label' => 'Badge Text Color',
            'type' => \Elementor\Controls_Manager::COLOR,
            'default' => '#fbbf24',
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_current_raffle();
        if ( ! $raffle || ! $raffle->prize_image ) {
            echo '<p style="color:#6b7280;text-align:center;padding:20px;">No raffle image available.</p>';
            return;
        }
        $settings = $this->get_settings_for_display();
        $radius = $settings['border_radius']['size'] ?? 12;
        $show_badge = $settings['show_cash_badge'] === 'yes' && ! empty( $raffle->enable_cash_alternative );
        ?>
        <div class="raffle-image-card" style="border-radius:<?php echo esc_attr( $radius ); ?>px;">
            <img src="<?php echo esc_url( $raffle->prize_image ); ?>" alt="<?php echo esc_attr( $raffle->title ); ?>">
            <?php if ( $show_badge ) : ?>
                <div class="raffle-image-overlay-badge" style="background:<?php echo esc_attr( $settings['overlay_bg'] ); ?>;color:<?php echo esc_attr( $settings['overlay_color'] ); ?>;">+ Cash Alternative Available</div>
            <?php endif; ?>
        </div>
        <?php
    }
}
