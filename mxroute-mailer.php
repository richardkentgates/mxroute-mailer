<?php
/**
 * Plugin Name: MXRoute Mailer
 * Plugin URI: https://mxroute.com
 * Description: Routes all WordPress email through MXRoute's HTTP API. Works on Google Cloud where SMTP ports are blocked.
 * Version: 1.1.2
 * Author: MXRoute
 * License: GPL v2 or later
 * Text Domain: mxroute-mailer
 * Requires PHP: 7.3
 * Requires at least: 5.0
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

define( 'MXROUTE_MAILER_VERSION', '1.0.0' );
define( 'MXROUTE_MAILER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
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

register_activation_hook(
	__FILE__,
	static function () {
		require_once MXROUTE_MAILER_PLUGIN_DIR . 'includes/class-mxroute-logger.php';
		MXRoute_Logger::create_table();
	}
);

mxroute_mailer();
