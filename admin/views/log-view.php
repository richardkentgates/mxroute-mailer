<?php
/**
 * MXRoute Mailer single log view.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

$log_id = isset( $_GET['id'] ) ? intval( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'mxroute_log_view_' . $log_id ) ) {
	wp_die( esc_html__( 'Security check failed.', 'mxroute-mailer' ), 403 );
}

$logger = new MXRoute_Logger();
$log    = $logger->get_log( $log_id );

if ( ! $log ) {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Log Entry Not Found', 'mxroute-mailer' ); ?></h1>
		<p>
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=mxroute-logs' ) ); ?>">
				<span aria-hidden="true">&laquo;</span> <?php esc_html_e( 'Back to Logs', 'mxroute-mailer' ); ?>
			</a>
		</p>
	</div>
	<?php
	return;
}

$request  = json_decode( $log->api_request, true );
$response = json_decode( $log->api_response, true );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Email Log Detail', 'mxroute-mailer' ); ?></h1>

	<p>
		<a href="<?php echo esc_url( admin_url( 'tools.php?page=mxroute-logs' ) ); ?>">
			<span aria-hidden="true">&laquo;</span> <?php esc_html_e( 'Back to Logs', 'mxroute-mailer' ); ?>
		</a>
	</p>

	<table class="widefat">
		<tr>
			<th scope="row" style="width:150px;"><?php esc_html_e( 'Timestamp', 'mxroute-mailer' ); ?></th>
			<td><?php echo esc_html( $log->timestamp ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'mxroute-mailer' ); ?></th>
		<td>
			<span class="mxroute-status-badge <?php echo esc_attr( $log->success > 0 ? 'mxroute-success' : 'mxroute-fail' ); ?>" role="status">
				<?php echo esc_html( $log->success > 0 ? __( 'Sent', 'mxroute-mailer' ) : __( 'Failed', 'mxroute-mailer' ) ); ?>
			</span>
		</td>
		</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'From', 'mxroute-mailer' ); ?></th>
		<td><?php echo esc_html( $log->from_email ); ?></td>
	</tr>
	<?php if ( ! empty( $log->reply_to ) ) : ?>
	<tr>
		<th scope="row"><?php esc_html_e( 'Reply-To', 'mxroute-mailer' ); ?></th>
		<td><?php echo esc_html( $log->reply_to ); ?></td>
	</tr>
	<?php endif; ?>
	<tr>
		<th scope="row"><?php esc_html_e( 'To', 'mxroute-mailer' ); ?></th>
		<td><?php echo esc_html( $log->to_email ); ?></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Subject', 'mxroute-mailer' ); ?></th>
		<td><?php echo esc_html( $log->subject ); ?></td>
	</tr>
	<?php if ( ! empty( $log->transport ) ) : ?>
	<tr>
		<th scope="row"><?php esc_html_e( 'Transport', 'mxroute-mailer' ); ?></th>
		<td><?php echo esc_html( 'smtp' === $log->transport ? __( 'SMTP', 'mxroute-mailer' ) : __( 'MXRoute API', 'mxroute-mailer' ) ); ?></td>
	</tr>
	<?php endif; ?>
	</table>

	<?php
	$queue     = new MXRoute_Queue();
	$att_info  = $queue->get_attachment_info( $log->attachments ?? '[]' );
	if ( ! empty( $att_info ) ) :
		?>
		<h4><?php esc_html_e( 'Attachments', 'mxroute-mailer' ); ?></h4>
		<table class="widefat">
			<thead>
				<tr>
					<th scope="col" style="width:180px;"><?php esc_html_e( 'Type', 'mxroute-mailer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Original Path', 'mxroute-mailer' ); ?></th>
					<th scope="col" style="width:120px;"><?php esc_html_e( 'Stored', 'mxroute-mailer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $att_info as $att ) : ?>
					<tr>
						<td><?php echo esc_html( $att['type_label'] ); ?></td>
						<td><code><?php echo esc_html( $att['origin'] ); ?></code></td>
						<td>
							<?php if ( $att['stored_exists'] ) : ?>
								<span class="mxroute-status-badge mxroute-success"><?php esc_html_e( 'OK', 'mxroute-mailer' ); ?></span>
							<?php elseif ( '' !== $att['stored_path'] ) : ?>
								<span class="mxroute-status-badge mxroute-fail"><?php esc_html_e( 'Missing', 'mxroute-mailer' ); ?></span>
							<?php else : ?>
								<?php esc_html_e( 'N/A', 'mxroute-mailer' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h4><?php esc_html_e( 'Message', 'mxroute-mailer' ); ?></h4>
	<div class="mxroute-json"><?php echo esc_html( $log->message ?? '' ); ?></div>

	<h4><?php esc_html_e( 'Request', 'mxroute-mailer' ); ?></h4>
	<pre class="mxroute-json"><?php echo esc_html( wp_json_encode( $request, JSON_PRETTY_PRINT ) ); ?></pre>

	<h4><?php esc_html_e( 'Response', 'mxroute-mailer' ); ?></h4>
	<pre class="mxroute-json"><?php echo esc_html( wp_json_encode( $response, JSON_PRETTY_PRINT ) ); ?></pre>
</div>
