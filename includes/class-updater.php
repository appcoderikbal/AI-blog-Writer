<?php
/**
 * Plugin Updater — checks GitHub Releases for new versions.
 *
 * The WordPress update system is hooked so that when a new tag is pushed
 * to the GitHub repository and a release is created, WordPress admins see
 * the standard "Update Available" notice and can update in one click.
 *
 * Workflow:
 *  1. Bump version in techzapp-ai-writer.php, package.json, and update-info.json
 *  2. git commit + git tag v1.x.x + git push --tags
 *  3. GitHub Actions builds the zip and creates the release automatically
 *  4. WordPress sites pick up the update within 12 hours (or immediately on "Check Again")
 *
 * @package TechZappAIWriter
 */

declare( strict_types=1 );

namespace TechZappAIWriter;

/**
 * Class Updater
 */
class Updater {

	/**
	 * Transient key used to cache the remote update-info.json payload.
	 */
	private const CACHE_KEY = 'tzaw_remote_update_info';

	/**
	 * How long to cache the remote payload (12 hours).
	 */
	private const CACHE_DURATION = 43200;

	/**
	 * Constructor — registers WordPress update hooks only when GitHub is configured.
	 */
	public function __construct() {
		if ( ! defined( 'TZAW_GITHUB_REPO' ) || empty( TZAW_GITHUB_REPO ) ) {
			return; // No repo configured — skip update checks.
		}

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ $this, 'clear_cache' ], 10, 2 );

		// Add "View version info" link on the Plugins page.
		add_filter(
			'plugin_action_links_' . TZAW_PLUGIN_BASENAME,
			[ $this, 'plugin_action_links' ]
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Update Check
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Inject update data into WordPress's plugin update transient.
	 *
	 * @param \stdClass $transient The current update_plugins transient.
	 * @return \stdClass
	 */
	public function check_for_update( \stdClass $transient ): \stdClass {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$info = $this->get_remote_info();

		if ( ! $info || empty( $info['version'] ) ) {
			return $transient;
		}

		$installed = $transient->checked[ TZAW_PLUGIN_BASENAME ] ?? TZAW_VERSION;

		if ( version_compare( $info['version'], $installed, '>' ) ) {
			$transient->response[ TZAW_PLUGIN_BASENAME ] = (object) [
				'id'          => 'techzapp-ai-writer',
				'slug'        => 'techzapp-ai-writer',
				'plugin'      => TZAW_PLUGIN_BASENAME,
				'new_version' => $info['version'],
				'url'         => 'https://github.com/' . TZAW_GITHUB_REPO,
				'package'     => $info['download_url'] ?? '',
				'requires'    => $info['requires'] ?? '6.0',
				'tested'      => $info['tested'] ?? '6.7',
				'requires_php'=> $info['requires_php'] ?? '8.0',
				'icons'       => [],
				'banners'     => [],
				'banners_rtl' => [],
			];
		} else {
			// Explicitly mark as no update so WP doesn't show stale notices.
			$transient->no_update[ TZAW_PLUGIN_BASENAME ] = (object) [
				'id'          => 'techzapp-ai-writer',
				'slug'        => 'techzapp-ai-writer',
				'plugin'      => TZAW_PLUGIN_BASENAME,
				'new_version' => $info['version'],
				'url'         => 'https://github.com/' . TZAW_GITHUB_REPO,
				'package'     => '',
				'requires'    => $info['requires'] ?? '6.0',
				'tested'      => $info['tested'] ?? '6.7',
				'requires_php'=> $info['requires_php'] ?? '8.0',
			];
		}

		return $transient;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Plugin Info Modal
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Populate the plugin information modal shown when "View details" is clicked.
	 *
	 * @param false|\stdClass|\WP_Error $result The current result.
	 * @param string                    $action The API action.
	 * @param \stdClass                 $args   Request arguments.
	 * @return false|\stdClass|\WP_Error
	 */
	public function plugin_info( false|\stdClass|\WP_Error $result, string $action, \stdClass $args ): false|\stdClass|\WP_Error {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ( $args->slug ?? '' ) !== 'techzapp-ai-writer' ) {
			return $result;
		}

		$info = $this->get_remote_info();

		if ( ! $info ) {
			return $result;
		}

		return (object) [
			'name'          => 'TechZapp AI Writer',
			'slug'          => 'techzapp-ai-writer',
			'version'       => $info['version'] ?? TZAW_VERSION,
			'author'        => '<a href="https://techzapp.com">TechZapp</a>',
			'author_profile'=> 'https://techzapp.com',
			'homepage'      => 'https://github.com/' . TZAW_GITHUB_REPO,
			'requires'      => $info['requires'] ?? '6.0',
			'tested'        => $info['tested'] ?? '6.7',
			'requires_php'  => $info['requires_php'] ?? '8.0',
			'last_updated'  => $info['last_updated'] ?? '',
			'download_link' => $info['download_url'] ?? '',
			'sections'      => array_map( 'wp_kses_post', (array) ( $info['sections'] ?? [] ) ),
			'banners'       => [],
		];
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Cache Management
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Clear the update cache after a successful plugin update.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array<string, mixed> $options Hook extra options.
	 */
	public function clear_cache( \WP_Upgrader $upgrader, array $options ): void {
		if (
			'update' === ( $options['action'] ?? '' ) &&
			'plugin' === ( $options['type'] ?? '' ) &&
			in_array( TZAW_PLUGIN_BASENAME, (array) ( $options['plugins'] ?? [] ), true )
		) {
			delete_transient( self::CACHE_KEY );
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Plugin Action Links
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Add useful links to the Plugins list table row.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function plugin_action_links( array $links ): array {
		$info = $this->get_remote_info();

		if ( $info && ! empty( $info['version'] ) ) {
			$remote  = $info['version'];
			$current = TZAW_VERSION;

			if ( version_compare( $remote, $current, '>' ) ) {
				$links['update'] = sprintf(
					'<a href="%s" style="color:#d63638;font-weight:600;">⬆ Update to v%s</a>',
					esc_url( network_admin_url( 'plugins.php?plugin_status=upgrade' ) ),
					esc_html( $remote )
				);
			} else {
				$links['version'] = '<span style="color:#46b450;">✓ Up to date</span>';
			}
		}

		// Settings link.
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=tzaw-settings' ) ) . '">Settings</a>'
		);

		return $links;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Remote Fetch
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Fetch and cache the remote update-info.json from GitHub.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_remote_info(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			TZAW_UPDATE_URL,
			[
				'timeout'    => 15,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			return null;
		}

		set_transient( self::CACHE_KEY, $data, self::CACHE_DURATION );

		return $data;
	}
}
