<?php
/**
 * Core Plugin class.
 *
 * @package TechZappAIWriter
 */

declare( strict_types=1 );

namespace TechZappAIWriter;

/**
 * Class Plugin
 *
 * Singleton that bootstraps all plugin components.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Admin handler.
	 *
	 * @var Admin
	 */
	private Admin $admin;

	/**
	 * REST API handler.
	 *
	 * @var Rest_Api
	 */
	private Rest_Api $rest_api;

	/**
	 * Settings handler.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Updater.
	 *
	 * @var Updater
	 */
	private Updater $updater;

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Get or create the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Instantiate all component classes.
	 */
	private function load_dependencies(): void {
		require_once TZAW_PLUGIN_DIR . 'includes/class-admin.php';
		require_once TZAW_PLUGIN_DIR . 'includes/class-settings.php';
		require_once TZAW_PLUGIN_DIR . 'includes/class-rest-api.php';
		require_once TZAW_PLUGIN_DIR . 'includes/class-ai-client.php';
		require_once TZAW_PLUGIN_DIR . 'includes/class-trending.php';
		require_once TZAW_PLUGIN_DIR . 'includes/class-draft-saver.php';
		require_once TZAW_PLUGIN_DIR . 'includes/class-updater.php';

		$this->settings = new Settings();
		$this->admin    = new Admin( $this->settings );
		$this->rest_api = new Rest_Api( $this->settings );
		$this->updater  = new Updater();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function init_hooks(): void {
		add_action( 'admin_menu', [ $this->admin, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this->admin, 'enqueue_assets' ] );
		add_action( 'rest_api_init', [ $this->rest_api, 'register_routes' ] );
	}
}
