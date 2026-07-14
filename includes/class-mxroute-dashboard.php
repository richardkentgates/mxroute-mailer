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
