<?php
/**
 * MXRoute Mailer WP-CLI commands.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage MXRoute Mailer settings, logs, queue, and emails via WP-CLI.
 *
 * ## EXAMPLES
 *
 *     wp mxroute settings get server
 *     wp mxroute settings set password mypassword
 *     wp mxroute logs list --status=1
 *     wp mxroute queue count
 *     wp mxroute send user@example.com "Test Subject" "Test body"
 *     wp mxroute test user@example.com
 *
 * @since 1.4.0
 */
class MXRoute_CLI_Commands extends WP_CLI_Command {

	/**
	 * Get or set MXRoute Mailer configuration.
	 *
	 * ## OPTIONS
	 *
	 * [<action>]
	 * : The action to perform: get or set.
	 *
	 * [<key>]
	 * : The setting key: server, username, password, logging_enabled, keep_data, batch_size.
	 *
	 * [<value>]
	 * : The value to set (required for set action).
	 *
	 * ## EXAMPLES
	 *
	 *     wp mxroute config get server
	 *     wp mxroute config set batch_size 10
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function config( $args, $assoc_args ) {
		$action = $args[0] ?? '';
		$key    = $args[1] ?? '';
		$value  = $args[2] ?? '';

		// Default to 'get' with no key (show all settings).
		if ( '' === $action ) {
			$action = 'get';
		}

		$valid_keys = array(
			'server',
			'username',
			'password',
			'logging_enabled',
			'keep_data',
			'batch_size',
		);

		if ( 'get' === $action ) {
			if ( empty( $key ) ) {
				// Show all settings.
				$settings = array();
				foreach ( $valid_keys as $k ) {
					$option    = 'mxroute_mailer_' . $k;
					$raw       = get_option( $option, '' );
					$display   = $k;

					if ( 'password' === $k ) {
						$display = '' !== $raw ? '********' : '(not set)';
					} elseif ( in_array( $k, array( 'logging_enabled', 'keep_data' ), true ) ) {
						$display = $raw ? 'true' : 'false';
					} else {
						$display = (string) $raw;
					}

					$settings[ $k ] = $display;
				}

				WP_CLI\Utils\format_items( 'table', $settings, array( 'key', 'value' ) );
				return;
			}

			if ( ! in_array( $key, $valid_keys, true ) ) {
				WP_CLI::error( sprintf( 'Invalid setting key: %s. Valid keys: %s', $key, implode( ', ', $valid_keys ) ) );
			}

			$option = 'mxroute_mailer_' . $key;
			$raw    = get_option( $option, '' );

			if ( 'password' === $key ) {
				$raw = '' !== $raw ? '********' : '(not set)';
			} elseif ( in_array( $key, array( 'logging_enabled', 'keep_data' ), true ) ) {
				$raw = $raw ? 'true' : 'false';
			}

			WP_CLI::log( $raw );
			return;
		}

		if ( 'set' === $action ) {
			if ( empty( $key ) || ! in_array( $key, $valid_keys, true ) ) {
				WP_CLI::error( sprintf( 'Invalid setting key: %s. Valid keys: %s', $key, implode( ', ', $valid_keys ) ) );
			}

			if ( 'password' === $key ) {
				if ( empty( $value ) ) {
					WP_CLI::error( 'Password cannot be empty.' );
				}
				$encrypted = MXRoute_Crypto::encrypt( $value );
				if ( is_wp_error( $encrypted ) ) {
					WP_CLI::error( $encrypted->get_error_message() );
				}
				$value = $encrypted;
			} elseif ( 'batch_size' === $key ) {
				$value = intval( $value );
				if ( $value < 1 || $value > 50 ) {
					WP_CLI::error( 'Batch size must be between 1 and 50.' );
				}
			} elseif ( in_array( $key, array( 'logging_enabled', 'keep_data' ), true ) ) {
				$value = $value ? 1 : 0;
			}

			$option = 'mxroute_mailer_' . $key;
			update_option( $option, $value );

			$display = 'password' === $key ? '********' : $value;
			WP_CLI::success( sprintf( 'Setting %s updated to %s.', $key, $display ) );
			return;
		}

		WP_CLI::error( 'Invalid action. Use "get" or "set".' );
	}

	/**
	 * List, view, clear, or delete email logs.
	 *
	 * ## OPTIONS
	 *
	 * [<action>]
	 * : The action to perform: list, view, clear, delete.
	 *
	 * [<id>]
	 * : Log ID (required for view and delete actions).
	 *
	 * [--status=<status>]
	 * : Filter by status: 1 (sent), -1 (failed), 0 (pending).
	 *
	 * [--per-page=<per-page>]
	 * : Number of results per page.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--page=<page>]
	 * : Page number.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format: table, csv, json.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp mxroute logs list
	 *     wp mxroute logs list --status=1 --per-page=10
	 *     wp mxroute logs view 123
	 *     wp mxroute logs delete 123
	 *     wp mxroute logs clear
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function logs( $args, $assoc_args ) {
		$action = $args[0] ?? '';
		$id     = $args[1] ?? '';

		$logger = new MXRoute_Logger();

		if ( 'list' === $action || '' === $action ) {
			$per_page = intval( $assoc_args['per-page'] ?? 20 );
			$page     = intval( $assoc_args['page'] ?? 1 );
			$format   = $assoc_args['format'] ?? 'table';
			$status   = $assoc_args['status'] ?? '';

			$filters = array();
			if ( '' !== $status ) {
				$filters['success'] = $status;
			}

			$result = $logger->get_logs( $per_page, $page, $filters );

			if ( empty( $result['logs'] ) ) {
				WP_CLI::log( 'No logs found.' );
				return;
			}

			$rows = array();
			foreach ( $result['logs'] as $log ) {
				$status_label = 1 === (int) $log->success ? 'Sent' : ( -1 === (int) $log->success ? 'Failed' : 'Pending' );
				$rows[]       = array(
					'ID'        => $log->id,
					'Timestamp' => $log->timestamp,
					'From'      => $log->from_email,
					'To'        => $log->to_email,
					'Subject'   => $log->subject,
					'Status'    => $status_label,
					'Transport' => $log->transport,
				);
			}

			WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'Timestamp', 'From', 'To', 'Subject', 'Status', 'Transport' ) );
			WP_CLI::log( sprintf( 'Showing %d of %d total logs.', count( $result['logs'] ), $result['total'] ) );
			return;
		}

		if ( 'view' === $action ) {
			if ( empty( $id ) || ! is_numeric( $id ) ) {
				WP_CLI::error( 'Please provide a log ID.' );
			}

			$log = $logger->get_log( intval( $id ) );
			if ( ! $log ) {
				WP_CLI::error( 'Log not found.' );
			}

			$status_label = 1 === (int) $log->success ? 'Sent' : ( -1 === (int) $log->success ? 'Failed' : 'Pending' );
			$request      = json_decode( $log->api_request, true );
			$response     = json_decode( $log->api_response, true );

			$rows = array(
				array( 'Field' => 'ID', 'Value' => $log->id ),
				array( 'Field' => 'Timestamp', 'Value' => $log->timestamp ),
				array( 'Field' => 'Status', 'Value' => $status_label ),
				array( 'Field' => 'From', 'Value' => $log->from_email ),
				array( 'Field' => 'Reply-To', 'Value' => $log->reply_to ?: '(none)' ),
				array( 'Field' => 'To', 'Value' => $log->to_email ),
				array( 'Field' => 'Subject', 'Value' => $log->subject ),
				array( 'Field' => 'Transport', 'Value' => $log->transport ),
				array( 'Field' => 'Created', 'Value' => $log->created_at ?: $log->timestamp ),
				array( 'Field' => 'Processed', 'Value' => $log->processed_at ?: '(pending)' ),
			);

			WP_CLI\Utils\format_items( 'table', $rows, array( 'Field', 'Value' ) );

			if ( ! empty( $log->message ) ) {
				WP_CLI::log( '' );
				WP_CLI::log( '--- Message ---' );
				WP_CLI::log( wp_strip_all_tags( $log->message ) );
			}

			if ( ! empty( $request ) ) {
				WP_CLI::log( '' );
				WP_CLI::log( '--- API Request ---' );
				WP_CLI::log( wp_json_encode( $request, JSON_PRETTY_PRINT ) );
			}

			if ( ! empty( $response ) ) {
				WP_CLI::log( '' );
				WP_CLI::log( '--- API Response ---' );
				WP_CLI::log( wp_json_encode( $response, JSON_PRETTY_PRINT ) );
			}
			return;
		}

		if ( 'delete' === $action ) {
			if ( empty( $id ) || ! is_numeric( $id ) ) {
				WP_CLI::error( 'Please provide a log ID.' );
			}

			$log = $logger->get_log( intval( $id ) );
			if ( ! $log ) {
				WP_CLI::error( 'Log not found.' );
			}

			$logger->delete_log( intval( $id ) );
			WP_CLI::success( sprintf( 'Log %d deleted.', $id ) );
			return;
		}

		if ( 'clear' === $action ) {
			$logger->clear_logs();
			WP_CLI::success( 'All logs cleared.' );
			return;
		}

		WP_CLI::error( 'Invalid action. Use "list", "view", "delete", or "clear".' );
	}

	/**
	 * Manage the email queue.
	 *
	 * ## OPTIONS
	 *
	 * [<action>]
	 * : The action to perform: list, count, clear.
	 *
	 * [--per-page=<per-page>]
	 * : Number of results per page.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--page=<page>]
	 * : Page number.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format: table, csv, json.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp mxroute queue list
	 *     wp mxroute queue count
	 *     wp mxroute queue clear
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function queue( $args, $assoc_args ) {
		$action = $args[0] ?? 'list';
		$queue  = new MXRoute_Queue();

		if ( 'count' === $action ) {
			$count = $queue->count_pending();
			WP_CLI::log( $count );
			return;
		}

		if ( 'clear' === $action ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'mxroute_mailer_logs';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, safe.
			$count = (int) $wpdb->query( "DELETE FROM {$table_name} WHERE success = 0 AND processed_at IS NULL" );
			WP_CLI::success( sprintf( '%d pending items cleared.', $count ) );
			return;
		}

		if ( 'list' === $action || '' === $action ) {
			$per_page = intval( $assoc_args['per-page'] ?? 20 );
			$page     = intval( $assoc_args['page'] ?? 1 );
			$format   = $assoc_args['format'] ?? 'table';

			$items = $queue->get_pending_paginated( $per_page, $page );
			$count = $queue->count_pending();

			if ( empty( $items ) ) {
				WP_CLI::log( 'No pending items in queue.' );
				return;
			}

			$rows = array();
			foreach ( $items as $item ) {
				$rows[] = array(
					'ID'        => $item->id,
					'Created'   => $item->created_at,
					'From'      => $item->from_email,
					'To'        => $item->to_email,
					'Subject'   => $item->subject,
					'Transport' => $item->transport,
				);
			}

			WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'Created', 'From', 'To', 'Subject', 'Transport' ) );
			WP_CLI::log( sprintf( 'Showing %d of %d pending items.', count( $items ), $count ) );
			return;
		}

		WP_CLI::error( 'Invalid action. Use "list", "count", or "clear".' );
	}

	/**
	 * Send an email directly through MXRoute (bypasses queue).
	 *
	 * ## OPTIONS
	 *
	 * <to>
	 * : Recipient email address.
	 *
	 * [<subject>]
	 * : Email subject.
	 *
	 * [<message>]
	 * : Email body.
	 *
	 * [--from=<from>]
	 * : Sender email address. Defaults to configured username.
	 *
	 * [--reply-to=<reply-to>]
	 * : Reply-To email address.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mxroute send user@example.com "Test Subject" "Test body"
	 *     wp mxroute send user@example.com --from=noreply@example.com
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function send( $args, $assoc_args ) {
		$to       = sanitize_email( $args[0] );
		$subject  = sanitize_text_field( $args[1] ?? '' );
		$message  = sanitize_textarea_field( $args[2] ?? '' );
		$from     = sanitize_email( $assoc_args['from'] ?? get_option( 'mxroute_mailer_username', '' ) );
		$reply_to = sanitize_email( $assoc_args['reply-to'] ?? '' );

		if ( empty( $to ) ) {
			WP_CLI::error( 'Recipient email address is required.' );
		}

		if ( empty( $subject ) ) {
			$subject = __( 'MXRoute Mailer CLI Send', 'mxroute-mailer' );
		}

		if ( empty( $message ) ) {
			$message = __( 'Email sent via MXRoute Mailer CLI.', 'mxroute-mailer' );
		}

		if ( empty( $from ) ) {
			WP_CLI::error( 'No sender address configured. Set username first: wp mxroute settings set username <email>' );
		}

		$api = new MXRoute_API();
		WP_CLI::log( 'Sending email...' );

		$result = $api->send( $from, $to, $subject, $message, $reply_to );

		if ( $result['success'] ) {
			WP_CLI::success( 'Email sent successfully.' );
		} else {
			WP_CLI::error( sprintf( 'Failed to send: %s', $result['message'] ) );
		}
	}

	/**
	 * Send a test email through the queue.
	 *
	 * ## OPTIONS
	 *
	 * <to>
	 * : Recipient email address.
	 *
	 * [--subject=<subject>]
	 * : Email subject.
	 * ---
	 * default: MXRoute Mailer Test
	 * ---
	 *
	 * [--message=<message>]
	 * : Email body.
	 * ---
	 * default: This is a test email from MXRoute Mailer CLI.
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp mxroute test user@example.com
	 *     wp mxroute test user@example.com --subject="Custom Subject"
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function test( $args, $assoc_args ) {
		$to      = sanitize_email( $args[0] );
		$from    = get_option( 'mxroute_mailer_username', '' );
		$subject = sanitize_text_field( $assoc_args['subject'] ?? '' );
		$message = sanitize_textarea_field( $assoc_args['message'] ?? '' );

		if ( empty( $to ) ) {
			WP_CLI::error( 'Recipient email address is required.' );
		}

		if ( empty( $from ) ) {
			WP_CLI::error( 'No sender address configured. Set username first: wp mxroute settings set username <email>' );
		}

		if ( '' === $subject ) {
			$subject = __( 'MXRoute Mailer Test', 'mxroute-mailer' );
		}
		if ( '' === $message ) {
			$message = __( 'This is a test email from MXRoute Mailer CLI.', 'mxroute-mailer' );
		}

		$queue = new MXRoute_Queue();
		$log_id = $queue->add( $from, $to, $subject, $message, '', array(), '' );

		if ( false === $log_id ) {
			WP_CLI::error( 'Failed to queue test email.' );
		}

		WP_CLI::success( sprintf( 'Test email queued (ID: %d). It will be sent on the next cron cycle.', $log_id ) );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'mxroute', 'MXRoute_CLI_Commands' );
}
