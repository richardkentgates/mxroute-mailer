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
		add_action( 'admin_init', array( $this, 'set_log_view_title' ) );
		add_action( 'load-settings_page_mxroute-mailer', array( $this, 'add_settings_help_tabs' ) );
		add_action( 'load-tools_page_mxroute-logs', array( $this, 'add_logs_help_tabs' ) );
		add_action( 'load-tools_page_mxroute-log-view', array( $this, 'add_log_view_help_tabs' ) );
		add_filter( 'pre_update_option_mxroute_mailer_password', array( $this, 'encrypt_password_on_update' ), 10, 3 );
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
				'sanitize_callback' => array( $this, 'sanitize_username_local' ),
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
	 * Encrypt the password before it is saved to the database.
	 *
	 * This runs on the pre_update_option filter. Empty values keep the old
	 * password (the user left the field blank). Identical values are not
	 * re-encrypted.
	 *
	 * @param string $value     New option value.
	 * @param string $old_value Previous option value.
	 * @param string $option    Option name.
	 * @return string Encrypted value, old value, or input value.
	 */
	public function encrypt_password_on_update( $value, $old_value, $option ) {
		if ( '' === $value ) {
			return $old_value;
		}
		if ( $value === $old_value ) {
			return $value;
		}
		update_option( 'mxroute_mailer_password_encrypted', 1 );
		return MXRoute_Crypto::encrypt( $value );
	}

	/**
	 * Sanitize username local part and combine with domain from From Email.
	 *
	 * @param string $value Local part of the username submitted by the form.
	 * @return string Full username email address.
	 */
	public function sanitize_username_local( $value ) {
		$local = sanitize_text_field( $value );
		if ( false !== strpos( $local, '@' ) ) {
			$local = substr( $local, 0, strpos( $local, '@' ) );
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return sanitize_email( $local . '@' . $host );
	}

	/**
	 * Pre-set the page title for the log detail view on admin_init.
	 *
	 * This runs before admin-header.php loads, so get_admin_page_title()
	 * finds a non-empty $title and returns early, avoiding strip_tags(null).
	 *
	 * @return void
	 */
	public function set_log_view_title() {
		if ( isset( $_GET['page'] ) && 'mxroute-log-view' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$GLOBALS['title'] = __( 'MXRoute Log Detail', 'mxroute-mailer' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
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
						'logDeleted'        => __( 'Log entry deleted.', 'mxroute-mailer' ),
						'logsCleared'       => __( 'All logs cleared.', 'mxroute-mailer' ),
						'logsBulkDeleted'   => __( 'Selected logs deleted.', 'mxroute-mailer' ),
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

	/**
	 * Add help tabs for the Settings page.
	 *
	 * @return void
	 */
	public function add_settings_help_tabs() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'mxroute-overview',
				'title'   => __( 'Overview', 'mxroute-mailer' ),
				'content' => '<p>' . esc_html__( 'MXRoute Mailer intercepts all outgoing WordPress emails and routes them through MXRoute\'s HTTP API instead of SMTP. This bypasses port blocks on cloud hosting providers like Google Cloud.', 'mxroute-mailer' ) . '</p>'
					. '<p>' . esc_html__( 'Enter your MXRoute API credentials below, then use the test form to verify your setup.', 'mxroute-mailer' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'mxroute-credentials',
				'title'   => __( 'Credentials', 'mxroute-mailer' ),
				'content' => '<p><strong>' . esc_html__( 'Server Hostname:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'Found in your MXRoute control panel under Email Clients. Example: tuesday.mxrouting.net', 'mxroute-mailer' ) . '</p>'
					. '<p><strong>' . esc_html__( 'Username:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'Enter only the local part of your email address. The domain is taken from your WordPress site URL.', 'mxroute-mailer' ) . '</p>'
					. '<p><strong>' . esc_html__( 'Password:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'Your MXRoute password. Leave blank when editing to keep the current value.', 'mxroute-mailer' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'mxroute-test-email',
				'title'   => __( 'Test Email', 'mxroute-mailer' ),
				'content' => '<p>' . esc_html__( 'Use the test form at the bottom of this page to send a test email. Enter a recipient address, then click "Send Test Email". The sender address is automatically taken from your configured username.', 'mxroute-mailer' ) . '</p>'
					. '<p>' . esc_html__( 'If the test succeeds, you\'ll see a green notice. If it fails, you\'ll see a red notice with the error details.', 'mxroute-mailer' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'mxroute-options',
				'title'   => __( 'Options', 'mxroute-mailer' ),
				'content' => '<p><strong>' . esc_html__( 'Enable Logging:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'When checked, all sent emails are logged with request and response data. You can view logs under Tools > MXRoute Logs.', 'mxroute-mailer' ) . '</p>'
					. '<p><strong>' . esc_html__( 'Uninstall:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'When checked, your logs and settings are preserved when the plugin is deleted. Uncheck to remove all data on uninstall.', 'mxroute-mailer' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'mxroute-duplicate-sends',
				'title'   => __( 'Duplicate Sends', 'mxroute-mailer' ),
				'content' => '<p>' . esc_html__( 'If you are seeing two copies of every email, make sure you are running MXRoute Mailer 1.2.16 or later.', 'mxroute-mailer' ) . '</p>'
					. '<p>' . esc_html__( 'Starting with 1.2.16, the plugin uses the WordPress pre_wp_mail filter to stop the default server mailer (sendmail/ssmtp) before it runs, so only the MXRoute API send is delivered.', 'mxroute-mailer' ) . '</p>'
					. '<p>' . esc_html__( 'If you still see duplicates after updating, check for another active mail plugin that is also sending emails.', 'mxroute-mailer' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'mxroute-docs',
				'title'   => __( 'Documentation', 'mxroute-mailer' ),
				'content' => '<p>' . esc_html__( 'For setup details, configuration help, and troubleshooting, see the MXRoute Mailer wiki:', 'mxroute-mailer' ) . '</p>'
					. '<ul>'
					. '<li><a href="https://github.com/richardkentgates/mxroute-mailer/wiki/Installation" target="_blank">' . esc_html__( 'Installation', 'mxroute-mailer' ) . '</a></li>'
					. '<li><a href="https://github.com/richardkentgates/mxroute-mailer/wiki/Configuration" target="_blank">' . esc_html__( 'Configuration', 'mxroute-mailer' ) . '</a></li>'
					. '<li><a href="https://github.com/richardkentgates/mxroute-mailer/wiki/Troubleshooting" target="_blank">' . esc_html__( 'Troubleshooting', 'mxroute-mailer' ) . '</a></li>'
					. '<li><a href="https://github.com/richardkentgates/mxroute-mailer/wiki/Auto-Updates" target="_blank">' . esc_html__( 'Auto-Updates', 'mxroute-mailer' ) . '</a></li>'
					. '</ul>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'mxroute-mailer' ) . '</strong></p>'
			. '<p><a href="https://github.com/richardkentgates/mxroute-mailer/wiki" target="_blank">' . esc_html__( 'MXRoute Mailer Wiki', 'mxroute-mailer' ) . '</a></p>'
			. '<p><a href="https://mxroute.com" target="_blank">' . esc_html__( 'MXRoute Documentation', 'mxroute-mailer' ) . '</a></p>'
		);
	}

	/**
	 * Add help tabs for the Logs page.
	 *
	 * @return void
	 */
	public function add_logs_help_tabs() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'mxroute-logs-overview',
				'title'   => __( 'Overview', 'mxroute-mailer' ),
				'content' => '<p>' . esc_html__( 'This page displays a log of all emails sent through MXRoute Mailer. Each entry shows the timestamp, status, sender, recipient, and subject.', 'mxroute-mailer' ) . '</p>'
					. '<p>' . esc_html__( 'Click "View" on any row to see the full API request and response data for that email.', 'mxroute-mailer' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'mxroute-logs-filtering',
				'title'   => __( 'Filtering', 'mxroute-mailer' ),
				'content' => '<p>' . esc_html__( 'Use the filter controls above the table to narrow down results:', 'mxroute-mailer' ) . '</p>'
					. '<ul>'
					. '<li>' . esc_html__( 'Search: Filter by subject, sender, or recipient email address.', 'mxroute-mailer' ) . '</li>'
					. '<li>' . esc_html__( 'Status: Show only successful or failed emails.', 'mxroute-mailer' ) . '</li>'
					. '<li>' . esc_html__( 'From: Filter by a specific sender email address.', 'mxroute-mailer' ) . '</li>'
					. '<li>' . esc_html__( 'Date range: Filter logs between two dates.', 'mxroute-mailer' ) . '</li>'
					. '</ul>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'mxroute-logs-actions',
				'title'   => __( 'Actions', 'mxroute-mailer' ),
				'content' => '<p><strong>' . esc_html__( 'Clear All Logs:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'Removes all log entries. This action cannot be undone.', 'mxroute-mailer' ) . '</p>'
					. '<p><strong>' . esc_html__( 'Bulk Delete:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'Select multiple entries using the checkboxes, choose "Delete" from the Bulk Actions dropdown, and click "Apply".', 'mxroute-mailer' ) . '</p>'
					. '<p><strong>' . esc_html__( 'Delete Single Entry:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'Click the "Delete" button on any row to remove that specific log entry.', 'mxroute-mailer' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'mxroute-logs-docs',
				'title'   => __( 'Documentation', 'mxroute-mailer' ),
				'content' => '<p>' . esc_html__( 'Learn more about logging, configuration, and troubleshooting in the wiki:', 'mxroute-mailer' ) . '</p>'
					. '<ul>'
					. '<li><a href="https://github.com/richardkentgates/mxroute-mailer/wiki/Configuration" target="_blank">' . esc_html__( 'Configuration', 'mxroute-mailer' ) . '</a></li>'
					. '<li><a href="https://github.com/richardkentgates/mxroute-mailer/wiki/Troubleshooting" target="_blank">' . esc_html__( 'Troubleshooting', 'mxroute-mailer' ) . '</a></li>'
					. '<li><a href="https://github.com/richardkentgates/mxroute-mailer/wiki/Auto-Updates" target="_blank">' . esc_html__( 'Auto-Updates', 'mxroute-mailer' ) . '</a></li>'
					. '</ul>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'mxroute-mailer' ) . '</strong></p>'
			. '<p><a href="https://github.com/richardkentgates/mxroute-mailer/wiki" target="_blank">' . esc_html__( 'MXRoute Mailer Wiki', 'mxroute-mailer' ) . '</a></p>'
			. '<p><a href="https://mxroute.com" target="_blank">' . esc_html__( 'MXRoute Documentation', 'mxroute-mailer' ) . '</a></p>'
		);
	}

	/**
	 * Add help tabs for the Log Detail page.
	 *
	 * @return void
	 */
	public function add_log_view_help_tabs() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'mxroute-log-detail-overview',
				'title'   => __( 'Overview', 'mxroute-mailer' ),
				'content' => '<p>' . esc_html__( 'This page shows the full details for a single email log entry, including the message content, API request payload, and API response.', 'mxroute-mailer' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'mxroute-log-detail-fields',
				'title'   => __( 'Fields', 'mxroute-mailer' ),
				'content' => '<ul>'
					. '<li><strong>' . esc_html__( 'Timestamp:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'When the email was sent.', 'mxroute-mailer' ) . '</li>'
					. '<li><strong>' . esc_html__( 'Status:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'Whether the API accepted the email (Sent) or rejected it (Fail).', 'mxroute-mailer' ) . '</li>'
					. '<li><strong>' . esc_html__( 'From / To / Subject:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'The email headers as passed to the API.', 'mxroute-mailer' ) . '</li>'
					. '<li><strong>' . esc_html__( 'Message:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'The email body content.', 'mxroute-mailer' ) . '</li>'
					. '<li><strong>' . esc_html__( 'API Request:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'The JSON payload sent to the MXRoute API (password excluded).', 'mxroute-mailer' ) . '</li>'
					. '<li><strong>' . esc_html__( 'API Response:', 'mxroute-mailer' ) . '</strong> ' . esc_html__( 'The raw JSON response from the MXRoute API.', 'mxroute-mailer' ) . '</li>'
					. '</ul>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:', 'mxroute-mailer' ) . '</strong></p>'
			. '<p><a href="https://github.com/richardkentgates/mxroute-mailer/wiki" target="_blank">' . esc_html__( 'MXRoute Mailer Wiki', 'mxroute-mailer' ) . '</a></p>'
			. '<p><a href="https://mxroute.com" target="_blank">' . esc_html__( 'MXRoute Documentation', 'mxroute-mailer' ) . '</a></p>'
		);
	}
}

new MXRoute_Settings();
