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

global $wpdb;
$table_name = $wpdb->prefix . 'mxroute_mailer_logs';

$offset = ( $current_page - 1 ) * $items_per_page;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
$pending_items = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$table_name} WHERE success = 0 AND processed_at IS NULL ORDER BY created_at ASC LIMIT %d OFFSET %d",
		$items_per_page,
		$offset
	)
);

$total = $queue->count_pending();

$total_pages = $items_per_page > 0 ? (int) ceil( $total / $items_per_page ) : 0;
?>

<div class="wrap mxroute-logs-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'MXRoute Email Queue', 'mxroute-mailer' ); ?></h1>

	<div id="mxroute-status-announcer" class="screen-reader-text" aria-live="polite"></div>

	<div class="mxroute-queue-add" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
		<h2 style="margin-top: 0;"><?php esc_html_e( 'Add Email to Queue', 'mxroute-mailer' ); ?></h2>
		<form id="mxroute-queue-add-form">
			<?php wp_nonce_field( 'mxroute_log_manage', 'mxroute_queue_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="mxroute-queue-from"><?php esc_html_e( 'From', 'mxroute-mailer' ); ?></label></th>
					<td><input type="email" id="mxroute-queue-from" name="from_email" required class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="mxroute-queue-to"><?php esc_html_e( 'To', 'mxroute-mailer' ); ?></label></th>
					<td><input type="email" id="mxroute-queue-to" name="to_email" required class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="mxroute-queue-subject"><?php esc_html_e( 'Subject', 'mxroute-mailer' ); ?></label></th>
					<td><input type="text" id="mxroute-queue-subject" name="subject" required class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="mxroute-queue-body"><?php esc_html_e( 'Body', 'mxroute-mailer' ); ?></label></th>
					<td><textarea id="mxroute-queue-body" name="message" required class="large-text" rows="4"></textarea></td>
				</tr>
			</table>
			<?php submit_button( __( 'Add to Queue', 'mxroute-mailer' ), 'primary', 'mxroute-queue-submit' ); ?>
		</form>
	</div>

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
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pending_items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item->id ); ?></td>
						<td><?php echo esc_html( $item->created_at ); ?></td>
						<td><?php echo esc_html( $item->from_email ); ?></td>
						<td><?php echo esc_html( $item->to_email ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $item->subject, 8 ) ); ?></td>
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
