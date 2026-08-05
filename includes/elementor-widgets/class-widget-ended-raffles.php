<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_Ended_Raffles extends \Elementor\Widget_Base {

    public function get_name() { return 'raffle_ended_raffles'; }
    public function get_title() { return 'Ended Raffles Grid'; }
    public function get_icon() { return 'eicon-posts-grid'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'ended', 'finished', 'past', 'results', 'grid' ); }

    protected function register_controls() {

        $this->start_controls_section( 'layout', array( 'label' => 'Layout' ) );
        $this->add_control( 'columns', array(
            'label'   => 'Grid Columns',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
            'default' => '3',
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'visibility', array( 'label' => 'Show / Hide Elements' ) );
        $this->add_control( 'show_image', array(
            'label'   => 'Prize Image',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );
        $this->add_control( 'show_date', array(
            'label'   => 'Draw Date Badge',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );
        $this->add_control( 'show_entries', array(
            'label'   => 'Entry Count',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );
        $this->add_control( 'show_winner', array(
            'label'   => 'Winner Box',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );
        $this->add_control( 'show_instant', array(
            'label'   => 'Instant Wins',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );
        $this->add_control( 'show_video_btn', array(
            'label'   => 'Watch Draw Button',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );
        $this->add_control( 'show_verified_btn', array(
            'label'   => 'Verified Draw Button',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();

        echo do_shortcode( sprintf(
            '[raffle_ended_list columns="%s" show_image="%s" show_winner="%s" show_instant="%s" show_video_btn="%s" show_verified_btn="%s" show_date="%s" show_entries="%s"]',
            esc_attr( $s['columns'] ),
            $s['show_image'] === 'yes' ? 'yes' : 'no',
            $s['show_winner'] === 'yes' ? 'yes' : 'no',
            $s['show_instant'] === 'yes' ? 'yes' : 'no',
            $s['show_video_btn'] === 'yes' ? 'yes' : 'no',
            $s['show_verified_btn'] === 'yes' ? 'yes' : 'no',
            $s['show_date'] === 'yes' ? 'yes' : 'no',
            $s['show_entries'] === 'yes' ? 'yes' : 'no'
        ) );
    }

    /**
     * Static editor preview (the live grid is shortcode-driven and unavailable
     * in the editor canvas).
     */
    protected function content_template() {
        ?>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;padding:8px;">
            <# _.times( 3, function() { #>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                    <div style="height:120px;background:linear-gradient(135deg,#f3f4f6,#e5e7eb);position:relative;">
                        <span style="position:absolute;top:8px;left:8px;background:#dc2626;color:#fff;font-size:11px;padding:2px 8px;border-radius:4px;">Ended</span>
                    </div>
                    <div style="padding:12px;">
                        <div style="height:14px;width:70%;background:#e5e7eb;border-radius:4px;margin-bottom:6px;"></div>
                        <div style="font-size:12px;color:#6b7280;">Winner: #12345 &mdash; J. Smith</div>
                    </div>
                </div>
            <# } ); #>
        </div>
        <?php
    }

}
