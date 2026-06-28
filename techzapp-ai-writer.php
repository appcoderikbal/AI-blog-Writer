<?php
/**
 * Plugin Name:       TechZapp AI Writer
 * Plugin URI:        https://techzapp.com/plugins/ai-writer
 * Description:       Generate high-quality technology blog posts using AI. Supports OpenAI, Groq, and Google Gemini. Discover trending topics, generate complete articles, edit, and save as WordPress drafts.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Tested up to:      6.7
 * Requires PHP:      8.0
 * Author:            TechZapp
 * Author URI:        https://techzapp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       techzapp-ai-writer
 * Domain Path:       /languages
 *
 * @package TechZappAIWriter
 */

declare( strict_types=1 );

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Plugin Constants ─────────────────────────────────────────────────────────

define( 'TZAW_VERSION', '1.2.0' );
define( 'TZAW_PLUGIN_FILE', __FILE__ );
define( 'TZAW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TZAW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TZAW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'TZAW_REST_NAMESPACE', 'techzapp-ai-writer/v1' );
define( 'TZAW_OPTION_KEY', 'tzaw_settings' );

// ─── GitHub Update Configuration ─────────────────────────────────────────────
// Set this to your GitHub repository in the format "username/repo-name".
// Example: 'techzapp/Blog-maintance'
// The updater fetches update-info.json from the default branch of this repo.
// Leave empty to disable automatic update checks.
define( 'TZAW_GITHUB_REPO', 'appcoderikbal/AI-blog-Writer' );
define( 'TZAW_UPDATE_URL', 'https://raw.githubusercontent.com/' . TZAW_GITHUB_REPO . '/master/update-info.json' );

// ─── Autoloader ───────────────────────────────────────────────────────────────

/**
 * Autoloader for plugin classes.
 *
 * @param string $class_name Fully-qualified class name.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'TechZappAIWriter\\';
		$len    = strlen( $prefix );

		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		$relative = substr( $class_name, $len );
		$file     = TZAW_PLUGIN_DIR . 'includes/class-' . strtolower( str_replace( [ '\\', '_' ], [ '/', '-' ], $relative ) ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

// ─── Bootstrap ────────────────────────────────────────────────────────────────

/**
 * Initialize the plugin after all plugins are loaded.
 */
function tzaw_init(): void {
	require_once TZAW_PLUGIN_DIR . 'includes/class-plugin.php';
	\TechZappAIWriter\Plugin::get_instance();
}
add_action( 'plugins_loaded', 'tzaw_init' );

// ─── Activation / Deactivation ────────────────────────────────────────────────

register_activation_hook(
	__FILE__,
	static function (): void {
		if ( ! get_option( TZAW_OPTION_KEY ) ) {
			update_option(
				TZAW_OPTION_KEY,
				[
					'ai_provider'        => 'openai',
					'openai_api_key'     => '',
					'groq_api_key'       => '',
					'gemini_api_key'     => '',
					'ai_model'           => 'gpt-4o-mini',
					'default_tone'       => 'professional',
					'default_word_count' => 1500,
					'default_category'   => 0,
				]
			);
		}
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);
