<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Raffle_Widget_All_Competitions extends \Elementor\Widget_Base {
    public function get_name() { return 'raffle_all_competitions'; }
    public function get_title() { return 'All Competitions'; }
    public function get_icon() { return 'eicon-posts-grid'; }
    public function get_categories() { return array( 'raffle-system' ); }

    protected function register_controls() {
        $this->start_controls_section( 'content', array( 'label' => 'Content' ) );
        $this->add_control( 'status', array(
            'label'   => 'Show Raffles',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => array(
                'active'   => 'Active / Live',
                'finished' => 'Finished / Ended',
                'all'      => 'All',
            ),
            'default' => 'active',
        ) );
        $this->add_control( 'columns', array(
            'label'   => 'Columns',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => array( '2' => '2', '3' => '3', '4' => '4' ),
            'default' => '3',
        ) );
        $this->add_control( 'count', array(
            'label'   => 'Max Raffles (0 = all)',
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => 0,
            'min'     => 0,
        ) );
        $this->end_controls_section();

        $this->start_controls_section( 'style', array( 'label' => 'Grid Style' ) );
        $this->add_control( 'gap', array(
            'label'   => 'Gap (px)',
            'type'    => \Elementor\Controls_Manager::SLIDER,
            'range'   => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
            'default' => array( 'size' => 24 ),
        ) );
        $this->end_controls_section();
    }

    protected function render() {
        $s      = $this->get_settings_for_display();
        $status = $s['status'];
        $cols   = absint( $s['columns'] ) ?: 3;
        $count  = absint( $s['count'] );
        $gap    = ! empty( $s['gap']['size'] ) ? intval( $s['gap']['size'] ) : 24;

        global $wpdb;
        $table = $wpdb->prefix . 'raffles';
        $now   = current_time( 'mysql' );

        if ( $status === 'active' ) {
            $raffles = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE status = 'active'
                   AND ( start_date IS NULL OR start_date <= %s )
                   AND ( draw_date IS NULL OR draw_date > %s )
                   AND sold_tickets < total_tickets
                 ORDER BY created_at DESC",
                $now, $now
            ) );
        } elseif ( $status === 'finished' ) {
            $raffles = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE status = 'finished'
                    OR ( status = 'active' AND draw_date IS NOT NULL AND draw_date <= %s )
                    OR ( status = 'active' AND sold_tickets >= total_tickets )
                 ORDER BY draw_date DESC, created_at DESC",
                $now
            ) );
        } else {
            $raffles = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
        }

        if ( empty( $raffles ) ) {
            echo '<div style="text-align:center;padding:40px;color:#6b7280;font-weight:500;">No competitions found.</div>';
            return;
        }

        if ( $count > 0 ) {
            $raffles = array_slice( $raffles, 0, $count );
        }

        // Enqueue countdown JS
        wp_enqueue_script( 'raffle-shop-countdown', RAFFLE_SYSTEM_URL . 'assets/js/shop-countdown.js', array( 'jquery' ), RAFFLE_SYSTEM_VERSION, true );

        // Batch instant-win counts for all cards in one query (avoids N+1).
        $iw_counts = function_exists( 'wpraffle_batch_instant_win_counts' )
            ? wpraffle_batch_instant_win_counts( wp_list_pluck( $raffles, 'id' ) )
            : array();

        $col_pct = round( 100 / $cols );
        ?>
        <div class="raffle-list-grid" style="display:grid;grid-template-columns:repeat(<?php echo esc_attr( $cols ); ?>,1fr);gap:<?php echo esc_attr( $gap ); ?>px;padding:20px 0;">
            <?php foreach ( $raffles as $r ) :
                $raffle  = $r;
                $product = $r->wc_product_id ? wc_get_product( $r->wc_product_id ) : null;
                if ( ! $product ) {
                    $product = new stdClass();
                    $product->_fake = true;
                }
                $iw_count = isset( $iw_counts[ $r->id ] ) ? $iw_counts[ $r->id ] : null;
                ?>
                <div class="raffle-list-item">
                    <?php include RAFFLE_SYSTEM_PATH . 'public/views/raffle-loop-card.php'; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}