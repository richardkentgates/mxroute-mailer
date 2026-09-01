<?php
/**
 * MXRoute Mailer REST API.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers REST API routes for MXRoute Mailer.
 *
 * External endpoints are protected by WordPress Application Passwords
 * (Basic Auth). Internal logic reads status JSON files directly and
 * never hits the REST API.
 */
class MXRoute_REST_API {

	/**
	 * Register REST routes.
	 *
	 * Called from rest_api_init. Registers all endpoints unconditionally
	 * so they are available for both authenticated and unauthenticated
	 * requests — the permission callback handles access control.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			'mxroute/v1',
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_status' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
	}

	/**
	 * Permission callback for external endpoints.
	 *
	 * Requires the user to be authenticated with WordPress Application
	 * Passwords (Basic Auth) and have manage_options capability.
	 *
	 * WordPress core processes the Authorization header before the
	 * permission callback runs. If a valid Application Password is
	 * provided, wp_get_current_user() returns the authenticated user.
	 *
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public static function check_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_unauthorized',
				__( 'Authentication required.', 'mxroute-mailer' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this resource.', 'mxroute-mailer' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /mxroute/v1/status
	 *
	 * Returns the queue status JSON. Reads from the status file written
	 * by the queue processor. Falls back to a live DB query if the file
	 * is missing.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response data or error.
	 */
	public static function get_status( WP_REST_Request $request ) {
		$status_file = '/var/run/mxroute-status.json';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading status file.
		$json = @file_get_contents( $status_file );

		if ( false !== $json ) {
			$data = json_decode( $json, true );
			if ( is_array( $data ) ) {
				return rest_ensure_response( $data );
			}
		}

		// Fallback: live query if status file is missing.
		return rest_ensure_response( self::get_live_status() );
	}

	/**
	 * Build status data from live DB queries.
	 *
	 * Used as fallback when the status file does not exist.
	 *
	 * @return array Status data.
	 */
	private static function get_live_status() {
		global $wpdb;

		$table = $wpdb->prefix . 'mxroute_mailer_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$pending = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE success = 0 AND processed_at IS NULL" )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$sent = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE success = 1" )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
		$failed = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE success = -1" )
		);

		$last_run = wp_next_scheduled( 'mxroute_mailer_process_queue' );

		return array(
			'pending'    => $pending,
			'sent'       => $sent,
			'failed'     => $failed,
			'cron'       => array(
				'last_run'       => $last_run ? gmdate( 'Y-m-d H:i:s', $last_run ) : null,
				'next_scheduled' => $last_run ? gmdate( 'Y-m-d H:i:s', $last_run ) : null,
				'interval'       => 60,
			),
			'version'    => MXROUTE_MAILER_VERSION,
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		);
	}
}
