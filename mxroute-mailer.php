<?php
/**
 * Plugin Name: MXRoute Mailer
 * Plugin URI: https://richardkentgates.com
 * Description: Sends WordPress email through MXRoute's HTTP API over port 443. Includes logging, test tools, and automatic updates.
 * Version: 1.4.69
 * Author: Richard Kent Gates
 * Author URI: https://richardkentgates.com
 * License: GPL v2 or later
 * Text Domain: mxroute-mailer
 * Domain Path: /languages
 * Requires PHP: 7.3
 * Requires at least: 5.0
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin version.
 *
 * @var string
 */
define( 'MXROUTE_MAILER_VERSION', '1.4.69' );

/**
 * Absolute path to the main plugin file.
 *
 * Used by MXRoute_Updater to derive the correct plugin_basename for the
 * WordPress update transient. Must be defined here, not in a sub-file,
 * so that plugin_basename() resolves to "mxroute-mailer/mxroute-mailer.php".
 *
 * @var string
 */
define( 'MXROUTE_MAILER_FILE', __FILE__ );

/**
 * Enable debug logging for API calls.
 *
 * Set to true in wp-config.php to log MXRoute API requests and responses
 * to the WordPress debug log. Do not leave enabled in production.
 *
 * @var bool
 */
if ( ! defined( 'MXROUTE_MAILER_DEBUG' ) ) {
	define( 'MXROUTE_MAILER_DEBUG', false );
}

/**
 * Absolute path to the plugin directory.
 *
 * @var string
 */
define( 'MXROUTE_MAILER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL to the plugin directory.
 *
 * @var string
 */
define( 'MXROUTE_MAILER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Get the MXRoute_Mailer singleton instance.
 *
 * @return MXRoute_Mailer
 */
function mxroute_mailer() {
	return MXRoute_Mailer::instance();
}

/**
 * Check if the current user can manage MXRoute Mailer settings.
 *
 * On multisite, checks for manage_network_options capability.
 * On single site, checks for manage_options capability.
 *
 * @return bool True if the user can manage settings.
 */
function mxroute_mailer_can_manage() {
	if ( is_multisite() ) {
		return current_user_can( 'manage_network_options' );
	}
	return current_user_can( 'manage_options' );
}

require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-crypto.php';
require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-mailer.php';
require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-updater.php';
require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-cron-tracker.php';

register_activation_hook(
	__FILE__,
	static function () {
		MXRoute_Logger::create_table();

		// On multisite, create tables for all existing sites.
		if ( is_multisite() ) {
			$sites = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $sites as $site_id ) {
				switch_to_blog( $site_id );
				MXRoute_Logger::create_table();
				restore_current_blog();
			}
		}

		// Schedule cron events.
		if ( ! wp_next_scheduled( 'mxroute_mailer_process_queue' ) ) {
			wp_schedule_event( time(), 'mxroute_mailer_interval', 'mxroute_mailer_process_queue' );
		}
		if ( ! wp_next_scheduled( 'mxroute_write_status_json' ) ) {
			wp_schedule_event( time(), 'mxroute_mailer_interval', 'mxroute_write_status_json' );
		}
	}
);

/**
 * Run database upgrades on admin init.
 *
 * @return void
 */
function mxroute_mailer_db_upgrade() {
	if ( get_option( 'mxroute_mailer_db_version', '0' ) !== MXROUTE_MAILER_VERSION ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mxroute_mailer_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, safe. Schema upgrade.
		$columns = $wpdb->get_col( "DESCRIBE `$table_name`", 0 );

		if ( ! in_array( 'reply_to', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `reply_to` varchar(255) NOT NULL DEFAULT '' AFTER `from_email`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( ! in_array( 'headers', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `headers` longtext NOT NULL AFTER `message`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( ! in_array( 'attachments', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `attachments` longtext NOT NULL AFTER `headers`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( ! in_array( 'created_at', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `created_at` datetime DEFAULT NULL AFTER `success`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( ! in_array( 'processed_at', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `processed_at` datetime DEFAULT NULL AFTER `created_at`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( ! in_array( 'transport', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `transport` varchar(10) NOT NULL DEFAULT 'api' AFTER `success`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Migrate old failed entries (success=0) to new failed status (success=-1)
		// before widening the column. In the old system 0 = failed; in the new
		// system 0 = pending (queued). At upgrade time no queue entries exist yet,
		// so every success=0 row is an old failure.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE `$table_name` SET `success` = -1 WHERE `success` = 0" );

		// Widen success column to support -1 (failed) status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$success_type = $wpdb->get_var( "SHOW COLUMNS FROM `$table_name` LIKE 'success'" );
		if ( false !== strpos( $success_type, 'tinyint(1)' ) ) {
			$wpdb->query( "ALTER TABLE `$table_name` MODIFY COLUMN `success` tinyint(2) NOT NULL DEFAULT 0" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		update_option( 'mxroute_mailer_db_version', MXROUTE_MAILER_VERSION );
	}
}
add_action( 'admin_init', 'mxroute_mailer_db_upgrade' );

mxroute_mailer();

MXRoute_Updater::init();

/**
 * Schedule the daily queue cleanup cron event.
 *
 * @return void
 */
function mxroute_mailer_schedule_cleanup() {
	if ( ! wp_next_scheduled( 'mxroute_mailer_daily_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'mxroute_mailer_daily_cleanup' );
	}
}
add_action( 'init', 'mxroute_mailer_schedule_cleanup' );

/**
 * Register custom cron intervals for queue processing.
 *
 * @param array $schedules Existing cron schedules.
 * @return array Modified schedules with the MXRoute interval added.
 */
function mxroute_mailer_cron_schedules( $schedules ) {
	$schedules['mxroute_mailer_interval'] = array(
		'interval' => 60,
		'display'  => __( 'Every Minute', 'mxroute-mailer' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'mxroute_mailer_cron_schedules' );

/**
 * Run daily queue cleanup to remove old processed entries.
 *
 * @return void
 */
function mxroute_mailer_daily_cleanup() {
	$queue = new MXRoute_Queue();
	$queue->cleanup( 30 );
}
add_action( 'mxroute_mailer_daily_cleanup', MXRoute_Cron_Tracker::wrap( 'mxroute_mailer_daily_cleanup', 'mxroute_mailer_daily_cleanup' ) );

// -------------------------------------------------------------------------
// Dashboard widget
// -------------------------------------------------------------------------

add_action( 'wp_dashboard_setup', 'mxroute_mailer_register_dashboard_widget' );

/**
 * Register the MXRoute Mailer dashboard widget.
 *
 * @return void
 */
function mxroute_mailer_register_dashboard_widget() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_add_dashboard_widget(
		'mxroute_mailer_status',
		__( 'MXRoute Mailer Status', 'mxroute-mailer' ),
		'mxroute_mailer_render_dashboard_widget'
	);
}

/**
 * Render the MXRoute Mailer dashboard widget.
 *
 * @return void
 */
function mxroute_mailer_render_dashboard_widget() {
	$content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '/tmp';
	$status_file = $content_dir . '/mxroute-status.json';
	$data        = array();

	if ( is_readable( $status_file ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json = @file_get_contents( $status_file );
		if ( false !== $json ) {
			$decoded = json_decode( $json, true );
			if ( is_array( $decoded ) ) {
				$data = $decoded;
			}
		}
	}

	$pending = $data['pending'] ?? 0;
	$sent    = $data['sent'] ?? 0;
	$failed  = $data['failed'] ?? 0;
	$history = $data['history'] ?? array();
	?>
	<table class="widefat striped" style="margin-bottom:0">
		<thead><tr><th><?php esc_html_e( 'Queue', 'mxroute-mailer' ); ?></th><th><?php esc_html_e( 'Count', 'mxroute-mailer' ); ?></th></tr></thead>
		<tbody>
			<tr>
				<td><?php esc_html_e( 'Pending', 'mxroute-mailer' ); ?></td>
				<td><?php echo $pending > 0
					? '<span style="color:#dba617;font-weight:600;">' . esc_html( $pending ) . '</span>'
					: '<span style="color:#00a32a;">0</span>'; ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Sent', 'mxroute-mailer' ); ?></td>
				<td><?php echo esc_html( $sent ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Failed', 'mxroute-mailer' ); ?></td>
				<td><?php echo $failed > 0
					? '<span style="color:#d63638;font-weight:600;">' . esc_html( $failed ) . '</span>'
					: '0'; ?></td>
			</tr>
		</tbody>
	</table>
	<?php if ( ! empty( $history ) ) : ?>
	<br />
	<table class="widefat striped" style="margin-bottom:0">
		<thead><tr><th><?php esc_html_e( 'Cron Event', 'mxroute-mailer' ); ?></th><th><?php esc_html_e( 'Last Run', 'mxroute-mailer' ); ?></th><th><?php esc_html_e( 'Status', 'mxroute-mailer' ); ?></th><th><?php esc_html_e( 'Pass', 'mxroute-mailer' ); ?></th><th><?php esc_html_e( 'Fail', 'mxroute-mailer' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $history as $hook => $info ) : ?>
			<tr>
				<td><?php echo esc_html( $hook ); ?></td>
				<td><?php echo esc_html( ! empty( $info['last_run'] ) ? gmdate( 'Y-m-d H:i', strtotime( $info['last_run'] ) ) : '—' ); ?></td>
				<td><?php echo 'pass' === ( $info['last_status'] ?? '' )
					? '<span class="dashicons dashicons-yes-alt" style="color:#00a32a;font-size:18px;width:18px;height:18px;"></span>'
					: '<span class="dashicons dashicons-dismiss" style="color:#d63638;font-size:18px;width:18px;height:18px;"></span>'; ?></td>
				<td><?php echo esc_html( $info['pass_count'] ?? 0 ); ?></td>
				<td><?php echo ( $info['fail_count'] ?? 0 ) > 0
					? '<span style="color:#d63638;">' . esc_html( $info['fail_count'] ) . '</span>'
					: esc_html( $info['fail_count'] ?? 0 ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
	<?php
}

/**
 * Create the logs table when a new site is added on multisite.
 *
 * @param WP_Site $new_site New site object.
 * @return void
 */
function mxroute_mailer_new_site( $new_site ) {
	switch_to_blog( $new_site->blog_id );
	MXRoute_Logger::create_table();
	restore_current_blog();
}
if ( is_multisite() ) {
	add_action( 'wp_initialize_site', 'mxroute_mailer_new_site' );
}

/**
 * Load WP-CLI commands if available.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-cli.php';
}
