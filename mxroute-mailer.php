<?php
/**
 * Plugin Name: MXRoute Mailer
 * Plugin URI: https://richardkentgates.com
 * Description: Sends WordPress email through MXRoute's HTTP API over port 443. Includes logging, test tools, and automatic updates.
 * Version: 1.3.9
 * Author: Richard Kent Gates
 * Author URI: https://richardkentgates.com
 * License: GPL v2 or later
 * Text Domain: mxroute-mailer
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
define( 'MXROUTE_MAILER_VERSION', '1.3.9' );

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

require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-crypto.php';
require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-mailer.php';
require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-updater.php';

register_activation_hook(
	__FILE__,
	static function () {
		MXRoute_Logger::create_table();
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema upgrade.
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

new MXRoute_Updater(
	__FILE__,
	'richardkentgates/mxroute-mailer',
	MXROUTE_MAILER_VERSION
);

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
 * Run daily queue cleanup to remove old processed entries.
 *
 * @return void
 */
function mxroute_mailer_daily_cleanup() {
	$queue = new MXRoute_Queue();
	$queue->cleanup( 30 );
}
add_action( 'mxroute_mailer_daily_cleanup', 'mxroute_mailer_daily_cleanup' );
