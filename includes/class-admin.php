<?php
/**
 * Admin class — menus and asset enqueuing.
 *
 * @package TechZappAIWriter
 */

declare( strict_types=1 );

namespace TechZappAIWriter;

/**
 * Class Admin
 */
class Admin {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Slugs that belong to this plugin.
	 *
	 * @var string[]
	 */
	private array $plugin_screens = [
		'toplevel_page_techzapp-ai-writer',
		'techzapp-ai-writer_page_tzaw-generate',
		'techzapp-ai-writer_page_tzaw-settings',
	];

	/**
	 * Admin constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register admin menu items.
	 */
	public function register_menus(): void {
		// Top-level menu.
		add_menu_page(
			__( 'TechZapp AI Writer', 'techzapp-ai-writer' ),
			__( 'TechZapp AI Writer', 'techzapp-ai-writer' ),
			'edit_posts',
			'techzapp-ai-writer',
			[ $this, 'render_app' ],
			'dashicons-edit-large',
			30
		);

		// Dashboard sub-menu (replaces duplicated top-level entry).
		add_submenu_page(
			'techzapp-ai-writer',
			__( 'Dashboard', 'techzapp-ai-writer' ),
			__( 'Dashboard', 'techzapp-ai-writer' ),
			'edit_posts',
			'techzapp-ai-writer',
			[ $this, 'render_app' ]
		);

		// Generate Blog.
		add_submenu_page(
			'techzapp-ai-writer',
			__( 'Generate Blog', 'techzapp-ai-writer' ),
			__( 'Generate Blog', 'techzapp-ai-writer' ),
			'edit_posts',
			'tzaw-generate',
			[ $this, 'render_app' ]
		);

		// Settings.
		add_submenu_page(
			'techzapp-ai-writer',
			__( 'Settings', 'techzapp-ai-writer' ),
			__( 'Settings', 'techzapp-ai-writer' ),
			'manage_options',
			'tzaw-settings',
			[ $this, 'render_app' ]
		);
	}

	/**
	 * Render the React SPA container.
	 * All three menu pages render the same shell; React handles routing internally.
	 */
	public function render_app(): void {
		$page          = sanitize_text_field( $_GET['page'] ?? 'techzapp-ai-writer' ); // phpcs:ignore WordPress.Security.NonceVerification
		$initial_route = match ( $page ) {
			'tzaw-generate' => 'generate',
			'tzaw-settings' => 'settings',
			default         => 'dashboard',
		};

		echo '<div id="tzaw-root" data-route="' . esc_attr( $initial_route ) . '"></div>';
	}

	/**
	 * Enqueue React app scripts and styles on plugin pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->plugin_screens, true ) ) {
			return;
		}

		// Stable predictable paths — Vite is configured to output main.js and main.css.
		$js_path  = TZAW_PLUGIN_DIR . 'assets/build/main.js';
		$css_path = TZAW_PLUGIN_DIR . 'assets/build/main.css';
		$js_url   = TZAW_PLUGIN_URL . 'assets/build/main.js';
		$css_url  = TZAW_PLUGIN_URL . 'assets/build/main.css';

		// Use file modification time for cache busting — avoids stale builds.
		$js_ver  = file_exists( $js_path )  ? (string) filemtime( $js_path )  : TZAW_VERSION;
		$css_ver = file_exists( $css_path ) ? (string) filemtime( $css_path ) : TZAW_VERSION;

		// Enqueue CSS.
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'tzaw-app', $css_url, [], $css_ver );
		} else {
			// CSS not built yet — inject a notice via inline style so the admin still works.
			wp_add_inline_style(
				'wp-admin',
				'#tzaw-root::before { content: "⚠ Plugin CSS not built. Run: npm install && npm run build"; display:block; padding:16px; color:#856404; background:#fff3cd; border:1px solid #ffc107; margin:16px; border-radius:6px; font-family:monospace; }'
			);
		}

		// Enqueue JS.
		if ( file_exists( $js_path ) ) {
			wp_enqueue_script( 'tzaw-app', $js_url, [], $js_ver, true );

			// Vite outputs ES modules — WordPress must serve them with type="module".
			add_filter( 'script_loader_tag', [ $this, 'add_module_type' ], 10, 2 );
		}

		// Pass data to JS.
		$categories = get_categories( [ 'hide_empty' => false ] );
		$cat_data   = array_map(
			static fn( $cat ) => [ 'id' => $cat->term_id, 'name' => $cat->name ],
			$categories
		);

		wp_localize_script(
			'tzaw-app',
			'tzawData',
			[
				'apiUrl'     => rest_url( TZAW_REST_NAMESPACE ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'siteUrl'    => get_site_url(),
				'adminUrl'   => admin_url( 'admin.php' ),
				'categories' => $cat_data,
				'settings'   => $this->settings->get_public_settings(),
			]
		);

		add_action( 'admin_head', [ $this, 'inject_admin_styles' ] );
	}

	/**
	 * Add type="module" attribute to the plugin script tag.
	 * Required for Vite ES module output to run correctly in browsers.
	 *
	 * @param string $tag    Script HTML tag.
	 * @param string $handle Script handle.
	 * @return string
	 */
	public function add_module_type( string $tag, string $handle ): string {
		if ( 'tzaw-app' === $handle ) {
			// Replace text/javascript with module (WP may have already set a type attr).
			$tag = preg_replace( '/\s+type=["\']text\/javascript["\']/i', '', $tag );
			$tag = str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}

	/**
	 * Inject admin-specific CSS overrides.
	 * Removes WP admin's default page heading and gives our root full height.
	 */
	public function inject_admin_styles(): void {
		echo '<style>
/* Give the plugin root full access to the content area */
#wpbody-content { padding-bottom: 0 !important; }
.wrap > h1:first-child { display: none !important; }
#tzaw-root {
	display: block;
	margin: -8px -20px 0 !important; /* pull out of WP wrap padding */
	padding: 0 !important;
	overflow: hidden;
}
/* Ensure Inter font loads even before React renders */
#tzaw-root * { box-sizing: border-box; }
</style>';
	}
}
