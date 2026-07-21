<?php
/**
 * MXRoute Mailer uninstall handler.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * @package MXRoute_Mailer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( get_option( 'mxroute_mailer_keep_data' ) ) {
	return;
}

global $wpdb;

$table_name = $wpdb->prefix . 'mxroute_mailer_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup, table drop cannot use prepare.
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

delete_option( 'mxroute_mailer_server' );
delete_option( 'mxroute_mailer_username' );
delete_option( 'mxroute_mailer_password' );
delete_option( 'mxroute_mailer_logging_enabled' );
delete_option( 'mxroute_mailer_keep_data' );
delete_option( 'mxroute_mailer_batch_size' );
delete_option( 'mxroute_mailer_db_version' );

// Remove stored attachment files.
$upload_dir = wp_upload_dir();
$attach_dir = $upload_dir['basedir'] . '/mxroute-mailer-attachments';

if ( is_dir( $attach_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();

	global $wp_filesystem;

	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $attach_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $files as $file ) {
		if ( $file->isDir() ) {
			$wp_filesystem->rmdir( $file->getRealPath() );
		} else {
			$wp_filesystem->delete( $file->getRealPath() );
		}
	}

	$wp_filesystem->rmdir( $attach_dir );
}
