<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Raffle_Feature_Schema {
    public static function ensure() {
        global $wpdb;
        if ( get_option( 'wpraffle_schema_145_order_items' ) ) { return true; }
        $table = $wpdb->prefix . 'raffle_purchases';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return false; }
        $column = $wpdb->get_var( "SHOW COLUMNS FROM {$table} LIKE 'wc_order_item_id'" );
        if ( ! $column && false === $wpdb->query( "ALTER TABLE {$table} ADD COLUMN wc_order_item_id bigint(20) UNSIGNED DEFAULT NULL" ) ) { return false; }
        $index = $wpdb->get_row( "SHOW INDEX FROM {$table} WHERE Key_name = 'unique_wc_order_item'" );
        if ( ! $index && false === $wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY unique_wc_order_item (wc_order_item_id)" ) ) { return false; }
        update_option( 'wpraffle_schema_145_order_items', 1, false ); return true;
    }
}
