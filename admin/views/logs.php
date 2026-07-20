<?php
/**
 * MXRoute Mailer logs page view.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

$logger = new MXRoute_Logger();

$current_page  = max( 1, intval( wp_unslash( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$logs_per_page = 20;

$filters = array(
	'search'     => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'success'    => in_array( wp_unslash( $_GET['status'] ?? '' ), array( '-1', '1' ), true ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'from_email' => sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'date_from'  => sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'date_to'    => sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
);

$result      = $logger->get_logs( $logs_per_page, $current_page, $filters );
$logs        = $result['logs'];
$total       = $result['total'];
$total_pages = $result['pages'];
?>

<div class="wrap mxroute-logs-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'MXRoute Email Logs', 'mxroute-mailer' ); ?></h1>
	<span id="mxroute-clear-warning" class="screen-reader-text"><?php esc_html_e( 'This will permanently delete all log entries and cannot be undone.', 'mxroute-mailer' ); ?></span>
	<button type="button" class="page-title-action mxroute-clear-logs" aria-describedby="mxroute-clear-warning"><?php esc_html_e( 'Clear All Logs', 'mxroute-mailer' ); ?></button>

	<div id="mxroute-status-announcer" class="screen-reader-text" aria-live="polite"></div>

	<form method="get" class="mxroute-filters-form">
		<input type="hidden" name="page" value="mxroute-logs" />

		<div class="mxroute-filters">
			<label for="mxroute-filter-search" class="screen-reader-text"><?php esc_html_e( 'Search subject, from, to...', 'mxroute-mailer' ); ?></label>
			<input type="search" id="mxroute-filter-search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>"
					placeholder="<?php esc_attr_e( 'Search subject, from, to...', 'mxroute-mailer' ); ?>" />

			<label for="mxroute-filter-status" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'mxroute-mailer' ); ?></label>
			<select id="mxroute-filter-status" name="status">
				<option value=""><?php esc_html_e( 'All Status', 'mxroute-mailer' ); ?></option>
				<option value="1" <?php selected( $filters['success'], '1' ); ?>><?php esc_html_e( 'Sent', 'mxroute-mailer' ); ?></option>
				<option value="-1" <?php selected( $filters['success'], '-1' ); ?>><?php esc_html_e( 'Failed', 'mxroute-mailer' ); ?></option>
			</select>

			<label for="mxroute-filter-from" class="screen-reader-text"><?php esc_html_e( 'From email...', 'mxroute-mailer' ); ?></label>
			<input type="email" id="mxroute-filter-from" name="from" value="<?php echo esc_attr( $filters['from_email'] ); ?>"
					placeholder="<?php esc_attr_e( 'From email...', 'mxroute-mailer' ); ?>" />

			<label for="mxroute-date-from" class="screen-reader-text"><?php esc_html_e( 'Date from', 'mxroute-mailer' ); ?></label>
			<input type="date" id="mxroute-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
			<label for="mxroute-date-to" class="screen-reader-text"><?php esc_html_e( 'Date to', 'mxroute-mailer' ); ?></label>
			<input type="date" id="mxroute-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mxroute-mailer' ); ?></button>
		</div>
	</form>

	<p class="description">
	<?php
		printf(
			/* translators: %s: number of emails found */
			esc_html( _n( '%s email found.', '%s emails found.', $total, 'mxroute-mailer' ) ),
			esc_html( number_format_i18n( $total ) )
		);
		?>
	</p>

	<?php if ( ! empty( $logs ) ) : ?>
		<div class="tablenav top">
			<div class="alignleft actions bulkactions">
				<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'mxroute-mailer' ); ?></label>
				<select name="action" id="bulk-action-selector-top">
					<option value=""><?php esc_html_e( 'Bulk Actions', 'mxroute-mailer' ); ?></option>
					<option value="bulk-requeue"><?php esc_html_e( 'Re-queue', 'mxroute-mailer' ); ?></option>
					<option value="bulk-delete"><?php esc_html_e( 'Delete', 'mxroute-mailer' ); ?></option>
				</select>
				<input type="submit" id="mxroute-bulk-apply" class="button action" value="<?php esc_attr_e( 'Apply', 'mxroute-mailer' ); ?>" />
			</div>
		</div>

		<table class="widefat striped mxroute-logs-table">
			<thead>
				<tr>
					<th class="check-column" style="width:40px;">
						<label for="mxroute-select-all" class="screen-reader-text"><?php esc_html_e( 'Select all logs', 'mxroute-mailer' ); ?></label>
						<input type="checkbox" id="mxroute-select-all" />
					</th>
					<th scope="col" style="width:50px;"><?php esc_html_e( 'ID', 'mxroute-mailer' ); ?></th>
					<th scope="col" style="width:80px;"><?php esc_html_e( 'Status', 'mxroute-mailer' ); ?></th>
					<th scope="col" style="width:160px;"><?php esc_html_e( 'Timestamp', 'mxroute-mailer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'From', 'mxroute-mailer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'To', 'mxroute-mailer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Subject', 'mxroute-mailer' ); ?></th>
					<th scope="col" style="width:80px;"><?php esc_html_e( 'Transport', 'mxroute-mailer' ); ?></th>
					<th scope="col" style="width:80px;"><?php esc_html_e( 'Actions', 'mxroute-mailer' ); ?></th>
				</tr>
			</thead>
		<tbody>
			<?php foreach ( $logs as $log ) : ?>
				<?php
				// translators: %d: log entry ID.
				$select_label = sprintf( __( 'Select log %d', 'mxroute-mailer' ), $log->id );
				// translators: %d: log entry ID.
				$view_label = sprintf( __( 'View log %d', 'mxroute-mailer' ), $log->id );
				// translators: %d: log entry ID.
				$requeue_label = sprintf( __( 'Re-queue log %d', 'mxroute-mailer' ), $log->id );
				// translators: %d: log entry ID.
				$delete_label = sprintf( __( 'Delete log %d', 'mxroute-mailer' ), $log->id );
				?>
				<tr class="mxroute-log-row" data-log-id="<?php echo esc_attr( $log->id ); ?>">
					<td class="check-column">
						<input type="checkbox" name="log_ids[]" value="<?php echo esc_attr( $log->id ); ?>" class="mxroute-log-checkbox" aria-label="<?php echo esc_attr( $select_label ); ?>" />
					</td>
					<td><?php echo esc_html( $log->id ); ?></td>
				<td>
					<span class="mxroute-status-badge <?php echo esc_attr( $log->success > 0 ? 'mxroute-success' : 'mxroute-fail' ); ?>" role="status">
						<?php echo esc_html( $log->success > 0 ? __( 'Sent', 'mxroute-mailer' ) : __( 'Fail', 'mxroute-mailer' ) ); ?>
					</span>
				</td>
					<td><?php echo esc_html( $log->timestamp ); ?></td>
					<td><?php echo esc_html( $log->from_email ); ?></td>
					<td><?php echo esc_html( $log->to_email ); ?></td>
					<td><?php echo esc_html( wp_trim_words( $log->subject, 8 ) ); ?></td>
					<td><?php echo esc_html( 'smtp' === ( $log->transport ?? '' ) ? __( 'SMTP', 'mxroute-mailer' ) : __( 'API', 'mxroute-mailer' ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'tools.php?page=mxroute-log-view&id=' . $log->id ), 'mxroute_log_view_' . $log->id ) ); ?>" class="button button-small" aria-label="<?php echo esc_attr( $view_label ); ?>"><?php esc_html_e( 'View', 'mxroute-mailer' ); ?></a>
						<button class="button button-small mxroute-requeue-log" data-log-id="<?php echo esc_attr( $log->id ); ?>" aria-label="<?php echo esc_attr( $requeue_label ); ?>"><?php esc_html_e( 'Re-queue', 'mxroute-mailer' ); ?></button>
						<button class="button button-small mxroute-delete-log" data-log-id="<?php echo esc_attr( $log->id ); ?>" aria-label="<?php echo esc_attr( $delete_label ); ?>"><?php esc_html_e( 'Delete', 'mxroute-mailer' ); ?></button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
		</table>

		<div class="tablenav bottom">
			<div class="alignleft actions bulkactions">
				<label for="bulk-action-selector-bottom" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'mxroute-mailer' ); ?></label>
				<select name="action" id="bulk-action-selector-bottom">
					<option value=""><?php esc_html_e( 'Bulk Actions', 'mxroute-mailer' ); ?></option>
					<option value="bulk-requeue"><?php esc_html_e( 'Re-queue', 'mxroute-mailer' ); ?></option>
					<option value="bulk-delete"><?php esc_html_e( 'Delete', 'mxroute-mailer' ); ?></option>
				</select>
				<input type="submit" id="mxroute-bulk-apply-bottom" class="button action" value="<?php esc_attr_e( 'Apply', 'mxroute-mailer' ); ?>" />
			</div>

			<?php if ( $total_pages > 1 ) : ?>
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
			<?php endif; ?>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'No logs found.', 'mxroute-mailer' ); ?></p>
	<?php endif; ?>
</div>
