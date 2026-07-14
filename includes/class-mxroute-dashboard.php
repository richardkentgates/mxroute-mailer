<?php
/**
 * MXRoute Mailer dashboard widget.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds a dashboard widget and handles AJAX log operations.
 */
class MXRoute_Dashboard {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_action( 'admin_ajax_mxroute_log_detail', array( $this, 'ajax_log_detail' ) );
		add_action( 'admin_ajax_mxroute_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'admin_ajax_mxroute_delete_log', array( $this, 'ajax_delete_log' ) );
	}

	/**
	 * Register the dashboard widget.
	 *
	 * @return void
	 */
	public function add_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'mxroute_mailer_widget',
			__( 'MXRoute Mailer - Recent Emails', 'mxroute-mailer' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the dashboard widget content.
	 *
	 * @return void
	 */
	public function render_widget() {
		$logger = new MXRoute_Logger();
		$logs   = $logger->get_recent_logs( 10 );

		if ( empty( $logs ) ) {
			echo '<p class="mxroute-no-logs">' . esc_html__( 'No emails sent yet.', 'mxroute-mailer' ) . '</p>';
			return;
		}

		echo '<table class="widefat mxroute-widget-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Status', 'mxroute-mailer' ) . '</th>';
		echo '<th>' . esc_html__( 'Time', 'mxroute-mailer' ) . '</th>';
		echo '<th>' . esc_html__( 'From', 'mxroute-mailer' ) . '</th>';
		echo '<th>' . esc_html__( 'To', 'mxroute-mailer' ) . '</th>';
		echo '<th>' . esc_html__( 'Subject', 'mxroute-mailer' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $logs as $log ) {
			$status_class = $log->success ? 'mxroute-success' : 'mxroute-fail';
			$status_text  = $log->success ? __( 'Sent', 'mxroute-mailer' ) : __( 'Failed', 'mxroute-mailer' );
			$time         = human_time_diff( strtotime( $log->timestamp ), time() ) . ' ago';

			echo '<tr class="mxroute-log-row" data-log-id="' . esc_attr( $log->id ) . '">';
			echo '<td><span class="mxroute-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_text ) . '</span></td>';
			echo '<td>' . esc_html( $time ) . '</td>';
			echo '<td>' . esc_html( $log->from_email ) . '</td>';
			echo '<td>' . esc_html( $log->to_email ) . '</td>';
			echo '<td>' . esc_html( wp_trim_words( $log->subject, 6 ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		echo '<p class="mxroute-widget-footer"><a href="' . esc_url( admin_url( 'tools.php?page=mxroute-logs' ) ) . '">' . esc_html__( 'View all logs', 'mxroute-mailer' ) . '</a></p>';

		echo '<div id="mxroute-log-modal" class="mxroute-modal" style="display:none;">';
		echo '<div class="mxroute-modal-content">';
		echo '<span class="mxroute-modal-close">&times;</span>';
		echo '<h3>' . esc_html__( 'Log Details', 'mxroute-mailer' ) . '</h3>';
		echo '<div id="mxroute-modal-body"></div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * AJAX handler for log detail view.
	 *
	 * @return void
	 */
	public function ajax_log_detail() {
		check_ajax_referer( 'mxroute_log_view', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'mxroute-mailer' ) ) );
			return;
		}

		$id     = intval( $_POST['log_id'] ?? 0 );
		$logger = new MXRoute_Logger();
		$log    = $logger->get_log( $id );

		if ( ! $log ) {
			wp_send_json_error( array( 'message' => __( 'Log not found.', 'mxroute-mailer' ) ) );
			return;
		}

		$request  = json_decode( $log->api_request, true );
		$response = json_decode( $log->api_response, true );

		ob_start();
		?>
		<table class="widefat">
			<tr><th><?php esc_html_e( 'Timestamp', 'mxroute-mailer' ); ?></th><td><?php echo esc_html( $log->timestamp ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Status', 'mxroute-mailer' ); ?></th><td><span class="mxroute-status-badge <?php echo esc_attr( $log->success ? 'mxroute-success' : 'mxroute-fail' ); ?>"><?php echo esc_html( $log->success ? __( 'Success', 'mxroute-mailer' ) : __( 'Failed', 'mxroute-mailer' ) ); ?></span></td></tr>
			<tr><th><?php esc_html_e( 'From', 'mxroute-mailer' ); ?></th><td><?php echo esc_html( $log->from_email ); ?></td></tr>
			<tr><th><?php esc_html_e( 'To', 'mxroute-mailer' ); ?></th><td><?php echo esc_html( $log->to_email ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Subject', 'mxroute-mailer' ); ?></th><td><?php echo esc_html( $log->subject ); ?></td></tr>
		</table>

		<h4><?php esc_html_e( 'API Request', 'mxroute-mailer' ); ?></h4>
		<pre class="mxroute-json"><?php echo esc_html( wp_json_encode( $request, JSON_PRETTY_PRINT ) ); ?></pre>

		<h4><?php esc_html_e( 'API Response', 'mxroute-mailer' ); ?></h4>
		<pre class="mxroute-json"><?php echo esc_html( wp_json_encode( $response, JSON_PRETTY_PRINT ) ); ?></pre>
		<?php
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * AJAX handler to clear all logs.
	 *
	 * @return void
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'mxroute_log_manage', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'mxroute-mailer' ) ) );
			return;
		}

		$logger = new MXRoute_Logger();
		$logger->clear_logs();

		wp_send_json_success( array( 'message' => __( 'All logs cleared.', 'mxroute-mailer' ) ) );
	}

	/**
	 * AJAX handler to delete a single log.
	 *
	 * @return void
	 */
	public function ajax_delete_log() {
		check_ajax_referer( 'mxroute_log_manage', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'mxroute-mailer' ) ) );
			return;
		}

		$id     = intval( $_POST['log_id'] ?? 0 );
		$logger = new MXRoute_Logger();
		$logger->delete_log( $id );

		wp_send_json_success( array( 'message' => __( 'Log deleted.', 'mxroute-mailer' ) ) );
	}
}

new MXRoute_Dashboard();
