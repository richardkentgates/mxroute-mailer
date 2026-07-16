<?php
/**
 * Plugin Name: MXRoute Mailer
 * Plugin URI: https://richardkentgates.com
 * Description: Sends WordPress email through MXRoute's HTTP API over port 443. Includes logging, test tools, and automatic updates.
 * Version: 1.2.14
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
define( 'MXROUTE_MAILER_VERSION', '1.2.14' );

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

require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-mailer.php';
require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-updater.php';

register_activation_hook(
	__FILE__,
	static function () {
		require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-logger.php';
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
		$table_name    = $wpdb->prefix . 'mxroute_mailer_logs';
		$column_exists = $wpdb->get_var( "SHOW COLUMNS FROM `$table_name` LIKE 'reply_to'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema upgrade.
		if ( ! $column_exists ) {
			$wpdb->query( "ALTER TABLE `$table_name` ADD COLUMN `reply_to` varchar(255) NOT NULL DEFAULT '' AFTER `from_email`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Schema upgrade.
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
