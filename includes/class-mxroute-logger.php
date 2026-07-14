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
            to_email varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            api_request longtext,
            api_response longtext,
            success tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY success (success),
            KEY from_email (from_email),
            KEY to_email (to_email)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table creation on activation.
		dbDelta( $sql );
	}

	/**
	 * Drop the logging database table.
	 *
	 * @return void
	 */
	public static function drop_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mxroute_mailer_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table drop, cannot use prepare.
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}

	/**
	 * Log an email sent via the MXRoute API.
	 *
	 * @param string       $from     Sender email address.
	 * @param string|array $to       Recipient email address(es).
	 * @param string       $subject  Email subject.
	 * @param array        $request  API request data.
	 * @param array        $response API response data.
	 * @param bool         $success  Whether the send was successful.
	 * @return void
	 */
	public function log( $from, $to, $subject, $request, $response, $success ) {
		if ( ! get_option( 'mxroute_mailer_logging_enabled', 1 ) ) {
			return;
		}

		global $wpdb;

		$to_address = '';
		if ( is_array( $to ) ) {
			$first      = reset( $to );
			$to_address = false !== $first ? sanitize_email( $first ) : '';
		} else {
			$to_address = sanitize_email( $to );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Logging write, caching not applicable.
		$wpdb->insert(
			$this->table_name,
			array(
				'from_email'   => sanitize_email( $from ),
				'to_email'     => $to_address,
				'subject'      => sanitize_text_field( $subject ),
				'api_request'  => wp_json_encode( $request ),
				'api_response' => wp_json_encode( $response ),
				'success'      => $success ? 1 : 0,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d' )
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

		$where  = '1=1';
		$values = array();

		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where   .= ' AND (from_email LIKE %s OR to_email LIKE %s OR subject LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		if ( ! empty( $filters['success'] ) ) {
			$where   .= ' AND success = %d';
			$values[] = ( '1' === $filters['success'] ) ? 1 : 0;
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Count query with dynamic WHERE, table name is safe.
		$count_query = $wpdb->prepare( $count_query, $count_values );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Already prepared above.
		$total = (int) $wpdb->get_var( $count_query );

		return array(
			'logs'  => $results,
			'total' => $total,
			'pages' => ceil( $total / $per_page ),
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- TRUNCATE cannot use prepare.
		$wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
	}

	/**
	 * Delete a single log entry by ID.
	 *
	 * @param int $id Log entry ID.
	 * @return void
	 */
	public function delete_log( $id ) {
		global $wpdb;
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
		global $wpdb;
		$ids = array_map( 'intval', $ids );
		$ids = array_filter( $ids );
		if ( empty( $ids ) ) {
			return;
		}
		$placeholders = array_fill( 0, count( $ids ), '%d' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete by primary keys, caching not applicable.
		$wpdb->delete(
			$this->table_name,
			array( 'id' => $ids ),
			array( $placeholders )
		);
	}

	/**
	 * Get the most recent log entries.
	 *
	 * @param int $count Number of logs to retrieve.
	 * @return array Array of log objects.
	 */
	public function get_recent_logs( $count = 10 ) {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Recent logs query, caching not applicable.
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} ORDER BY timestamp DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$count
			)
		);
	}
}
