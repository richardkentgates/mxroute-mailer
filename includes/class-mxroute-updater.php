<?php
/**
 * Apt-server-based automatic updater for MXRoute Mailer.
 *
 * Hooks into the WordPress core plugin-update pipeline so MXRoute Mailer
 * appears in Dashboard -> Updates exactly like a wordpress.org-hosted plugin.
 *
 * How it works:
 *   1. Every 12 hours (matching WP's own update check interval) this class
 *      fetches metadata.json from the apt server.
 *   2. If the remote version is newer than MXROUTE_MAILER_VERSION it injects a
 *      plugin_information object into the core update transient.
 *   3. WordPress then offers the update in the normal UI and can install it
 *      with a single click — no manual download required.
 *   4. After WordPress downloads the zip, a filter renames the extracted
 *      folder to the correct slug (mxroute-mailer) so the plugin path stays
 *      stable.
 *
 * A "Check for Updates" action link is added to the Plugins list page so
 * admins can force an immediate check without waiting for the next cron cycle.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MXRoute_Updater
 */
class MXRoute_Updater {

	/** URL of the apt server metadata.json. */
	private const METADATA_URL = 'https://apt.richardkentgates.com/mxroute-mailer/metadata.json';

	/** WordPress option / transient key used to cache the remote metadata. */
	private const TRANSIENT = 'mxroute_remote_metadata';

	/** How long to cache the remote response (seconds). Mirrors WP's 12-hour cycle. */
	private const CACHE_TTL = 43200;

	/** Basename of this plugin file, e.g. "mxroute-mailer/mxroute-mailer.php". */
	private $plugin_basename;

	/** Full path to the main plugin file. */
	private $file;

	/**
	 * Boot the updater.
	 */
	public static function init(): void {
		$instance = new self();
		$instance->hooks();
	}

	/**
	 * Create an instance for testing.
	 *
	 * @param string $file Optional plugin file path override.
	 * @return self
	 */
	public static function create_for_test( string $file = '' ): self {
		$instance = new self();
		if ( $file ) {
			$instance->file          = $file;
			$instance->plugin_basename = plugin_basename( $file );
		}
		return $instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->file          = __FILE__;
		$this->plugin_basename = plugin_basename( __FILE__ );
	}

	/**
	 * Register all WordPress hooks.
	 */
	private function hooks(): void {
		// Skip update-check hooks under WP-CLI — they trigger wp_remote_get()
		// which blocks the process when the apt server is slow or unreachable.
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
			add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
			add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
		}

		add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'action_links' ) );
		add_action( 'admin_init', array( $this, 'handle_manual_check' ) );
	}

	// -------------------------------------------------------------------------
	// Apt server metadata
	// -------------------------------------------------------------------------

	/**
	 * Fetch the latest metadata from the apt server, with caching.
	 *
	 * @param bool $force_refresh Bypass the transient cache when true.
	 * @return object|null  Decoded metadata object, or null on failure.
	 */
	private function get_metadata( bool $force_refresh = false ): ?object {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::TRANSIENT );
			if ( false !== $cached ) {
				return $cached ?: null;
			}
		}

		$response = wp_remote_get(
			self::METADATA_URL,
			array(
				'timeout'    => 10,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; MXRouteMailer/' . MXROUTE_MAILER_VERSION,
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_transient( self::TRANSIENT, '', self::CACHE_TTL );
			return null;
		}

		$metadata = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $metadata->version ) || empty( $metadata->download_url ) ) {
			set_transient( self::TRANSIENT, '', self::CACHE_TTL );
			return null;
		}

		if ( empty( $metadata->requires ) || ! is_object( $metadata->requires ) ) {
			$metadata->requires = (object) array(
				'php'       => '7.3',
				'wordpress' => '5.0',
			);
		}
		if ( empty( $metadata->requires->php ) ) {
			$metadata->requires->php = '7.3';
		}
		if ( empty( $metadata->requires->wordpress ) ) {
			$metadata->requires->wordpress = '5.0';
		}

		set_transient( self::TRANSIENT, $metadata, self::CACHE_TTL );
		return $metadata;
	}

	// -------------------------------------------------------------------------
	// WordPress update pipeline hooks
	// -------------------------------------------------------------------------

	/**
	 * Inject MXRoute Mailer update data into the WP plugin-update transient.
	 *
	 * @param  \stdClass|false $transient The update_plugins site transient.
	 * @return \stdClass|false           Modified transient, or false to let WP handle it.
	 */
	public function inject_update( $transient ) {
		$metadata = $this->get_metadata();
		if ( null === $metadata ) {
			return false;
		}

		$remote_version = $metadata->version;

		if ( version_compare( $remote_version, MXROUTE_MAILER_VERSION, '>' ) ) {
			if ( ! is_object( $transient ) ) {
				$transient = new \stdClass();
			}
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}

			$transient->response[ $this->plugin_basename ] = (object) array(
				'id'            => 'mxroute-mailer/apt-server',
				'slug'          => 'mxroute-mailer',
				'plugin'        => $this->plugin_basename,
				'new_version'   => $remote_version,
				'url'           => 'https://github.com/richardkentgates/mxroute-mailer',
				'package'       => $metadata->download_url,
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => $metadata->requires->wordpress ?? '5.0',
				'requires'      => $metadata->requires->wordpress ?? '5.0',
				'requires_php'  => $metadata->requires->php ?? '7.3',
				'compatibility' => new \stdClass(),
			);

			return $transient;
		}

		return false;
	}

	/**
	 * Provide plugin information for the "View version x.x.x details" thickbox.
	 *
	 * @param false|object $result  Existing result (false if unhandled).
	 * @param string       $action  Current API action.
	 * @param object       $args    API request arguments.
	 * @return false|object         Plugin info object, or false to let WP handle it.
	 */
	public function plugin_info( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || 'mxroute-mailer' !== $args->slug ) {
			return $result;
		}

		$metadata = $this->get_metadata();
		if ( null === $metadata ) {
			return $result;
		}

		$remote_version = $metadata->version;
		$requires_php   = $metadata->requires->php ?? '7.3';
		$requires_wp    = $metadata->requires->wordpress ?? '5.0';

		$plugin_data = get_plugin_data( $this->file, false, false );

		return (object) array(
			'name'              => $plugin_data['Name'] ?? 'MXRoute Mailer',
			'slug'              => 'mxroute-mailer',
			'version'           => $remote_version,
			'author'            => '<a href="https://github.com/richardkentgates">Richard Kent Gates</a>',
			'author_profile'    => 'https://github.com/richardkentgates',
			'homepage'          => 'https://mxroute.com',
			'requires'          => $requires_wp,
			'requires_php'      => $requires_php,
			'download_link'     => $metadata->download_url,
			'trunk'             => $metadata->download_url,
			'last_updated'      => '',
			'sections'          => array(
				'description' => $plugin_data['Description'] ?? 'Sends WordPress email through MXRoute HTTP API.',
				'changelog'   => $this->build_changelog_section(),
			),
			'banners'           => array(),
			'icons'             => array(),
		);
	}

	/**
	 * Fix the plugin folder name after WordPress extracts the zip.
	 *
	 * @param  string      $source        Extracted folder path.
	 * @param  string      $remote_source Temp folder containing the zip.
	 * @param  WP_Upgrader $upgrader      Upgrader instance.
	 * @param  array       $hook_extra    Extra context.
	 * @return string                     Corrected source path.
	 */
	public function fix_source_dir( string $source, string $remote_source, $upgrader, array $hook_extra ): string {
		global $wp_filesystem;

		if ( empty( $hook_extra['plugin'] ) || $this->plugin_basename !== $hook_extra['plugin'] ) {
			return $source;
		}

		$correct = trailingslashit( $remote_source ) . 'mxroute-mailer/';
		if ( $source === $correct ) {
			return $source;
		}

		if ( $wp_filesystem->move( $source, $correct ) ) {
			return $correct;
		}

		return $source;
	}

	// -------------------------------------------------------------------------
	// Manual "Check for Updates" link
	// -------------------------------------------------------------------------

	/**
	 * Add a "Check for Updates" action link on the Plugins page.
	 *
	 * @param  array $links Existing action links.
	 * @return array        Modified action links.
	 */
	public function action_links( array $links ): array {
		$check_url = wp_nonce_url(
			add_query_arg( array( 'mxr_check_update' => '1' ), admin_url( 'plugins.php' ) ),
			'mxr_check_update'
		);

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $check_url ),
			esc_html__( 'Check for Updates', 'mxroute-mailer' )
		);

		return $links;
	}

	/**
	 * Handle the manual update check request.
	 */
	public function handle_manual_check(): void {
		if ( empty( $_GET['mxr_check_update'] ) ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to update plugins.', 'mxroute-mailer' ) );
		}

		check_admin_referer( 'mxr_check_update' );

		delete_transient( self::TRANSIENT );
		delete_site_transient( 'update_plugins' );

		$metadata = $this->get_metadata( true );

		if ( $metadata ) {
			$remote_version = $metadata->version;
			if ( version_compare( $remote_version, MXROUTE_MAILER_VERSION, '>' ) ) {
				$notice = urlencode(
					sprintf(
						/* translators: %s = new version number */
						__( 'MXRoute Mailer %s is available. Check the Updates page to install.', 'mxroute-mailer' ),
						$remote_version
					)
				);
				$type = 'updated';
			} else {
				$notice = urlencode( __( 'MXRoute Mailer is up to date.', 'mxroute-mailer' ) );
				$type   = 'updated';
			}
		} else {
			$notice = urlencode( __( 'Could not contact the update server to check for updates.', 'mxroute-mailer' ) );
			$type   = 'error';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'mxr_notice'      => $notice,
					'mxr_notice_type' => $type,
					'mxr_check_update' => false,
					'_wpnonce'        => false,
				),
				admin_url( 'plugins.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a minimal changelog section from the CHANGELOG.md file.
	 *
	 * @return string HTML changelog or empty string.
	 */
	private function build_changelog_section(): string {
		$file = dirname( __FILE__, 2 ) . '/CHANGELOG.md';
		if ( ! file_exists( $file ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw  = file_get_contents( $file );
		$html = '';

		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( str_starts_with( $line, '## ' ) ) {
				$html .= '<h4>' . esc_html( substr( $line, 3 ) ) . '</h4>';
			} elseif ( str_starts_with( $line, '- ' ) ) {
				$html .= '<li>' . esc_html( substr( $line, 2 ) ) . '</li>';
			}
		}

		return $html;
	}
}
