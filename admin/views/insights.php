<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$summary = $report['summary'];
$metrics = array(
    array( __( 'Gross sales', 'wpraffle' ), $money( $summary->gross ), 'green' ),
    array( __( 'Completed purchases', 'wpraffle' ), esc_html( number_format_i18n( $summary->purchases ) ), 'blue' ),
    array( __( 'Confirmed tickets', 'wpraffle' ), esc_html( number_format_i18n( $summary->tickets ) ), 'blue' ),
    array( __( 'Unique buyers', 'wpraffle' ), esc_html( number_format_i18n( $summary->customers ) ), 'blue' ),
    array( __( 'Repeat buyers', 'wpraffle' ), esc_html( number_format_i18n( $report['repeat_buyers'] ) ), 'blue' ),
    array( __( 'Average purchase', 'wpraffle' ), $money( $summary->purchases ? $summary->gross / $summary->purchases : 0 ), 'green' ),
);
?>
<div class="wrap rs-wrap rs-feature-page">
    <header class="rs-page-header"><div><h1 class="rs-page-title"><?php esc_html_e( 'Insights', 'wpraffle' ); ?></h1><p class="rs-page-subtitle"><?php esc_html_e( 'Sales and customer activity across your raffles.', 'wpraffle' ); ?></p></div></header>
    <dl class="rs-stats-grid rs-insights-metrics">
        <?php foreach ( $metrics as $metric ) : ?>
        <div class="rs-stat-card border-<?php echo esc_attr( $metric[2] ); ?>"><div class="rs-stat-body"><dt class="rs-stat-label"><?php echo esc_html( $metric[0] ); ?></dt><dd class="rs-stat-number"><?php echo wp_kses_post( $metric[1] ); ?></dd></div></div>
        <?php endforeach; ?>
    </dl>
    <section class="rs-card"><h2 class="rs-card-title"><?php esc_html_e( 'Top raffles by sales', 'wpraffle' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Up to 100 raffles, ordered by completed-purchase sales.', 'wpraffle' ); ?></p>
        <div class="rs-feature-table-scroll" role="region" aria-label="<?php esc_attr_e( 'Raffle sales', 'wpraffle' ); ?>" tabindex="0"><table class="widefat striped rs-table"><thead><tr>
            <th scope="col"><?php esc_html_e( 'Raffle', 'wpraffle' ); ?></th><th scope="col"><?php esc_html_e( 'Purchases', 'wpraffle' ); ?></th><th scope="col"><?php esc_html_e( 'Tickets', 'wpraffle' ); ?></th><th scope="col"><?php esc_html_e( 'Buyers', 'wpraffle' ); ?></th><th scope="col"><?php esc_html_e( 'Gross sales', 'wpraffle' ); ?></th>
        </tr></thead><tbody>
        <?php if ( empty( $report['competitions'] ) ) : ?><tr><td colspan="5" class="rs-table-empty"><?php esc_html_e( 'No raffle sales to display yet.', 'wpraffle' ); ?></td></tr><?php endif; ?>
        <?php foreach ( $report['competitions'] as $row ) : ?><tr><th scope="row"><a href="<?php echo esc_url( admin_url( 'admin.php?page=raffle-list&action=edit&id=' . absint( $row->id ) ) ); ?>"><?php echo esc_html( $row->title ); ?></a></th><td><?php echo esc_html( number_format_i18n( $row->purchases ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row->tickets ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row->customers ) ); ?></td><td><?php echo $money( $row->gross ); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    <p class="description rs-feature-note"><?php esc_html_e( 'Based on current completed purchases. Sales are gross amounts, not profit or historical net revenue. Removed or refunded records may change these totals. Buyers are counted by email address.', 'wpraffle' ); ?></p>
</div>
