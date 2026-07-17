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
		add_action( 'admin_ajax_mxroute_requeue_log', array( $this, 'ajax_requeue_log' ) );
		add_action( 'admin_ajax_mxroute_bulk_requeue_logs', array( $this, 'ajax_bulk_requeue_logs' ) );
		add_action( 'admin_ajax_mxroute_add_to_queue', array( $this, 'ajax_add_to_queue' ) );
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
		$singular = _n( '%d log deleted.', '%d logs deleted.', $count, 'mxroute-mailer' );
		$message  = sprintf( $singular, $count );

		wp_send_json_success(
			array(
				'message' => $message,
			)
		);
	}

	/**
	 * AJAX handler to re-queue a single log entry.
	 *
	 * Resets the entry to pending status so the queue processor sends it again.
	 *
	 * @return void
	 */
	public function ajax_requeue_log() {
		check_ajax_referer( 'mxroute_log_manage', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'mxroute-mailer' ) ) );
			return;
		}

		$log_id = intval( $_POST['log_id'] ?? 0 );
		if ( $log_id < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid log ID.', 'mxroute-mailer' ) ) );
			return;
		}

		$logger = new MXRoute_Logger();
		$log    = $logger->get_log( $log_id );
		if ( ! $log ) {
			wp_send_json_error( array( 'message' => __( 'Log entry not found.', 'mxroute-mailer' ) ) );
			return;
		}

		$logger->requeue_log( $log_id );

		wp_send_json_success( array( 'message' => __( 'Email re-queued.', 'mxroute-mailer' ) ) );
	}

	/**
	 * AJAX handler to re-queue multiple selected logs.
	 *
	 * @return void
	 */
	public function ajax_bulk_requeue_logs() {
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
		$logger->requeue_logs( $ids );

		$count = count( $ids );
		// translators: %d: number of logs re-queued.
		$singular = _n( '%d email re-queued.', '%d emails re-queued.', $count, 'mxroute-mailer' );
		$message  = sprintf( $singular, $count );

		wp_send_json_success(
			array(
				'message' => $message,
			)
		);
	}

	/**
	 * AJAX handler to add an email directly to the queue.
	 *
	 * @return void
	 */
	public function ajax_add_to_queue() {
		check_ajax_referer( 'mxroute_log_manage', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'mxroute-mailer' ) ) );
			return;
		}

		$from_email = sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) );
		$to_email   = sanitize_email( wp_unslash( $_POST['to_email'] ?? '' ) );
		$subject    = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$message    = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

		if ( empty( $from_email ) || empty( $to_email ) || empty( $subject ) || empty( $message ) ) {
			wp_send_json_error( array( 'message' => __( 'All fields are required.', 'mxroute-mailer' ) ) );
			return;
		}

		$queue = new MXRoute_Queue();
		$queue->add( $from_email, $to_email, $subject, $message, '', array(), '' );

		if ( ! wp_next_scheduled( 'mxroute_mailer_process_queue' ) ) {
			wp_schedule_single_event( time(), 'mxroute_mailer_process_queue' );
		}

		wp_send_json_success( array( 'message' => __( 'Email added to queue.', 'mxroute-mailer' ) ) );
	}
}

new MXRoute_Dashboard();
