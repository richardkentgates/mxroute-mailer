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

		// Insert the queue row first to get the log ID for attachment storage.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue write.
		$inserted = $wpdb->insert(
			$this->table_name,
			array(
				'success'      => 0,
				'from_email'   => sanitize_email( $from ),
				'reply_to'     => sanitize_email( $reply_to ),
				'to_email'     => sanitize_email( $to ),
				'subject'      => sanitize_text_field( $subject ),
				'message'      => $message,
				'headers'      => is_array( $headers ) ? wp_json_encode( $headers ) : (string) $headers,
				'attachments'  => '[]',
				'api_request'  => '',
				'api_response' => '',
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		$log_id = $wpdb->insert_id;

		// Store attachment files and build typed references.
		$stored_attachments = $this->prepare_attachments_for_storage( $log_id, $attachments );
		$attachments_json   = wp_json_encode( $stored_attachments );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue update by primary key.
		$wpdb->update(
			$this->table_name,
			array( 'attachments' => $attachments_json ),
			array( 'id' => $log_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $log_id;
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
	 * Atomically claim pending items for processing.
	 *
	 * Uses UPDATE to set processed_at on a batch of rows, then selects them.
	 * Prevents duplicate processing when concurrent cron runs overlap.
	 *
	 * @param int $limit Maximum items to claim.
	 * @return array Claimed queue items.
	 */
	public function claim_pending( $limit = 5 ) {
		global $wpdb;

		$limit = absint( $limit );
		$claim_time = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, dynamic limit.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table_name} SET processed_at = %s WHERE success = 0 AND processed_at IS NULL ORDER BY created_at ASC LIMIT %d",
				$claim_time,
				$limit
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE processed_at = %s ORDER BY created_at ASC LIMIT %d",
				$claim_time,
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
	 * Get paginated pending queue items.
	 *
	 * @param int $per_page Results per page.
	 * @param int $page     Current page number.
	 * @return array Pending queue items for the given page.
	 */
	public function get_pending_paginated( $per_page = 20, $page = 1 ) {
		global $wpdb;

		$per_page = absint( $per_page );
		$page     = max( 1, absint( $page ) );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe, dynamic limit/offset.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE success = 0 AND processed_at IS NULL ORDER BY created_at ASC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
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
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT attachments FROM {$this->table_name} WHERE success IN (1, -1) AND processed_at < %s",
				$date
			)
		);

		foreach ( (array) $rows as $json ) {
			$this->delete_stored_attachments( $json );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE success IN (1, -1) AND processed_at < %s",
				$date
			)
		);
	}

	/**
	 * Get the base directory for persistent attachment storage.
	 *
	 * @return string Full directory path.
	 */
	public function get_storage_dir() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'mxroute-mailer-attachments';
	}

	/**
	 * Ensure the storage directory exists and is secure.
	 *
	 * Creates the directory if missing. Adds an index.php to prevent
	 * directory listing if the web server does not block it.
	 *
	 * @return bool True if directory exists or was created, false on failure.
	 */
	public function ensure_storage_dir() {
		$dir = $this->get_storage_dir();

		if ( ! is_dir( $dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.mkdir_filesystem_mkdir -- WordPress filesystem not available at queue time.
			if ( ! @mkdir( $dir, 0750, true ) ) {
				return false;
			}
		}

		// Prevent directory listing.
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Static security file.
			@file_put_contents( $index, '<?php // Silence is golden.' );
		}

		return true;
	}

	/**
	 * Copy a file to persistent attachment storage.
	 *
	 * @param int    $log_id    The log/queue row ID.
	 * @param string $file_path Absolute path to the source file.
	 * @return string|false Stored file path on success, false on failure.
	 */
	public function store_attachment( $log_id, $file_path ) {
		if ( ! is_string( $file_path ) || '' === $file_path ) {
			return false;
		}

		// Resolve WordPress attachment IDs that may have been passed as strings.
		if ( ctype_digit( $file_path ) ) {
			$resolved = get_attached_file( absint( $file_path ) );
			if ( $resolved && '' !== $resolved ) {
				$file_path = $resolved;
			}
		}

		if ( ! is_readable( $file_path ) ) {
			return false;
		}

		// Enforce 5 MB limit.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading file size.
		$file_size = @filesize( $file_path );
		if ( false === $file_size || $file_size > 5 * MB_IN_BYTES ) {
			return false;
		}

		if ( ! $this->ensure_storage_dir() ) {
			return false;
		}

		$original_name = wp_basename( $file_path );
		$extension     = pathinfo( $original_name, PATHINFO_EXTENSION );
		$hash          = md5( $log_id . $original_name . filemtime( $file_path ) );
		$stored_name   = $hash . ( $extension ? '.' . $extension : '' );
		$stored_path   = $this->get_storage_dir() . '/' . $stored_name;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Copying to persistent storage for queue reliability.
		if ( ! @copy( $file_path, $stored_path ) ) {
			return false;
		}

		// Verify the copy succeeded and matches original size.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Verifying copy integrity.
		$copy_size = @filesize( $stored_path );
		if ( false === $copy_size || $copy_size !== $file_size ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_file_system_operations -- Cleanup failed copy.
			@unlink( $stored_path );
			return false;
		}

		return $stored_path;
	}

	/**
	 * Prepare attachment references for database storage.
	 *
	 * Resolves WordPress attachment IDs to persistent storage and copies
	 * file paths to persistent storage. Returns an array of typed references
	 * that survive across re-queue attempts.
	 *
	 * @param int   $log_id      The log/queue row ID.
	 * @param array $attachments Raw attachments from wp_mail (IDs and paths).
	 * @return array Array of attachment reference objects.
	 */
	public function prepare_attachments_for_storage( $log_id, $attachments ) {
		$stored   = array();
		$temp_dir = @sys_get_temp_dir();

		foreach ( (array) $attachments as $attachment ) {
			// Media library IDs: record the ID only, re-resolve at send time.
			// No copy needed — media library files are persistent.
			if ( is_int( $attachment ) || ( is_string( $attachment ) && ctype_digit( $attachment ) ) ) {
				$attachment_id = absint( $attachment );
				$file_path     = get_attached_file( $attachment_id );
				if ( $file_path && is_readable( $file_path ) ) {
					$stored[] = array(
						'type' => 'id',
						'id'   => $attachment_id,
					);
				}
				continue;
			}

			// File paths: only copy if in a temp directory. Otherwise record as-is.
			if ( is_string( $attachment ) && '' !== $attachment ) {
				$is_temp = ( $temp_dir && 0 === strpos( $attachment, $temp_dir ) );

				if ( $is_temp ) {
					$stored_path = $this->store_attachment( $log_id, $attachment );
					if ( false !== $stored_path ) {
						$stored[] = array(
							'type'   => 'stored',
							'path'   => $stored_path,
							'origin' => $attachment,
						);
					}
				} else {
					// Persistent path — just record it, no copy.
					if ( is_readable( $attachment ) ) {
						$stored[] = array(
							'type'   => 'path',
							'path'   => $attachment,
							'origin' => $attachment,
						);
					}
				}
			}
		}

		return $stored;
	}

	/**
	 * Resolve attachment references to actual file paths for sending.
	 *
	 * @param string $attachments_json JSON-encoded attachment metadata from the queue row.
	 * @return array Resolved array of file paths ready for PHPMailer/API.
	 */
	public function resolve_attachments( $attachments_json ) {
		$metadata = json_decode( $attachments_json, true );
		if ( ! is_array( $metadata ) ) {
			return array();
		}

		$resolved = array();

		foreach ( $metadata as $ref ) {
			// Legacy format: plain string path.
			if ( is_string( $ref ) ) {
				if ( '' !== $ref && is_readable( $ref ) ) {
					$resolved[] = $ref;
				}
				continue;
			}

			if ( ! is_array( $ref ) || empty( $ref['type'] ) ) {
				continue;
			}

			$type   = $ref['type'];
			$path   = $ref['path'] ?? '';
			$origin = $ref['origin'] ?? $path;

			if ( 'id' === $type && ! empty( $ref['id'] ) ) {
				// Media library ID: re-resolve from WordPress.
				$file_path = get_attached_file( absint( $ref['id'] ) );
				if ( $file_path && is_readable( $file_path ) ) {
					$resolved[] = $file_path;
				}
			} elseif ( '' !== $path && is_readable( $path ) ) {
				// type: 'stored' or type: 'path' — use the stored/recorded path.
				$resolved[] = $path;
			} elseif ( '' !== $origin && is_readable( $origin ) ) {
				// Fallback to original path.
				$resolved[] = $origin;
			}
		}

		return $resolved;
	}

	/**
	 * Delete stored attachment copies after successful send.
	 *
	 * @param string $attachments_json JSON-encoded attachment metadata from the queue row.
	 * @return void
	 */
	public function delete_stored_attachments( $attachments_json ) {
		$metadata = json_decode( $attachments_json, true );
		if ( ! is_array( $metadata ) ) {
			return;
		}

		foreach ( $metadata as $ref ) {
			// Only delete files we actually copied (type: 'stored').
			// type: 'id' and type: 'path' are references to originals — do not delete.
			if ( is_array( $ref ) && 'stored' === ( $ref['type'] ?? '' ) && ! empty( $ref['path'] ) ) {
				if ( is_readable( $ref['path'] ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_file_system_operations -- Cleaning up sent attachment copy.
					@unlink( $ref['path'] );
				}
			}
		}
	}

	/**
	 * Get human-readable attachment information for display.
	 *
	 * Decodes the attachments JSON and returns an array of display-ready
	 * info for each attachment: type label, original path, stored status.
	 *
	 * @param string $attachments_json JSON-encoded attachment metadata.
	 * @return array Array of attachment info arrays.
	 */
	public function get_attachment_info( $attachments_json ) {
		$metadata = json_decode( $attachments_json, true );
		if ( ! is_array( $metadata ) ) {
			return array();
		}

		$info = array();

		foreach ( $metadata as $ref ) {
			// Legacy format: plain string path.
			if ( is_string( $ref ) ) {
				$info[] = array(
					'type_label'    => __( 'File', 'mxroute-mailer' ),
					'type'          => 'file',
					'origin'        => $ref,
					'stored_path'   => '',
					'stored_exists' => false,
				);
				continue;
			}

			if ( ! is_array( $ref ) || empty( $ref['type'] ) ) {
				continue;
			}

			$origin    = $ref['origin'] ?? '';
			$stored    = $ref['path'] ?? '';
			$exists    = ( '' !== $stored && is_readable( $stored ) );

			if ( 'id' === $ref['type'] ) {
				$label = sprintf(
					/* translators: %d: attachment ID */
					__( 'Media ID %d', 'mxroute-mailer' ),
					$ref['id']
				);
				// Resolve original path from media library for display.
				if ( '' === $origin ) {
					$resolved = get_attached_file( absint( $ref['id'] ) );
					$origin   = $resolved ? $resolved : sprintf( __( '[ID %d not found]', 'mxroute-mailer' ), $ref['id'] );
				}
			} elseif ( 'stored' === $ref['type'] ) {
				$label = __( 'Temp file (stored)', 'mxroute-mailer' );
			} else {
				$label = __( 'File', 'mxroute-mailer' );
			}

			$info[] = array(
				'type_label'    => $label,
				'type'          => $ref['type'],
				'origin'        => $origin,
				'stored_path'   => $stored,
				'stored_exists' => $exists,
			);
		}

		return $info;
	}
}
