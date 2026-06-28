<?php
/**
 * REST API endpoint registration.
 *
 * @package TechZappAIWriter
 */

declare( strict_types=1 );

namespace TechZappAIWriter;

/**
 * Class Rest_Api
 */
class Rest_Api {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * AI client (multi-provider).
	 *
	 * @var AI_Client
	 */
	private AI_Client $ai_client;

	/**
	 * Trending aggregator.
	 *
	 * @var Trending
	 */
	private Trending $trending;

	/**
	 * Draft saver.
	 *
	 * @var Draft_Saver
	 */
	private Draft_Saver $draft_saver;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings    = $settings;
		$this->ai_client   = new AI_Client( $settings );
		$this->trending    = new Trending();
		$this->draft_saver = new Draft_Saver();
	}

	/**
	 * Register all REST routes.
	 */
	public function register_routes(): void {
		$ns = TZAW_REST_NAMESPACE;

		// Trending topics.
		register_rest_route(
			$ns,
			'/trending',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_trending' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'refresh' => [
						'type'    => 'boolean',
						'default' => false,
					],
				],
			]
		);

		// Generate article.
		register_rest_route(
			$ns,
			'/generate',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'generate_article' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => $this->get_generate_args(),
			]
		);

		// Improve/Expand/Shorten article.
		register_rest_route(
			$ns,
			'/improve',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'improve_article' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => [
					'content'     => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'wp_kses_post',
					],
					'instruction' => [
						'required' => true,
						'type'     => 'string',
						'enum'     => [ 'improve', 'expand', 'shorten' ],
					],
				],
			]
		);

		// Save draft.
		register_rest_route(
			$ns,
			'/save-draft',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_draft' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
				'args'                => $this->get_save_draft_args(),
			]
		);

		// Dashboard stats.
		register_rest_route(
			$ns,
			'/stats',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_stats' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
			]
		);

		// Get/Update settings.
		register_rest_route(
			$ns,
			'/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);

		// Test API connection.
		register_rest_route(
			$ns,
			'/test-connection',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'test_connection' ],
				'permission_callback' => [ $this, 'check_manage_permission' ],
			]
		);

		// WordPress categories.
		register_rest_route(
			$ns,
			'/categories',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_categories' ],
				'permission_callback' => [ $this, 'check_edit_permission' ],
			]
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Callbacks
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * GET /trending
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_trending( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$refresh = (bool) $request->get_param( 'refresh' );
		$topics  = $this->trending->get_topics( $refresh );

		return rest_ensure_response(
			[
				'success' => true,
				'data'    => $topics,
			]
		);
	}

	/**
	 * POST /generate
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_article( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$params = [
			'title'              => $request->get_param( 'title' ),
			'keyword'            => $request->get_param( 'keyword' ),
			'secondary_keywords' => $request->get_param( 'secondary_keywords' ),
			'audience'           => $request->get_param( 'audience' ),
			'tone'               => $request->get_param( 'tone' ),
			'word_count'         => $request->get_param( 'word_count' ),
		];

		$result = $this->ai_client->generate_article( $params );

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array_merge( [ 'status' => 422 ], (array) $result->get_error_data() )
			);
		}

		return rest_ensure_response(
			[
				'success' => true,
				'data'    => $result,
			]
		);
	}

	/**
	 * POST /improve
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function improve_article( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$content     = (string) $request->get_param( 'content' );
		$instruction = (string) $request->get_param( 'instruction' );

		$result = $this->ai_client->improve_article( $content, $instruction );

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => 422 ]
			);
		}

		return rest_ensure_response(
			[
				'success' => true,
				'data'    => $result,
			]
		);
	}

	/**
	 * POST /save-draft
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_draft( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$data = [
			'title'           => $request->get_param( 'title' ),
			'content'         => $request->get_param( 'content' ),
			'meta_description' => $request->get_param( 'meta_description' ),
			'focus_keyword'   => $request->get_param( 'focus_keyword' ),
			'categories'      => $request->get_param( 'categories' ),
			'tags'            => $request->get_param( 'tags' ),
		];

		$post_id = $this->draft_saver->save( $data );

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error(
				$post_id->get_error_code(),
				$post_id->get_error_message(),
				[ 'status' => 422 ]
			);
		}

		return rest_ensure_response(
			[
				'success'  => true,
				'post_id'  => $post_id,
				'edit_url' => get_edit_post_link( $post_id, 'raw' ),
				'message'  => __( 'Draft saved successfully!', 'techzapp-ai-writer' ),
			]
		);
	}

	/**
	 * GET /stats
	 *
	 * @return \WP_REST_Response
	 */
	public function get_stats(): \WP_REST_Response {
		return rest_ensure_response(
			[
				'success' => true,
				'data'    => [
					'total_generated' => $this->draft_saver->get_total_count(),
					'recent_drafts'   => $this->draft_saver->get_recent_drafts( 5 ),
				],
			]
		);
	}

	/**
	 * GET /settings
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings(): \WP_REST_Response {
		$all = $this->settings->get_all();

		// Mask all provider API keys before sending to frontend.
		foreach ( [ 'openai_api_key', 'groq_api_key', 'gemini_api_key' ] as $key_field ) {
			if ( ! empty( $all[ $key_field ] ) ) {
				$all[ $key_field ] = str_repeat( '*', 20 ) . substr( (string) $all[ $key_field ], -4 );
			}
		}

		$all['provider_models'] = Settings::$provider_models;

		return rest_ensure_response(
			[
				'success' => true,
				'data'    => $all,
			]
		);
	}

	/**
	 * POST /settings
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		$this->settings->update( $params );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Settings saved successfully.', 'techzapp-ai-writer' ),
			]
		);
	}

	/**
	 * POST /test-connection
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test_connection(): \WP_REST_Response|\WP_Error {
		$result = $this->ai_client->test_connection();

		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => 422 ]
			);
		}

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'OpenAI connection successful!', 'techzapp-ai-writer' ),
			]
		);
	}

	/**
	 * GET /categories
	 *
	 * @return \WP_REST_Response
	 */
	public function get_categories(): \WP_REST_Response {
		$categories = get_categories( [ 'hide_empty' => false ] );
		$data       = array_map(
			static fn( $cat ) => [ 'id' => $cat->term_id, 'name' => $cat->name ],
			$categories
		);

		return rest_ensure_response(
			[
				'success' => true,
				'data'    => $data,
			]
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Permission Callbacks
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Check if the current user can edit posts.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_edit_permission(): bool|\WP_Error {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}
		return new \WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to perform this action.', 'techzapp-ai-writer' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Check if the current user can manage options.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_manage_permission(): bool|\WP_Error {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new \WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to manage settings.', 'techzapp-ai-writer' ),
			[ 'status' => 403 ]
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Argument Schemas
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Argument schema for /generate endpoint.
	 *
	 * @return array<string, mixed>
	 */
	private function get_generate_args(): array {
		return [
			'title'              => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'minLength'         => 5,
			],
			'keyword'            => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
			'secondary_keywords' => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
			'audience'           => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'general tech enthusiasts',
			],
			'tone'               => [
				'required' => false,
				'type'     => 'string',
				'enum'     => [ 'professional', 'friendly', 'conversational', 'beginner-friendly', 'technical' ],
				'default'  => 'professional',
			],
			'word_count'         => [
				'required' => false,
				'type'     => 'integer',
				'enum'     => [ 1000, 1500, 2000, 3000 ],
				'default'  => 1500,
			],
		];
	}

	/**
	 * Argument schema for /save-draft endpoint.
	 *
	 * @return array<string, mixed>
	 */
	private function get_save_draft_args(): array {
		return [
			'title'            => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'content'          => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			],
			'meta_description' => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
			'focus_keyword'    => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
			'categories'       => [
				'required' => false,
				'type'     => 'array',
				'items'    => [ 'type' => 'integer' ],
				'default'  => [],
			],
			'tags'             => [
				'required' => false,
				'type'     => 'array',
				'items'    => [ 'type' => 'string' ],
				'default'  => [],
			],
		];
	}
}
