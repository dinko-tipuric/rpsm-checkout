<?php
/**
 * GitHub Updater for RPSM plugins.
 *
 * Checks GitHub Releases for new versions and injects updates into the
 * WordPress plugin update system. Supports private repositories via PAT.
 *
 * Usage:
 *   1. Add header to your main plugin file:
 *      * GitHub Plugin URI: owner/repo-name
 *
 *   2. In your plugin bootstrap:
 *      require_once __DIR__ . '/includes/class-rpsm-github-updater.php';
 *      new RPSM_GitHub_Updater( __FILE__ );
 *
 *   3. Define PAT token in wp-config.php:
 *      define( 'RPSM_GITHUB_TOKEN', 'ghp_xxxx...' );
 *
 * @package RpsmPlugins
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Allow multiple plugins to include this file without conflict.
if ( class_exists( 'RPSM_GitHub_Updater_v2' ) ) {
	return;
}

/**
 * Class RPSM_GitHub_Updater_v2
 */
class RPSM_GitHub_Updater_v2 {

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Plugin basename (e.g. "rpsm-alati/rpsm-alati.php").
	 *
	 * @var string
	 */
	private string $basename;

	/**
	 * GitHub owner/repo (e.g. "dinko-tipuric/rpsm-alati").
	 *
	 * @var string
	 */
	private string $github_repo;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Plugin slug (directory name).
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Plugin display name.
	 *
	 * @var string
	 */
	private string $plugin_name;

	/**
	 * Cached GitHub release data.
	 *
	 * @var object|null
	 */
	private ?object $github_response = null;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file (__FILE__).
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->basename    = plugin_basename( $plugin_file );
		$this->slug        = dirname( $this->basename );

		$headers = get_file_data( $plugin_file, [
			'Version'           => 'Version',
			'GitHubPluginURI'   => 'GitHub Plugin URI',
			'PluginName'        => 'Plugin Name',
		] );

		$this->version     = $headers['Version'] ?? '0.0.0.0';
		$this->github_repo = trim( $headers['GitHubPluginURI'] ?? '' );
		$this->plugin_name = $headers['PluginName'] ?? $this->slug;

		if ( empty( $this->github_repo ) ) {
			return; // No GitHub repo configured — do nothing.
		}

		add_filter( 'site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api',                   [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_post_install',         [ $this, 'post_install' ], 10, 3 );
		add_filter( 'http_request_args',             [ $this, 'add_auth_header' ], 10, 2 );
		add_action( 'delete_site_transient_update_plugins', [ $this, 'clear_cache' ] );
	}

	// =========================================================================
	// GitHub API
	// =========================================================================

	/**
	 * Get the PAT token for GitHub API authentication.
	 *
	 * Checks (in order):
	 *   1. RPSM_GITHUB_TOKEN constant (wp-config.php)
	 *   2. rpsm_github_token option (database)
	 *
	 * @return string Token or empty string.
	 */
	private function get_token(): string {
		if ( defined( 'RPSM_GITHUB_TOKEN' ) && RPSM_GITHUB_TOKEN ) {
			return RPSM_GITHUB_TOKEN;
		}
		return (string) get_option( 'rpsm_github_token', '' );
	}

	/**
	 * Fetch the latest release data from GitHub API.
	 *
	 * Caches in a transient for 5 minutes to keep updates responsive.
	 *
	 * @return object|null Release data or null on failure.
	 */
	private function get_github_release(): ?object {
		if ( null !== $this->github_response ) {
			return $this->github_response;
		}

		$transient_key = 'rpsm_ghu_' . md5( $this->github_repo );
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			$this->github_response = $cached;
			return $this->github_response;
		}

		$url  = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
		$args = [
			'headers' => [
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'RPSM-GitHub-Updater/1.0',
			],
			'timeout' => 10,
		];

		$token = $this->get_token();
		if ( $token ) {
			$args['headers']['Authorization'] = "Bearer {$token}";
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Cache failure for 5 minutes to avoid hammering API.
			set_transient( $transient_key, (object) [ 'tag_name' => '' ], 5 * MINUTE_IN_SECONDS );
			$this->github_response = null;
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! $body || empty( $body->tag_name ) ) {
			$this->github_response = null;
			return null;
		}

		set_transient( $transient_key, $body, 5 * MINUTE_IN_SECONDS );
		$this->github_response = $body;
		return $this->github_response;
	}

	/**
	 * Normalize a GitHub tag to a version string.
	 *
	 * Strips leading "v" prefix: "v1.3.2.0" → "1.3.2.0".
	 *
	 * @param string $tag
	 * @return string
	 */
	private function tag_to_version( string $tag ): string {
		return ltrim( $tag, 'vV' );
	}

	/**
	 * Build icon URLs for the WordPress plugin list.
	 *
	 * Serves icons from the local plugin installation via plugins_url().
	 * This works regardless of whether the GitHub repo is private or public.
	 *
	 * @return array Icon URLs keyed by '1x' and '2x', or empty array.
	 */
	private function get_icon_urls(): array {
		$icons      = [];
		$plugin_dir = dirname( $this->plugin_file );

		if ( file_exists( $plugin_dir . '/assets/icon-128x128.png' ) ) {
			$icons['1x'] = plugins_url( 'assets/icon-128x128.png', $this->plugin_file );
		}
		if ( file_exists( $plugin_dir . '/assets/icon-256x256.png' ) ) {
			$icons['2x'] = plugins_url( 'assets/icon-256x256.png', $this->plugin_file );
		}
		if ( file_exists( $plugin_dir . '/assets/icon.svg' ) ) {
			$icons['svg'] = plugins_url( 'assets/icon.svg', $this->plugin_file );
		}

		return $icons;
	}

	// =========================================================================
	// WordPress hooks
	// =========================================================================

	/**
	 * Inject update data into the WordPress update transient.
	 *
	 * @param object $transient
	 * @return object
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_github_release();
		if ( ! $release || empty( $release->tag_name ) ) {
			return $transient;
		}

		$remote_version = $this->tag_to_version( $release->tag_name );

		if ( ! version_compare( $remote_version, $this->version, '>' ) ) {
			// No update available — report as "no_update" so WP knows we checked.
			$transient->no_update[ $this->basename ] = (object) [
				'id'            => $this->basename,
				'slug'          => $this->slug,
				'plugin'        => $this->basename,
				'new_version'   => $this->version,
				'package'       => '',
			];
			return $transient;
		}

		// Prefer the uploaded ZIP asset over the git zipball.
		// The git zipball reflects whatever is currently in the repo — if files were not
		// pushed to GitHub, the downloaded archive still contains the old version number.
		// Our manually built and uploaded ZIP always has the correct version.
		$download_url = "https://api.github.com/repos/{$this->github_repo}/zipball/{$release->tag_name}";
		if ( ! empty( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( '.zip' === substr( $asset->name ?? '', -4 ) ) {
					// Route through the API assets endpoint — works for both public and private repos.
					// add_auth_header() injects Bearer + Accept: application/octet-stream for this URL.
					$download_url = "https://api.github.com/repos/{$this->github_repo}/releases/assets/{$asset->id}";
					break;
				}
			}
		}

		$transient->response[ $this->basename ] = (object) [
			'id'            => $this->basename,
			'slug'          => $this->slug,
			'plugin'        => $this->basename,
			'new_version'   => $remote_version,
			'package'       => $download_url,
			'url'           => "https://github.com/{$this->github_repo}",
			'icons'         => $this->get_icon_urls(),
			'banners'       => [],
			'tested'        => '',
			'requires_php'  => '7.4',
		];

		return $transient;
	}

	/**
	 * Provide plugin info for the "View details" modal in WP admin.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ( $args->slug ?? '' ) !== $this->slug ) {
			return $result;
		}

		$release = $this->get_github_release();
		if ( ! $release || empty( $release->tag_name ) ) {
			return $result;
		}

		$remote_version = $this->tag_to_version( $release->tag_name );

		return (object) [
			'name'            => $this->plugin_name,
			'slug'            => $this->slug,
			'version'         => $remote_version,
			'author'          => 'Business Labs d.o.o.',
			'homepage'        => "https://github.com/{$this->github_repo}",
			'requires_php'    => '7.4',
			'download_link'   => "https://api.github.com/repos/{$this->github_repo}/zipball/{$release->tag_name}",
			'sections'        => [
				'description' => $this->plugin_name,
				'changelog'   => $this->build_changelog(),
			],
		];
	}

	/**
	 * Fetch all releases from GitHub and build an HTML changelog.
	 *
	 * Shows each release with its version, publish date and release notes —
	 * newest first. Useful when a site skips multiple versions.
	 *
	 * @return string HTML changelog.
	 */
	private function build_changelog(): string {
		$releases = $this->get_all_releases();

		if ( empty( $releases ) ) {
			return '<p>Nema podataka o verzijama.</p>';
		}

		$html = '';
		foreach ( $releases as $rel ) {
			$version = esc_html( $this->tag_to_version( $rel->tag_name ?? '' ) );
			$date    = ! empty( $rel->published_at )
				? date_i18n( get_option( 'date_format' ), strtotime( $rel->published_at ) )
				: '';
			$body    = ! empty( $rel->body )
				? nl2br( esc_html( $rel->body ) )
				: '<em>—</em>';

			$html .= '<h4 style="margin:14px 0 4px;padding:0;">'
				. $version
				. ( $date ? ' &mdash; <span style="font-weight:400;color:#666;">' . esc_html( $date ) . '</span>' : '' )
				. '</h4>';
			$html .= '<div style="margin-bottom:10px;line-height:1.6;">' . $body . '</div>';
			$html .= '<hr style="border:0;border-top:1px solid #eee;margin:0 0 10px;">';
		}

		return $html;
	}

	/**
	 * Fetch all releases from the GitHub API.
	 *
	 * Cached for 5 minutes. Returns releases newest-first (GitHub default).
	 *
	 * @return array Array of release objects, or empty array on failure.
	 */
	private function get_all_releases(): array {
		$transient_key = 'rpsm_ghu_all_' . md5( $this->github_repo );
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : [];
		}

		$url  = "https://api.github.com/repos/{$this->github_repo}/releases?per_page=50";
		$args = [
			'headers' => [
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'RPSM-GitHub-Updater/1.0',
			],
			'timeout' => 10,
		];

		$token = $this->get_token();
		if ( $token ) {
			$args['headers']['Authorization'] = "Bearer {$token}";
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $transient_key, [], 5 * MINUTE_IN_SECONDS );
			return [];
		}

		$releases = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_array( $releases ) ) {
			set_transient( $transient_key, [], 5 * MINUTE_IN_SECONDS );
			return [];
		}

		set_transient( $transient_key, $releases, 5 * MINUTE_IN_SECONDS );
		return $releases;
	}

	/**
	 * Add Authorization header to GitHub API download requests.
	 *
	 * WP's upgrader calls download_url() which goes through WP_Http.
	 * We intercept requests to api.github.com and inject the Bearer token.
	 *
	 * @param array  $args HTTP request arguments.
	 * @param string $url  Request URL.
	 * @return array
	 */
	public function add_auth_header( array $args, string $url ): array {
		if ( false === strpos( $url, "api.github.com/repos/{$this->github_repo}" ) ) {
			return $args;
		}

		$token = $this->get_token();
		if ( $token ) {
			$args['headers']['Authorization'] = "Bearer {$token}";
		}

		// Asset downloads need octet-stream; API calls need JSON.
		// Without octet-stream, GitHub returns asset metadata JSON instead of the file.
		if ( false !== strpos( $url, '/releases/assets/' ) ) {
			$args['headers']['Accept'] = 'application/octet-stream';
		} else {
			$args['headers']['Accept'] = 'application/vnd.github+json';
		}

		return $args;
	}

	/**
	 * Fix directory name after WP extracts the GitHub zipball.
	 *
	 * GitHub zipball extracts to "owner-repo-hash/" — we rename it
	 * to the expected plugin slug directory.
	 *
	 * @param bool  $response   Install response.
	 * @param array $hook_extra Extra info about the install.
	 * @param array $result     Installation result data.
	 * @return bool|WP_Error
	 */
	public function post_install( $response, $hook_extra, $result ) {
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $response;
		}

		global $wp_filesystem;

		$install_dir = $result['destination'];
		$proper_dir  = WP_PLUGIN_DIR . '/' . $this->slug;

		// If the extracted directory doesn't match our slug, rename it.
		if ( $install_dir !== $proper_dir ) {
			$wp_filesystem->move( $install_dir, $proper_dir );
			$result['destination']      = $proper_dir;
			$result['destination_name'] = $this->slug;
		}

		// Re-activate plugin if it was active.
		if ( is_plugin_active( $this->basename ) ) {
			activate_plugin( $this->basename );
		}

		return $response;
	}

	/**
	 * Force-clear the update transient cache for this repo.
	 *
	 * Call after pushing a new release to immediately see updates.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( 'rpsm_ghu_' . md5( $this->github_repo ) );
		delete_transient( 'rpsm_ghu_all_' . md5( $this->github_repo ) );
		$this->github_response = null;
	}
}
