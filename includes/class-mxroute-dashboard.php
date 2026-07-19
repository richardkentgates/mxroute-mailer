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
		add_action( 'admin_ajax_mxroute_check_queue', array( $this, 'ajax_check_queue' ) );
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
		if ( $log_id < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid log ID.', 'mxroute-mailer' ) ) );
			return;
		}

		$logger = new MXRoute_Logger();
		$log    = $logger->get_log( $log_id );
		if ( ! $log ) {
			wp_send_json_error( array( 'message' => __( 'Log not found.', 'mxroute-mailer' ) ) );
			return;
		}

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
		$ids = array_filter( $ids, function ( $id ) {
			return $id > 0;
		} );

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
	 * AJAX handler to check queue status.
	 *
	 * Returns which of the provided IDs are no longer pending,
	 * so the queue page can remove processed rows dynamically.
	 *
	 * @return void
	 */
	public function ajax_check_queue() {
		check_ajax_referer( 'mxroute_log_manage', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'mxroute-mailer' ) ) );
			return;
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
		$ids = array_filter( $ids, function ( $id ) {
			return $id > 0;
		} );

		if ( empty( $ids ) ) {
			wp_send_json_success( array( 'processed' => array() ) );
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'mxroute_mailer_logs';
		$format     = array_fill( 0, count( $ids ), '%d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Queue status check by primary keys.
		$pending = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE id IN (" . implode( ',', $format ) . ') AND success = 0 AND processed_at IS NULL',
				$ids
			)
		);

		$pending_ids = array_map( 'intval', $pending );
		$processed   = array_diff( $ids, $pending_ids );

		wp_send_json_success(
			array(
				'processed' => array_values( $processed ),
			)
		);
	}

}

new MXRoute_Dashboard();
