<?php
/**
 * WPRaffle — Gutenberg blocks (no-build)
 *
 * Phase 5 (1.3.0). Registers a starter set of dynamic blocks so non-Elementor
 * sites can drop raffle widgets into the block editor. Uses server-side render
 * (the block content is produced by PHP from a `raffle_id` attribute), so no
 * build step / @wordpress/scripts toolchain is required — the editor-side JS
 * only renders the inspector control + a placeholder.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Blocks {

    public function __construct() {
        add_action( 'init', array( $this, 'register' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor' ) );
    }

    /**
     * The starter block set. Each maps a block name → an existing public
     * shortcode or render callback, so the rendering logic is shared with the
     * Elementor widgets and shortcodes (single source of truth for output).
     */
    private static function block_defs() {
        return array(
            'raffle/countdown' => array(
                'title'       => 'Raffle Countdown',
                'description' => 'Live countdown to the draw date.',
                'render'      => array( __CLASS__, 'render_countdown' ),
            ),
            'raffle/progress' => array(
                'title'       => 'Raffle Progress',
                'description' => 'Tickets sold vs. total.',
                'render'      => array( __CLASS__, 'render_progress' ),
            ),
            'raffle/entry-button' => array(
                'title'       => 'Raffle Enter Button',
                'description' => 'Call-to-action button to enter the raffle.',
                'render'      => array( __CLASS__, 'render_entry_button' ),
            ),
            'raffle/instant-wins' => array(
                'title'       => 'Raffle Instant Wins',
                'description' => 'Table of instant-win prizes for a raffle.',
                'render'      => array( __CLASS__, 'render_instant_wins' ),
            ),
            'raffle/list' => array(
                'title'       => 'Raffle List',
                'description' => 'Grid of active competitions.',
                'render'      => array( __CLASS__, 'render_list' ),
            ),
        );
    }

    public function register() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }
        foreach ( self::block_defs() as $name => $def ) {
            register_block_type( $name, array(
                'editor_script'   => 'wpraffle-blocks-editor',
                'render_callback' => $def['render'],
                'attributes'      => array(
                    'raffle_id' => array(
                        'type' => 'number',
                        'default' => 0,
                    ),
                ),
            ) );
        }
    }

    public function enqueue_editor() {
        wp_enqueue_script(
            'wpraffle-blocks-editor',
            RAFFLE_SYSTEM_URL . 'assets/js/blocks/editor.js',
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-api-fetch' ),
            RAFFLE_SYSTEM_VERSION,
            true
        );
        // Pass the list of available raffles + the block definitions to the editor script.
        $raffles = array();
        global $wpdb;
        if ( function_exists( 'wpraffle_table_exists' ) ? wpraffle_table_exists( $wpdb->prefix . 'raffles' ) : true ) {
            $rows = $wpdb->get_results( "SELECT id, title FROM {$wpdb->prefix}raffles ORDER BY id DESC LIMIT 100" );
            if ( is_array( $rows ) ) {
                foreach ( $rows as $r ) {
                    $raffles[] = array( 'value' => (int) $r->id, 'label' => '#' . $r->id . ' — ' . $r->title );
                }
            }
        }
        wp_localize_script( 'wpraffle-blocks-editor', 'wpraffleBlocks', array(
            'raffles' => $raffles,
            'blocks'  => self::block_defs(),
        ) );
    }

    /* ── Render callbacks (server-side) ── */

    private static function raffle_id_attr( $attrs ) {
        return isset( $attrs['raffle_id'] ) ? absint( $attrs['raffle_id'] ) : 0;
    }

    public static function render_countdown( $attrs ) {
        $id = self::raffle_id_attr( $attrs );
        return $id ? do_shortcode( '[raffle id="' . $id . '"]' ) : '<!-- no raffle selected -->';
    }

    public static function render_progress( $attrs ) {
        $id = self::raffle_id_attr( $attrs );
        if ( ! $id ) {
            return '<!-- no raffle selected -->';
        }
        global $wpdb;
        $r = $wpdb->get_row( $wpdb->prepare( "SELECT sold_tickets, total_tickets FROM {$wpdb->prefix}raffles WHERE id = %d", $id ) );
        if ( ! $r ) {
            return '';
        }
        $pct = $r->total_tickets > 0 ? min( 100, round( ( $r->sold_tickets / $r->total_tickets ) * 100 ) ) : 0;
        return '<div class="wpr-progress" data-raffle-id="' . esc_attr( $id ) . '"><div class="wpr-progress-bar" style="width:' . esc_attr( $pct ) . '%"></div></div>';
    }

    public static function render_entry_button( $attrs ) {
        $id = self::raffle_id_attr( $attrs );
        return $id ? '<div class="wpr-enter-btn" data-raffle-id="' . esc_attr( $id ) . '"></div>' : '<!-- no raffle selected -->';
    }

    public static function render_instant_wins( $attrs ) {
        $id = self::raffle_id_attr( $attrs );
        return $id ? do_shortcode( '[raffle_entry_list id="' . $id . '"]' ) : '<!-- no raffle selected -->';
    }

    public static function render_list( $attrs ) {
        return do_shortcode( '[raffle_list]' );
    }
}
