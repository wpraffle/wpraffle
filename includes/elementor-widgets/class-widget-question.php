<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Raffle Skill Question widget.
 *
 * Renders the configured skill question + radio options. Reads the canonical
 * schema columns (question_text, question_answers, enable_question) and emits
 * the canonical radio name `raffle_skill_answer` so public.js can capture the
 * answer index and forward it with the purchase request.
 */
class Raffle_Widget_Question extends \Elementor\Widget_Base {

    public function get_name() { return 'raffle_question'; }
    public function get_title() { return 'Raffle Skill Question'; }
    public function get_icon() { return 'eicon-form-horizontal'; }
    public function get_categories() { return array( 'raffle-system' ); }
    public function get_keywords() { return array( 'raffle', 'skill', 'question', 'quiz', 'answer' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => __( 'Content', 'wpraffle' ) ) );
        Raffle_Elementor::register_raffle_id_control( $this );
        $this->add_control( 'show_question', array(
            'label'   => __( 'Show Question', 'wpraffle' ),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => __( 'Style', 'wpraffle' ) ) );
        $this->add_control( 'title_color', array(
            'label'     => __( 'Title Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#111827',
            'selectors' => array( '{{WRAPPER}} .raffle-question-title' => 'color: {{VALUE}};' ),
        ) );
        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'title_typography',
            'label'    => __( 'Title Typography', 'wpraffle' ),
            'selector' => '{{WRAPPER}} .raffle-question-title',
            'fields_options' => array(
                'font_size'   => array( 'default' => array( 'unit' => 'px', 'size' => 18 ) ),
                'font_weight' => array( 'default' => '700' ),
            ),
        ) );
        $this->add_control( 'option_bg', array(
            'label'     => __( 'Option Background', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array( '{{WRAPPER}} .raffle-question-option-card' => 'background: {{VALUE}};' ),
        ) );
        $this->add_control( 'option_border', array(
            'label'     => __( 'Option Border Color', 'wpraffle' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#e5e7eb',
            'selectors' => array( '{{WRAPPER}} .raffle-question-option-card' => 'border-color: {{VALUE}};' ),
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $raffle = Raffle_Elementor::get_raffle_for_widget( $this );
        if ( ! $raffle ) {
            return;
        }
        $s = $this->get_settings_for_display();
        if ( $s['show_question'] !== 'yes' ) {
            return;
        }

        // Canonical schema: question_text + question_answers (JSON array).
        if ( empty( $raffle->enable_question ) || empty( $raffle->question_text ) ) {
            return;
        }

        $answers = array();
        if ( ! empty( $raffle->question_answers ) ) {
            $decoded = json_decode( $raffle->question_answers, true );
            if ( is_array( $decoded ) ) {
                $answers = $decoded;
            }
        }
        if ( empty( $answers ) ) {
            return;
        }
        ?>
        <div class="raffle-question-wrapper">
            <h3 class="raffle-question-title"><?php echo esc_html( $raffle->question_text ); ?></h3>
            <div class="raffle-question-options">
                <?php foreach ( $answers as $idx => $opt ) : ?>
                    <label class="raffle-question-option-card">
                        <input type="radio" name="raffle_skill_answer" value="<?php echo esc_attr( $idx ); ?>">
                        <span class="raffle-question-option-dot"></span>
                        <span class="raffle-question-option-text"><?php echo esc_html( $opt ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="raffle-question-error" style="display: none;"></div>
        </div>
        <?php
    }

    protected function content_template() {
        ?>
        <div class="raffle-question-wrapper" style="font-family:sans-serif;">
            <h3 class="raffle-question-title" style="font-size:18px;font-weight:700;margin:0 0 12px;">Skill Question (preview)</h3>
            <div class="raffle-question-options" style="display:flex;flex-direction:column;gap:8px;">
                <label class="raffle-question-option-card" style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;">
                    <span style="width:16px;height:16px;border-radius:50%;border:2px solid #d4a017;display:inline-block;"></span>
                    Option A
                </label>
                <label class="raffle-question-option-card" style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;">
                    <span style="width:16px;height:16px;border-radius:50%;border:2px solid #d4a017;display:inline-block;"></span>
                    Option B
                </label>
            </div>
        </div>
        <?php
    }
}
