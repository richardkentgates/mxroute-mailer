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
	'success'    => in_array( wp_unslash( $_GET['status'] ?? '' ), array( '0', '1' ), true ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
	<button type="button" class="page-title-action mxroute-clear-logs"><?php esc_html_e( 'Clear All Logs', 'mxroute-mailer' ); ?></button>

	<form method="get" class="mxroute-filters-form">
		<input type="hidden" name="page" value="mxroute-logs" />

		<div class="mxroute-filters">
			<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>"
					placeholder="<?php esc_attr_e( 'Search subject, from, to...', 'mxroute-mailer' ); ?>" />

			<select name="status">
				<option value=""><?php esc_html_e( 'All Status', 'mxroute-mailer' ); ?></option>
				<option value="1" <?php selected( $filters['success'], '1' ); ?>><?php esc_html_e( 'Success', 'mxroute-mailer' ); ?></option>
				<option value="0" <?php selected( $filters['success'], '0' ); ?>><?php esc_html_e( 'Failed', 'mxroute-mailer' ); ?></option>
			</select>

			<input type="email" name="from" value="<?php echo esc_attr( $filters['from_email'] ); ?>"
					placeholder="<?php esc_attr_e( 'From email...', 'mxroute-mailer' ); ?>" />

			<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
			<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />

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

	<?php if ( empty( $logs ) ) : ?>
		<p><?php esc_html_e( 'No logs found.', 'mxroute-mailer' ); ?></p>
	<?php else : ?>
		<table class="widefat striped mxroute-logs-table">
			<thead>
				<tr>
					<th style="width:50px;"><?php esc_html_e( 'ID', 'mxroute-mailer' ); ?></th>
					<th style="width:80px;"><?php esc_html_e( 'Status', 'mxroute-mailer' ); ?></th>
					<th style="width:160px;"><?php esc_html_e( 'Timestamp', 'mxroute-mailer' ); ?></th>
					<th><?php esc_html_e( 'From', 'mxroute-mailer' ); ?></th>
					<th><?php esc_html_e( 'To', 'mxroute-mailer' ); ?></th>
					<th><?php esc_html_e( 'Subject', 'mxroute-mailer' ); ?></th>
					<th style="width:80px;"><?php esc_html_e( 'Actions', 'mxroute-mailer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr class="mxroute-log-row" data-log-id="<?php echo esc_attr( $log->id ); ?>">
						<td><?php echo esc_html( $log->id ); ?></td>
						<td>
							<span class="mxroute-status-badge <?php echo esc_attr( $log->success ? 'mxroute-success' : 'mxroute-fail' ); ?>">
								<?php echo esc_html( $log->success ? __( 'Sent', 'mxroute-mailer' ) : __( 'Fail', 'mxroute-mailer' ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $log->timestamp ); ?></td>
						<td><?php echo esc_html( $log->from_email ); ?></td>
						<td><?php echo esc_html( $log->to_email ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $log->subject, 8 ) ); ?></td>
						<td>
							<button class="button button-small mxroute-view-log" data-log-id="<?php echo esc_attr( $log->id ); ?>"><?php esc_html_e( 'View', 'mxroute-mailer' ); ?></button>
							<button class="button button-small mxroute-delete-log" data-log-id="<?php echo esc_attr( $log->id ); ?>"><?php esc_html_e( 'Delete', 'mxroute-mailer' ); ?></button>
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
	<?php endif; ?>

	<div id="mxroute-log-modal" class="mxroute-modal" style="display:none;">
		<div class="mxroute-modal-content">
			<span class="mxroute-modal-close">&times;</span>
			<h3><?php esc_html_e( 'Email Log Details', 'mxroute-mailer' ); ?></h3>
			<div id="mxroute-modal-body"></div>
		</div>
	</div>
</div>
