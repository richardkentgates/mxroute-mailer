<?php
/**
 * MXRoute Mailer single log view.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

$log_id = isset( $_GET['id'] ) ? intval( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'mxroute_log_view_' . $log_id ) ) {
	wp_die( esc_html__( 'Security check failed.', 'mxroute-mailer' ), '', array( 'response' => 403 ) );
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
			<span class="mxroute-status-badge <?php echo esc_attr( $log->success > 0 ? 'mxroute-success' : ( $log->success < 0 ? 'mxroute-fail' : 'mxroute-pending' ) ); ?>" role="status">
				<?php
				if ( $log->success > 0 ) {
					echo esc_html__( 'Sent', 'mxroute-mailer' );
				} elseif ( $log->success < 0 ) {
					echo esc_html__( 'Failed', 'mxroute-mailer' );
				} else {
					echo esc_html__( 'Pending', 'mxroute-mailer' );
				}
				?>
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

	<?php
	$history = json_decode( isset( $log->history ) && is_string( $log->history ) ? $log->history : '', true );
	$history = is_array( $history ) ? $history : array();

	if ( ! empty( $history ) ) :
		$attempt_number = 0;
		?>
		<h4><?php esc_html_e( 'History', 'mxroute-mailer' ); ?></h4>
		<table class="widefat">
			<thead>
				<tr>
					<th scope="col" style="width:170px;"><?php esc_html_e( 'Time', 'mxroute-mailer' ); ?></th>
					<th scope="col" style="width:150px;"><?php esc_html_e( 'Event', 'mxroute-mailer' ); ?></th>
					<th scope="col" style="width:110px;"><?php esc_html_e( 'Result', 'mxroute-mailer' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Transport', 'mxroute-mailer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $history as $event ) : ?>
					<?php
					$type   = isset( $event['event'] ) ? (string) $event['event'] : '';
					$at     = isset( $event['at'] ) ? (string) $event['at'] : '';
					$result = '&mdash;';
					$label  = $type;
					?>
					<tr>
						<td><?php echo esc_html( $at ); ?></td>
						<td>
							<?php
							if ( 'queued' === $type ) {
								esc_html_e( 'Queued', 'mxroute-mailer' );
							} elseif ( 'attempt' === $type ) {
								++$attempt_number;
								// translators: %d: attempt number.
								echo esc_html( sprintf( __( 'Attempt #%d', 'mxroute-mailer' ), $attempt_number ) );
								if ( ! empty( $event['by'] ) ) {
									echo ' ';
									// translators: %s: actor login.
									echo esc_html( sprintf( __( 'by %s', 'mxroute-mailer' ), $event['by'] ) );
								}
							} elseif ( 'requeue' === $type ) {
								esc_html_e( 'Re-queued', 'mxroute-mailer' );
								if ( ! empty( $event['by'] ) ) {
									echo ' ';
									// translators: %s: actor login.
									echo esc_html( sprintf( __( 'by %s', 'mxroute-mailer' ), $event['by'] ) );
								}
							} else {
								echo esc_html( $label );
							}
							?>
						</td>
						<td>
							<?php if ( 'attempt' === $type ) : ?>
								<span class="mxroute-status-badge <?php echo esc_attr( ! empty( $event['success'] ) ? 'mxroute-success' : 'mxroute-fail' ); ?>" role="status">
									<?php echo ! empty( $event['success'] ) ? esc_html__( 'Sent', 'mxroute-mailer' ) : esc_html__( 'Failed', 'mxroute-mailer' ); ?>
								</span>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
						<td>
							<?php echo 'attempt' === $type && ! empty( $event['transport'] ) ? esc_html( $event['transport'] ) : '&mdash;'; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		$attempt_number = 0;
		foreach ( $history as $event ) :
			if ( 'attempt' !== ( $event['event'] ?? '' ) ) {
				continue;
			}
			++$attempt_number;
			$attempt_request  = isset( $event['request'] ) && is_array( $event['request'] ) ? $event['request'] : array();
			$attempt_response = isset( $event['response'] ) && is_array( $event['response'] ) ? $event['response'] : array();
			?>
			<h5>
				<?php
				// translators: %1$d: attempt number, %2$s: timestamp.
				echo esc_html( sprintf( __( 'Attempt #%1$d — %2$s', 'mxroute-mailer' ), $attempt_number, isset( $event['at'] ) ? $event['at'] : '' ) );
				?>
			</h5>
			<?php if ( ! empty( $attempt_request ) ) : ?>
				<p class="description"><?php esc_html_e( 'Request', 'mxroute-mailer' ); ?></p>
				<pre class="mxroute-json"><?php echo esc_html( wp_json_encode( $attempt_request, JSON_PRETTY_PRINT ) ); ?></pre>
			<?php endif; ?>
			<?php if ( ! empty( $attempt_response ) ) : ?>
				<p class="description"><?php esc_html_e( 'Response', 'mxroute-mailer' ); ?></p>
				<pre class="mxroute-json"><?php echo esc_html( wp_json_encode( $attempt_response, JSON_PRETTY_PRINT ) ); ?></pre>
			<?php endif; ?>
			<?php
		endforeach;
		?>
	<?php endif; ?>
</div>
