<?php
/**
 * MXRoute Mailer queue status page view.
 *
 * Read-only view of pending emails in the queue.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

$queue = new MXRoute_Queue();

$current_page   = max( 1, intval( wp_unslash( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$items_per_page = 20;

$pending_items = $queue->get_pending_paginated( $items_per_page, $current_page );

$total = $queue->count_pending();

$total_pages = $items_per_page > 0 ? (int) ceil( $total / $items_per_page ) : 0;
?>

<div class="wrap mxroute-logs-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'MXRoute Email Queue', 'mxroute-mailer' ); ?></h1>

	<div id="mxroute-status-announcer" class="screen-reader-text" aria-live="polite"></div>

	<p class="description">
		<?php
		printf(
			/* translators: %s: number of pending emails */
			esc_html( _n( '%s email pending.', '%s emails pending.', $total, 'mxroute-mailer' ) ),
			esc_html( number_format_i18n( $total ) )
		);
		?>
	</p>

	<?php if ( ! empty( $pending_items ) ) : ?>
		<table class="widefat striped mxroute-logs-table">
			<thead>
				<tr>
					<th scope="col" style="width:50px;"><?php esc_html_e( 'ID', 'mxroute-mailer' ); ?></th>
					<th scope="col" style="width:160px;"><?php esc_html_e( 'Queued', 'mxroute-mailer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'From', 'mxroute-mailer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'To', 'mxroute-mailer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Subject', 'mxroute-mailer' ); ?></th>
					<th scope="col" style="width:100px;"><?php esc_html_e( 'Attachments', 'mxroute-mailer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$queue_helper = new MXRoute_Queue();
				foreach ( $pending_items as $item ) :
					$att_info  = $queue_helper->get_attachment_info( $item->attachments ?? '[]' );
					$att_count = count( $att_info );
					$att_ok    = 0;
					foreach ( $att_info as $att ) {
						if ( $att['stored_exists'] || '' === $att['stored_path'] ) {
							++$att_ok;
						}
					}
					?>
					<tr class="mxroute-queue-row" data-queue-id="<?php echo esc_attr( $item->id ); ?>">
						<td><?php echo esc_html( $item->id ); ?></td>
						<td><?php echo esc_html( $item->created_at ); ?></td>
						<td><?php echo esc_html( $item->from_email ); ?></td>
						<td><?php echo esc_html( $item->to_email ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $item->subject, 8 ) ); ?></td>
						<td>
							<?php if ( 0 === $att_count ) : ?>
								<?php esc_html_e( 'None', 'mxroute-mailer' ); ?>
							<?php elseif ( $att_ok === $att_count ) : ?>
								<span class="mxroute-status-badge mxroute-success"><?php echo esc_html( sprintf( _n( '%d file', '%d files', $att_count, 'mxroute-mailer' ), $att_count ) ); ?></span>
							<?php else : ?>
								<span class="mxroute-status-badge mxroute-fail"><?php echo esc_html( sprintf( __( '%d/%d stored', 'mxroute-mailer' ), $att_ok, $att_count ) ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $current_page,
								'total'     => $total_pages,
								'prev_text' => __( '&laquo; Previous', 'mxroute-mailer' ),
								'next_text' => __( 'Next &raquo;', 'mxroute-mailer' ),
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Queue is empty.', 'mxroute-mailer' ); ?></p>
	<?php endif; ?>
</div>
