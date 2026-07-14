<?php
/**
 * MXRoute Mailer admin settings.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers admin settings pages and handles asset loading.
 */
class MXRoute_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_title', array( $this, 'set_admin_title' ), 10, 2 );
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		add_options_page(
			__( 'MXRoute Mailer', 'mxroute-mailer' ),
			__( 'MXRoute Mailer', 'mxroute-mailer' ),
			'manage_options',
			'mxroute-mailer',
			array( $this, 'render_settings_page' )
		);

		add_management_page(
			__( 'MXRoute Email Logs', 'mxroute-mailer' ),
			__( 'MXRoute Logs', 'mxroute-mailer' ),
			'manage_options',
			'mxroute-logs',
			array( $this, 'render_logs_page' )
		);

		add_submenu_page(
			null,
			__( 'MXRoute Log Detail', 'mxroute-mailer' ),
			__( 'MXRoute Log Detail', 'mxroute-mailer' ),
			'manage_options',
			'mxroute-log-view',
			array( $this, 'render_log_view_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'mxroute_mailer_settings',
			'mxroute_mailer_server',
			array(
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'mxroute_mailer_settings',
			'mxroute_mailer_username',
			array(
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'mxroute_mailer_settings',
			'mxroute_mailer_password',
			array(
				'sanitize_callback' => array( $this, 'sanitize_password' ),
			)
		);
		register_setting(
			'mxroute_mailer_settings',
			'mxroute_mailer_default_from',
			array(
				'sanitize_callback' => 'sanitize_email',
			)
		);
		register_setting(
			'mxroute_mailer_settings',
			'mxroute_mailer_logging_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
		register_setting(
			'mxroute_mailer_settings',
			'mxroute_mailer_keep_data',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			)
		);
	}

	/**
	 * Sanitize checkbox value.
	 *
	 * @param mixed $value Checkbox value.
	 * @return int 1 or 0.
	 */
	public function sanitize_checkbox( $value ) {
		return $value ? 1 : 0;
	}

	/**
	 * Sanitize password value, preserving existing when empty.
	 *
	 * When the password field is left blank on save, the stored password is
	 * returned unchanged so the user does not accidentally clear it.
	 *
	 * @param string $value Password value.
	 * @return string Sanitized password.
	 */
	public function sanitize_password( $value ) {
		$value = sanitize_text_field( $value );
		if ( empty( $value ) ) {
			return get_option( 'mxroute_mailer_password', '' );
		}
		return $value;
	}

	/**
	 * Set the admin page title for the log detail view.
	 *
	 * Hooks into admin_title so the title is set before admin-header.php
	 * calls strip_tags(), preventing PHP 8.1 null deprecation.
	 *
	 * @param string $title The admin page title.
	 * @return string Modified title.
	 */
	public function set_admin_title( $title ) {
		if ( isset( $_GET['page'] ) && 'mxroute-log-view' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return __( 'MXRoute Log Detail', 'mxroute-mailer' );
		}
		return $title;
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$is_log_view = isset( $_GET['page'] ) && 'mxroute-log-view' === sanitize_text_field( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'settings_page_mxroute-mailer' !== $hook && 'tools_page_mxroute-logs' !== $hook && ! $is_log_view ) {
			return;
		}

		wp_enqueue_style(
			'mxroute-mailer-admin',
			MXROUTE_MAILER_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			MXROUTE_MAILER_VERSION
		);

		if ( 'tools_page_mxroute-logs' === $hook ) {
			wp_enqueue_script(
				'mxroute-mailer-admin',
				MXROUTE_MAILER_PLUGIN_URL . 'admin/js/admin.js',
				array( 'jquery' ),
				MXROUTE_MAILER_VERSION,
				true
			);

			wp_localize_script(
				'mxroute-mailer-admin',
				'mxrouteMailer',
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'logManageNonce' => wp_create_nonce( 'mxroute_log_manage' ),
					'i18n'           => array(
						'confirmDelete'     => __( 'Delete this log entry?', 'mxroute-mailer' ),
						'confirmClear'      => __( 'Are you sure you want to clear ALL email logs? This cannot be undone.', 'mxroute-mailer' ),
						// translators: %d: number of log entries to delete.
						'confirmBulkDelete' => __( 'Are you sure you want to delete %d log entries? This cannot be undone.', 'mxroute-mailer' ),
						'failedDelete'      => __( 'Failed to delete log.', 'mxroute-mailer' ),
						'failedClear'       => __( 'Failed to clear logs.', 'mxroute-mailer' ),
						'failedBulkDelete'  => __( 'Failed to delete logs.', 'mxroute-mailer' ),
						'noSelection'       => __( 'No logs selected.', 'mxroute-mailer' ),
					),
				)
			);
		}
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		include MXROUTE_MAILER_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render the logs page.
	 *
	 * @return void
	 */
	public function render_logs_page() {
		include MXROUTE_MAILER_PLUGIN_DIR . 'admin/views/logs.php';
	}

	/**
	 * Render the single log view page.
	 *
	 * @return void
	 */
	public function render_log_view_page() {
		include MXROUTE_MAILER_PLUGIN_DIR . 'admin/views/log-view.php';
	}
}

new MXRoute_Settings();
