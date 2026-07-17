<?php
/**
 * MXRoute Mailer email queue.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages the email queue for background processing via WP-Cron.
 *
 * Queue entries are stored in the same table as email logs. A pending entry
 * is created with success = 0 and processed_at = NULL. When the queue
 * processor sends the email, it updates the row with the API result.
 */
class MXRoute_Queue {

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'mxroute_mailer_logs';
	}

	/**
	 * Add a pending email to the queue.
	 *
	 * @param string       $from        Sender email address.
	 * @param string       $to          Recipient email address.
	 * @param string       $subject     Email subject.
	 * @param string       $message     Email body.
	 * @param string|array $headers     Email headers.
	 * @param array        $attachments Array of file paths.
	 * @param string       $reply_to    Optional Reply-To email address.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function add( $from, $to, $subject, $message, $headers = '', $attachments = array(), $reply_to = '' ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue write.
		return $wpdb->insert(
			$this->table_name,
			array(
				'success'      => 0,
				'from_email'   => sanitize_email( $from ),
				'reply_to'     => sanitize_email( $reply_to ),
				'to_email'     => sanitize_email( $to ),
				'subject'      => sanitize_text_field( $subject ),
				'message'      => $message,
				'headers'      => is_array( $headers ) ? wp_json_encode( $headers ) : (string) $headers,
				'attachments'  => wp_json_encode( $attachments ),
				'api_request'  => '',
				'api_response' => '',
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get pending queue items.
	 *
	 * @param int $limit Maximum number of items to retrieve.
	 * @return array Array of queue row objects.
	 */
	public function get_pending( $limit = 50 ) {
		global $wpdb;

		$limit = absint( $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, dynamic limit.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE success = 0 AND processed_at IS NULL ORDER BY created_at ASC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Mark a queue item as sent with API result data.
	 *
	 * @param int   $id       Queue item ID.
	 * @param array $request  API request data sent.
	 * @param array $response API response data.
	 * @return bool True on success, false on failure.
	 */
	public function mark_sent( $id, $request = array(), $response = array() ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue update by primary key.
		return (bool) $wpdb->update(
			$this->table_name,
			array(
				'success'      => 1,
				'api_request'  => wp_json_encode( $request ),
				'api_response' => wp_json_encode( $response ),
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark a queue item as failed with API result data.
	 *
	 * @param int   $id       Queue item ID.
	 * @param array $request  API request data sent.
	 * @param array $response API response data.
	 * @return bool True on success, false on failure.
	 */
	public function mark_failed( $id, $request = array(), $response = array() ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue update by primary key.
		return (bool) $wpdb->update(
			$this->table_name,
			array(
				'success'      => -1,
				'api_request'  => wp_json_encode( $request ),
				'api_response' => wp_json_encode( $response ),
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get the number of pending items in the queue.
	 *
	 * @return int Number of pending items.
	 */
	public function count_pending() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE success = 0 AND processed_at IS NULL"
		);
	}

	/**
	 * Delete queue items older than a given number of days.
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of rows deleted.
	 */
	public function cleanup( $days = 30 ) {
		global $wpdb;

		$date = gmdate( 'Y-m-d H:i:s', strtotime( '-' . absint( $days ) . ' days', current_time( 'timestamp' ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE success IN (1, -1) AND processed_at < %s",
				$date
			)
		);
	}
}
