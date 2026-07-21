<?php
/**
 * Mock WP_CLI\Utils namespace for testing.
 *
 * @package MXRoute_Mailer
 */

namespace WP_CLI\Utils;

if ( ! function_exists( 'WP_CLI\Utils\format_items' ) ) {
	/**
	 * Mock format_items for CLI output tests.
	 *
	 * @param string $format  Output format.
	 * @param array  $items   Rows of data.
	 * @param array  $columns Column headers.
	 */
	function format_items( $format, $items, $columns ) {
		$GLOBALS['wp_cli_format_items'] = array(
			'format'  => $format,
			'items'   => $items,
			'columns' => $columns,
		);
	}
}
