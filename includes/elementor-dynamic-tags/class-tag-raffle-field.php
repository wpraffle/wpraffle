<?php
/**
 * Elementor dynamic tag: Raffle Field.
 *
 * Surfaces a single field from the current (or a selected) raffle so it can be
 * bound to ANY native Elementor widget (Heading, Text Editor, Counter, Button,
 * etc.) via the dynamic-tag picker. Resolves against the raffle linked to the
 * current product when no explicit raffle_id is set on the tag.
 *
 * @package WPRaffle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Raffle_Field dynamic tag.
 *
 * Extends \Elementor\Base_Data_Tag so the editor treats it as a data source.
 * Note: Base_Data_Tag lives in Elementor Core (no Pro required) since 3.0.
 */
class Raffle_Tag_Raffle_Field extends \Elementor\Base_Data_Tag {

	/**
	 * Tag internal name.
	 */
	public function get_name() {
		return 'raffle-field';
	}

	/**
	 * Tag title shown in the dynamic-tag picker.
	 */
	public function get_title() {
		return __( 'Raffle Field', 'wpraffle' );
	}

	/**
	 * Tag group (registered by Raffle_Elementor).
	 */
	public function get_group() {
		return 'raffle-system';
	}

	/**
	 * Tag categories — where it can be used (text/number/URL/post-meta slots).
	 */
	public function get_categories() {
		return array(
			\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::URL_CATEGORY,
			\Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
		);
	}

	/**
	 * Field options offered on the tag.
	 *
	 * @return array
	 */
	protected function get_field_options() {
		return array(
			'title'           => __( 'Title', 'wpraffle' ),
			'ticket_price'    => __( 'Ticket Price', 'wpraffle' ),
			'prize_value'     => __( 'Prize Value', 'wpraffle' ),
			'total_tickets'   => __( 'Total Tickets', 'wpraffle' ),
			'sold_tickets'    => __( 'Tickets Sold', 'wpraffle' ),
			'remaining'       => __( 'Tickets Remaining', 'wpraffle' ),
			'progress'        => __( 'Progress %', 'wpraffle' ),
			'draw_date'       => __( 'Draw Date', 'wpraffle' ),
			'start_date'      => __( 'Start Date', 'wpraffle' ),
			'status'          => __( 'Status', 'wpraffle' ),
			'instant_win_qty' => __( 'Instant-Win Count', 'wpraffle' ),
		);
	}

	/**
	 * Register the tag's controls (field selector + optional explicit raffle).
	 */
	protected function register_controls() {
		// Optional explicit raffle (mirrors the widget raffle picker). When left
		// on "current", the tag resolves the raffle linked to the current product.
		$options = array( '' => __( '— Current page raffle —', 'wpraffle' ) );

		if ( function_exists( 'wpraffle_get_raffle' ) ) {
			global $wpdb;
			$table  = $wpdb->prefix . 'raffles';
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table; // phpcs:ignore WordPress.DB
			if ( $exists ) {
				$rows = $wpdb->get_results( "SELECT id, title FROM {$table} ORDER BY created_at DESC LIMIT 200" ); // phpcs:ignore WordPress.DB
				if ( is_array( $rows ) ) {
					foreach ( $rows as $r ) {
						$options[ $r->id ] = '#' . $r->id . ' — ' . $r->title;
					}
				}
			}
		}

		$this->add_control( 'raffle_id', array(
			'label'   => __( 'Raffle', 'wpraffle' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => '',
			'options' => $options,
		) );

		$this->add_control( 'field', array(
			'label'   => __( 'Field', 'wpraffle' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'title',
			'options' => $this->get_field_options(),
		) );
	}

	/**
	 * Resolve the tag value for the frontend / editor preview.
	 *
	 * @param array $options Tag options.
	 * @return mixed String or number; empty string when no raffle resolves.
	 */
	protected function get_value( array $options = array() ) {
		$settings = $this->get_settings();
		$field    = isset( $settings['field'] ) ? $settings['field'] : 'title';
		$raffle   = $this->resolve_raffle( isset( $settings['raffle_id'] ) ? absint( $settings['raffle_id'] ) : 0 );

		if ( ! $raffle ) {
			return '';
		}

		switch ( $field ) {
			case 'title':
				return $raffle->title;
			case 'ticket_price':
				return function_exists( 'wpr_price' ) ? wpr_price( $raffle->ticket_price ) : $raffle->ticket_price;
			case 'prize_value':
				return $raffle->prize_value;
			case 'total_tickets':
				return (int) $raffle->total_tickets;
			case 'sold_tickets':
				return (int) $raffle->sold_tickets;
			case 'remaining':
				return (int) $raffle->total_tickets - (int) $raffle->sold_tickets;
			case 'progress':
				return ( $raffle->total_tickets > 0 )
					? round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 )
					: 0;
			case 'draw_date':
				return $raffle->draw_date ? mysql2date( get_option( 'date_format' ), $raffle->draw_date ) : '';
			case 'start_date':
				return $raffle->start_date ? mysql2date( get_option( 'date_format' ), $raffle->start_date ) : '';
			case 'status':
				return ucfirst( $raffle->status );
			case 'instant_win_qty':
				return $this->count_instant_wins( $raffle->id );
			default:
				return '';
		}
	}

	/**
	 * Resolve the raffle row for the tag.
	 *
	 * @param int $explicit_id Optional explicit raffle id from the tag control.
	 * @return object|false
	 */
	protected function resolve_raffle( $explicit_id = 0 ) {
		if ( $explicit_id && function_exists( 'wpraffle_get_raffle' ) ) {
			return wpraffle_get_raffle( $explicit_id ) ?: false;
		}

		// Fall back to the raffle linked to the current product.
		$current_id = get_the_ID();
		if ( ! $current_id ) {
			return false;
		}
		$raffle_id = (int) get_post_meta( $current_id, '_raffle_id', true );
		if ( ! $raffle_id ) {
			return false;
		}
		if ( function_exists( 'wpraffle_get_raffle' ) ) {
			return wpraffle_get_raffle( $raffle_id ) ?: false;
		}
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}raffles WHERE id = %d", $raffle_id ) ) ?: false;
	}

	/**
	 * Count instant wins for a raffle (available + claimed).
	 *
	 * @param int $raffle_id Raffle id.
	 * @return int
	 */
	protected function count_instant_wins( $raffle_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}raffle_instant_wins WHERE raffle_id = %d",
			$raffle_id
		) );
	}
}
