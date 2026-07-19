<?php
/**
 * MXRoute Mailer email logging.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles logging of emails sent through the MXRoute API.
 */
class MXRoute_Logger {

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
	 * Create the logging database table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'mxroute_mailer_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            from_email varchar(255) NOT NULL,
            reply_to varchar(255) NOT NULL DEFAULT '',
            to_email varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            message longtext,
            headers longtext NOT NULL,
            attachments longtext NOT NULL,
            api_request longtext,
            api_response longtext,
            success tinyint(2) NOT NULL DEFAULT 0,
            transport varchar(10) NOT NULL DEFAULT 'api',
            created_at datetime DEFAULT NULL,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY success (success),
            KEY from_email (from_email),
            KEY to_email (to_email),
            KEY created_at (created_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table creation on activation.
		dbDelta( $sql );
	}

	/**
	 * Log an email sent via the MXRoute API.
	 *
	 * @param string       $from        Sender email address.
	 * @param string|array $to          Recipient email address(es).
	 * @param string       $subject     Email subject.
	 * @param string       $body        Email message body.
	 * @param array        $request     API request data.
	 * @param array        $response    API response data.
	 * @param bool         $success     Whether the send was successful.
	 * @param string       $reply_to    Optional Reply-To email address.
	 * @param string       $headers     Optional email headers.
	 * @param array        $attachments Optional array of file paths.
	 * @param string       $transport   Transport method ('api' or 'smtp').
	 * @return void
	 */
	public function log( $from, $to, $subject, $body, $request, $response, $success, $reply_to = '', $headers = '', $attachments = array(), $transport = 'api' ) {
		if ( ! get_option( 'mxroute_mailer_logging_enabled', 1 ) ) {
			return;
		}

		global $wpdb;

		$to_address = '';
		if ( is_array( $to ) ) {
			$first      = reset( $to );
			$to_address = false !== $first ? $first : '';
		} else {
			$to_address = $to;
		}

		if ( preg_match( '/<(.+?)>/', $to_address, $matches ) ) {
			$to_address = $matches[1];
		}
		$to_address = sanitize_email( $to_address );

		$reply_to_address = sanitize_email( $reply_to );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Logging write, caching not applicable.
		$wpdb->insert(
			$this->table_name,
			array(
				'from_email'   => sanitize_email( $from ),
				'reply_to'     => $reply_to_address,
				'to_email'     => $to_address,
				'subject'      => sanitize_text_field( $subject ),
				'message'      => $body,
				'headers'      => is_array( $headers ) ? wp_json_encode( $headers ) : (string) $headers,
				'attachments'  => wp_json_encode( $attachments ),
				'api_request'  => wp_json_encode( $request ),
				'api_response' => wp_json_encode( $response ),
				'success'      => $success ? 1 : -1,
				'transport'    => in_array( $transport, array( 'api', 'smtp' ), true ) ? $transport : 'api',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Get paginated logs with optional filters.
	 *
	 * @param int   $per_page Results per page.
	 * @param int   $page     Current page number.
	 * @param array $filters  Optional filter criteria.
	 * @return array {
	 *     Log query results.
	 *
	 *     @type array $logs  Array of log objects.
	 *     @type int   $total Total number of matching logs.
	 *     @type int   $pages Total number of pages.
	 * }
	 */
	public function get_logs( $per_page = 20, $page = 1, $filters = array() ) {
		global $wpdb;

		$values = array();

		// Exclude pending queue entries unless explicitly filtering for them.
		if ( ! empty( $filters['success'] ) && '0' === (string) $filters['success'] ) {
			$where = 'success = 0';
		} else {
			$where = 'success != 0';
		}

		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where   .= ' AND (from_email LIKE %s OR to_email LIKE %s OR subject LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		if ( ! empty( $filters['success'] ) && '0' !== (string) $filters['success'] ) {
			$where   .= ' AND success = %d';
			$values[] = intval( $filters['success'] );
		}

		if ( ! empty( $filters['from_email'] ) ) {
			$where   .= ' AND from_email = %s';
			$values[] = sanitize_email( $filters['from_email'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where   .= ' AND timestamp >= %s';
			$values[] = sanitize_text_field( $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where   .= ' AND timestamp <= %s';
			$values[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		$offset = ( $page - 1 ) * $per_page;

		$query    = "SELECT * FROM {$this->table_name} WHERE $where ORDER BY timestamp DESC LIMIT %d OFFSET %d";
		$values[] = $per_page;
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Log query with dynamic WHERE, table name is safe.
		$query = $wpdb->prepare( $query, $values );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Already prepared above.
		$results = $wpdb->get_results( $query );

		$count_values = array_slice( $values, 0, -2 );
		$count_query  = "SELECT COUNT(*) FROM {$this->table_name} WHERE $where";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Count query with dynamic WHERE, table name is safe.
		$count_query = ! empty( $count_values )
			? $wpdb->prepare( $count_query, $count_values ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: $count_query;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Already prepared above.
		$total = (int) $wpdb->get_var( $count_query );

		return array(
			'logs'  => $results,
			'total' => $total,
			'pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
		);
	}

	/**
	 * Get a single log entry by ID.
	 *
	 * @param int $id Log entry ID.
	 * @return object|null Log object or null if not found.
	 */
	public function get_log( $id ) {
		global $wpdb;

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single row by primary key, caching not applicable.
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);
	}

	/**
	 * Clear all log entries.
	 *
	 * @return void
	 */
	public function clear_logs() {
		global $wpdb;

		// Delete stored attachment copies before removing rows.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fetching attachments for cleanup.
		$rows = $wpdb->get_col( "SELECT attachments FROM {$this->table_name} WHERE success != 0" );
		$queue = new MXRoute_Queue();
		foreach ( (array) $rows as $json ) {
			$queue->delete_stored_attachments( $json );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DELETE with no user input, TRUNCATE avoided to preserve pending queue entries.
		$wpdb->query( "DELETE FROM {$this->table_name} WHERE success != 0" );
	}

	/**
	 * Delete a single log entry by ID.
	 *
	 * @param int $id Log entry ID.
	 * @return void
	 */
	public function delete_log( $id ) {
		global $wpdb;

		// Fetch attachments before deleting to clean up stored copies.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fetch by primary key.
		$attachments_json = $wpdb->get_var(
			$wpdb->prepare( "SELECT attachments FROM {$this->table_name} WHERE id = %d", absint( $id ) )
		);
		if ( $attachments_json ) {
			$queue = new MXRoute_Queue();
			$queue->delete_stored_attachments( $attachments_json );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete by primary key, caching not applicable.
		$wpdb->delete( $this->table_name, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Delete multiple log entries by ID.
	 *
	 * @param array $ids Array of log entry IDs.
	 * @return void
	 */
	public function delete_logs( $ids ) {
		$ids = array_filter( array_map( 'intval', (array) $ids ) );
		foreach ( $ids as $id ) {
			$this->delete_log( $id );
		}
	}

	/**
	 * Re-queue a log entry by resetting it to pending status.
	 *
	 * @param int $id Log entry ID.
	 * @return bool True on success, false if row not found.
	 */
	public function requeue_log( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( $id < 1 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table_name} SET success = 0, api_request = '', api_response = '', processed_at = NULL WHERE id = %d",
				$id
			)
		);

		return true;
	}

	/**
	 * Re-queue multiple log entries by resetting them to pending status.
	 *
	 * @param array $ids Array of log entry IDs.
	 * @return int Number of entries re-queued.
	 */
	public function requeue_logs( $ids ) {
		$count = 0;
		foreach ( (array) $ids as $id ) {
			if ( $this->requeue_log( $id ) ) {
				++$count;
			}
		}
		return $count;
	}

}
