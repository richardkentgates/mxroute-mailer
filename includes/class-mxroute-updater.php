<?php
/**
 * GitHub-based auto-updater for MXRoute Mailer.
 *
 * Checks GitHub releases for new versions and integrates with
 * the WordPress plugin update system.
 *
 * @package MXRoute_Mailer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles automatic updates from GitHub releases.
 */
class MXRoute_Updater {

	/**
	 * GitHub repository slug.
	 *
	 * @var string
	 */
	private $repo;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Main plugin file path.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * GitHub API cache.
	 *
	 * @var array|null
	 */
	private $github_data;

	/**
	 * Constructor.
	 *
	 * @param string $file    Main plugin file path.
	 * @param string $repo    GitHub repository slug (owner/repo).
	 * @param string $version Current plugin version.
	 */
	public function __construct( $file, $repo, $version ) {
		$this->file    = $file;
		$this->repo    = $repo;
		$this->version = $version;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_zip_folder' ), 10, 4 );
	}

	/**
	 * Get release data from GitHub.
	 *
	 * @return array|null Release data or null on failure.
	 */
	private function get_github_data() {
		if ( null !== $this->github_data ) {
			return $this->github_data;
		}

		$endpoint = 'https://api.github.com/repos/' . $this->repo . '/releases/latest';
		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/vnd.github.v3+json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$this->github_data = json_decode( wp_remote_retrieve_body( $response ), true );
		return $this->github_data;
	}

	/**
	 * Check for plugin updates.
	 *
	 * @param object $transient_data Site transient data.
	 * @return object Modified transient data.
	 */
	public function check_update( $transient_data ) {
		if ( ! is_object( $transient_data ) ) {
			return $transient_data;
		}

		$release = $this->get_github_data();
		if ( null === $release || empty( $release['tag_name'] ) ) {
			return $transient_data;
		}

		$remote_version = ltrim( $release['tag_name'], 'v' );
		if ( version_compare( $remote_version, $this->version, '<=' ) ) {
			return $transient_data;
		}

		$zip_url = null;
		foreach ( $release['assets'] as $asset ) {
			if ( ! empty( $asset['name'] ) && 'zip' === pathinfo( $asset['name'], PATHINFO_EXTENSION ) ) {
				$zip_url = $asset['browser_download_url'];
				break;
			}
		}

		if ( ! $zip_url ) {
			$zip_url = 'https://github.com/' . $this->repo . '/archive/refs/tags/' . $release['tag_name'] . '.zip';
		}

		$plugin_data = get_plugin_data( $this->file, false, false );

		$transient_data->response[ plugin_basename( $this->file ) ] = (object) array(
			'slug'        => basename( dirname( $this->file ) ),
			'new_version' => $remote_version,
			'url'         => $release['html_url'] ?? 'https://github.com/' . $this->repo,
			'package'     => $zip_url,
			'name'        => $plugin_data['Name'] ?? 'MXRoute Mailer',
			'sections'    => array(
				'description' => $release['body'] ?? '',
			),
			'banners'     => array(),
		);

		return $transient_data;
	}

	/**
	 * Provide update information to the plugins API.
	 *
	 * @param mixed  $result  Default result.
	 * @param string $action  API action.
	 * @param object $args    API arguments.
	 * @return mixed Plugin data or default result.
	 */
	public function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || basename( dirname( $this->file ) ) !== $args->slug ) {
			return $result;
		}

		$release = $this->get_github_data();
		if ( null === $release ) {
			return $result;
		}

		$plugin_data = get_plugin_data( $this->file, false, false );

		return (object) array(
			'name'          => $plugin_data['Name'] ?? 'MXRoute Mailer',
			'slug'          => basename( dirname( $this->file ) ),
			'version'       => ltrim( $release['tag_name'], 'v' ),
			'author'        => $plugin_data['Author'] ?? 'MXRoute',
			'author_profile' => 'https://github.com/' . explode( '/', $this->repo )[0],
			'repository'    => 'https://github.com/' . $this->repo,
			'requires'      => '5.0',
			'tested'        => '7.0',
			'requires_php'  => '7.3',
			'sections'      => array(
				'description'  => $release['body'] ?? $plugin_data['Description'] ?? '',
				'changelog'    => $release['body'] ?? '',
			),
			'download_link' => $this->get_zip_url( $release ),
		);
	}

	/**
	 * Get the download URL for the latest release zip.
	 *
	 * @param array $release GitHub release data.
	 * @return string Download URL.
	 */
	private function get_zip_url( $release ) {
		if ( ! empty( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( ! empty( $asset['name'] ) && 'zip' === pathinfo( $asset['name'], PATHINFO_EXTENSION ) ) {
					return $asset['browser_download_url'];
				}
			}
		}

		return 'https://github.com/' . $this->repo . '/archive/refs/tags/' . $release['tag_name'] . '.zip';
	}

	/**
	 * Fix the zip folder name to match the plugin directory.
	 *
	 * WordPress expects the extracted folder to match the plugin slug.
	 *
	 * @param string       $source        The source directory path.
	 * @param string       $remote_source The remote source directory path.
	 * @param object       $updater       The updater instance.
	 * @param array        $args          Updater arguments.
	 * @return string|WP_Error Modified source path or error.
	 */
	public function fix_zip_folder( $source, $remote_source, $updater = null, $args = array() ) {
		$desired_folder = basename( dirname( $this->file ) );

		$source_base = trailingslashit( $source );
		$expected    = $source_base . $desired_folder;

		if ( is_dir( $expected ) ) {
			return $expected;
		}

		$files = glob( $source_base . '*' );
		if ( ! empty( $files ) && 1 === count( $files ) && is_dir( $files[0] ) ) {
			$actual_folder = basename( $files[0] );
			if ( $actual_folder !== $desired_folder ) {
				$new_source = trailingslashit( $source ) . $desired_folder;
				if ( @rename( $files[0], $new_source ) ) {
					return $new_source;
				}
			}
		}

		return $source;
	}
}
