<?php
/**
 * WPRaffle — Admin order-item UI for manual ticket management
 *
 * Phase 5 (1.3.0). Renders an "Assign / view tickets" button on each raffle
 * line item in the WC admin order screen, with a popup showing the allocated
 * ticket numbers for that order item. Matches the per-order hands-on
 * management that the Flintop rival offers.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raffle_Order_Item_UI {

    public function __construct() {
        add_action( 'woocommerce_after_order_itemmeta', array( $this, 'render_item_button' ), 10, 3 );
        add_action( 'wp_ajax_raffle_order_item_tickets', array( $this, 'ajax_item_tickets' ) );
    }

    /**
     * Render a "View tickets" button on raffle line items in the admin order.
     *
     * @param int        $item_id
     * @param WC_Item    $item
     * @param WC_Product $product
     */
    public function render_item_button( $item_id, $item, $product ) {
        if ( ! $item instanceof WC_Order_Item_Product ) {
            return;
        }
        if ( $item->get_meta( '_is_raffle_order' ) !== 'yes' ) {
            return;
        }
        $raffle_id    = (int) $item->get_meta( '_raffle_id' );
        $purchase_id  = (int) $item->get_meta( '_raffle_purchase_id' );
        $ticket_nums  = $item->get_meta( '_raffle_ticket_numbers' );
        if ( ! $raffle_id ) {
            return;
        }
        ?>
        <span class="wpr-item-ui" style="display:inline-block;margin-top:6px;">
            <button type="button" class="button button-small wpr-view-tickets-btn"
                    data-raffle="<?php echo esc_attr( $raffle_id ); ?>"
                    data-purchase="<?php echo esc_attr( $purchase_id ); ?>"
                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'raffle_item_tickets_' . $purchase_id ) ); ?>">
                🎟️ View Tickets
            </button>
        </span>
        <script>
        (function(){
            if (window.__wprItemUIBound) return; window.__wprItemUIBound = true;
            document.addEventListener('click', function(e){
                var btn = e.target.closest('.wpr-view-tickets-btn');
                if (!btn) return;
                e.preventDefault();
                var data = {
                    action: 'raffle_order_item_tickets',
                    raffle_id: btn.dataset.raffle,
                    purchase_id: btn.dataset.purchase,
                    nonce: btn.dataset.nonce
                };
                // Inline prompt rather than a full modal — shows the ticket list.
                fetch(ajaxurl, { method:'POST', credentials:'same-origin',
                    body: new URLSearchParams(data) })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        if (res.success && res.data && res.data.tickets) {
                            alert('Tickets for this order item:\n' + res.data.tickets.join('\n'));
                        } else {
                            alert(res.data && res.data.message ? res.data.message : 'No tickets found.');
                        }
                    });
            });
        })();
        </script>
        <?php
    }

    /**
     * AJAX: return the ticket numbers for a given purchase id (admin only).
     */
    public function ajax_item_tickets() {
        $purchase_id = isset( $_POST['purchase_id'] ) ? absint( $_POST['purchase_id'] ) : 0;
        check_ajax_referer( 'raffle_item_tickets_' . $purchase_id, 'nonce' );
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => 'Security error.' ) );
        }
        if ( ! $purchase_id ) {
            wp_send_json_error( array( 'message' => 'No purchase.' ) );
        }
        global $wpdb;
        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $raffle    = $wpdb->get_row( $wpdb->prepare( "SELECT total_tickets, ticket_prefix, ticket_suffix FROM {$wpdb->prefix}raffles WHERE id = %d", $raffle_id ) );
        $tickets   = $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM {$wpdb->prefix}raffle_tickets WHERE purchase_id = %d ORDER BY ticket_number",
            $purchase_id
        ) );
        $formatted = array();
        foreach ( $tickets as $n ) {
            $formatted[] = Raffle_Tickets::format_ticket_number( (int) $n, $raffle ? (int) $raffle->total_tickets : 0, $raffle );
        }
        wp_send_json_success( array( 'tickets' => $formatted ) );
    }
}
