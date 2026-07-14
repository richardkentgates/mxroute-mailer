<?php
/**
 * MXRoute Mailer AJAX log operations.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles AJAX log operations for the logs page.
 */
class MXRoute_Dashboard {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_ajax_mxroute_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'admin_ajax_mxroute_delete_log', array( $this, 'ajax_delete_log' ) );
		add_action( 'admin_ajax_mxroute_bulk_delete_logs', array( $this, 'ajax_bulk_delete_logs' ) );
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

		$log_id = intval( $_POST['log_id'] ?? 0 );
		$logger = new MXRoute_Logger();
		$logger->delete_log( $log_id );

		wp_send_json_success( array( 'message' => __( 'Log deleted.', 'mxroute-mailer' ) ) );
	}

	/**
	 * AJAX handler to delete multiple selected logs.
	 *
	 * @return void
	 */
	public function ajax_bulk_delete_logs() {
		check_ajax_referer( 'mxroute_log_manage', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'mxroute-mailer' ) ) );
			return;
		}

		$ids = isset( $_POST['log_ids'] ) ? array_map( 'intval', (array) $_POST['log_ids'] ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No logs selected.', 'mxroute-mailer' ) ) );
			return;
		}

		$logger = new MXRoute_Logger();
		$logger->delete_logs( $ids );

		$count = count( $ids );
		// translators: %d: number of logs deleted.
		$message = sprintf( _n( '%d log deleted.', '%d logs deleted.', $count, 'mxroute-mailer' ), $count );

		wp_send_json_success(
			array(
				'message' => $message,
			)
		);
	}
}

new MXRoute_Dashboard();
