<?php
/**
 * MXRoute Mailer single log view.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

$id     = isset( $_GET['id'] ) ? intval( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$logger = new MXRoute_Logger();
$log    = $logger->get_log( $id );

if ( ! $log ) {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Log Entry Not Found', 'mxroute-mailer' ); ?></h1>
		<p>
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=mxroute-logs' ) ); ?>">
				<?php esc_html_e( '&laquo; Back to Logs', 'mxroute-mailer' ); ?>
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
			<?php esc_html_e( '&laquo; Back to Logs', 'mxroute-mailer' ); ?>
		</a>
	</p>

	<table class="widefat">
		<tr>
			<th style="width:150px;"><?php esc_html_e( 'Timestamp', 'mxroute-mailer' ); ?></th>
			<td><?php echo esc_html( $log->timestamp ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Status', 'mxroute-mailer' ); ?></th>
			<td>
				<span class="mxroute-status-badge <?php echo esc_attr( $log->success ? 'mxroute-success' : 'mxroute-fail' ); ?>">
					<?php echo esc_html( $log->success ? __( 'Success', 'mxroute-mailer' ) : __( 'Failed', 'mxroute-mailer' ) ); ?>
				</span>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'From', 'mxroute-mailer' ); ?></th>
			<td><?php echo esc_html( $log->from_email ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'To', 'mxroute-mailer' ); ?></th>
			<td><?php echo esc_html( $log->to_email ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Subject', 'mxroute-mailer' ); ?></th>
			<td><?php echo esc_html( $log->subject ); ?></td>
		</tr>
	</table>

	<h4><?php esc_html_e( 'API Request', 'mxroute-mailer' ); ?></h4>
	<pre class="mxroute-json"><?php echo esc_html( wp_json_encode( $request, JSON_PRETTY_PRINT ) ); ?></pre>

	<h4><?php esc_html_e( 'API Response', 'mxroute-mailer' ); ?></h4>
	<pre class="mxroute-json"><?php echo esc_html( wp_json_encode( $response, JSON_PRETTY_PRINT ) ); ?></pre>
</div>
