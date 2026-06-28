<?php
/**
 * Settings class — WordPress Options API wrapper.
 *
 * @package TechZappAIWriter
 */

declare( strict_types=1 );

namespace TechZappAIWriter;

/**
 * Class Settings
 */
class Settings {

	/**
	 * Default values for all settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $defaults = [
		'ai_provider'        => 'openai',
		'openai_api_key'     => '',
		'groq_api_key'       => '',
		'gemini_api_key'     => '',
		'ai_model'           => 'gpt-4o-mini',
		'default_tone'       => 'professional',
		'default_word_count' => 1500,
		'default_category'   => 0,
	];

	/**
	 * Cached settings array.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Available models per provider.
	 *
	 * @var array<string, array<int, array<string, string>>>
	 */
	public static array $provider_models = [
		'openai' => [
			[ 'value' => 'gpt-4o-mini',  'label' => 'GPT-4o Mini (Faster, Cheaper)' ],
			[ 'value' => 'gpt-4o',       'label' => 'GPT-4o (Best Quality)' ],
			[ 'value' => 'gpt-4-turbo',  'label' => 'GPT-4 Turbo' ],
			[ 'value' => 'gpt-3.5-turbo','label' => 'GPT-3.5 Turbo (Fastest)' ],
		],
		'groq'   => [
			[ 'value' => 'llama-3.3-70b-versatile',  'label' => 'Llama 3.3 70B Versatile (Recommended)' ],
			[ 'value' => 'llama-3.1-8b-instant',     'label' => 'Llama 3.1 8B Instant (Fastest)' ],
			[ 'value' => 'mixtral-8x7b-32768',       'label' => 'Mixtral 8x7B' ],
			[ 'value' => 'gemma2-9b-it',             'label' => 'Gemma 2 9B' ],
		],
		'gemini' => [
			[ 'value' => 'gemini-2.0-flash',         'label' => 'Gemini 2.0 Flash (Recommended)' ],
			[ 'value' => 'gemini-2.0-flash-lite',    'label' => 'Gemini 2.0 Flash Lite (Fastest)' ],
			[ 'value' => 'gemini-1.5-pro',           'label' => 'Gemini 1.5 Pro (Best Quality)' ],
			[ 'value' => 'gemini-1.5-flash',         'label' => 'Gemini 1.5 Flash' ],
		],
	];

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get_all(): array {
		if ( null === $this->cache ) {
			$saved       = get_option( TZAW_OPTION_KEY, [] );
			$this->cache = wp_parse_args( is_array( $saved ) ? $saved : [], $this->defaults );
		}
		return $this->cache;
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$settings = $this->get_all();
		return $settings[ $key ] ?? ( $default ?? ( $this->defaults[ $key ] ?? null ) );
	}

	/**
	 * Get the API key for the currently active provider.
	 *
	 * @return string
	 */
	public function get_active_api_key(): string {
		$provider = (string) $this->get( 'ai_provider', 'openai' );
		return (string) $this->get( $provider . '_api_key', '' );
	}

	/**
	 * Update settings. Sanitizes all values before saving.
	 *
	 * @param array<string, mixed> $new_settings Incoming settings data.
	 * @return bool
	 */
	public function update( array $new_settings ): bool {
		$allowed_providers = [ 'openai', 'groq', 'gemini' ];
		$provider          = in_array( $new_settings['ai_provider'] ?? '', $allowed_providers, true )
			? sanitize_text_field( $new_settings['ai_provider'] )
			: 'openai';

		// Validate model against the provider's model list.
		$allowed_models = array_column( self::$provider_models[ $provider ] ?? [], 'value' );
		$model          = in_array( $new_settings['ai_model'] ?? '', $allowed_models, true )
			? sanitize_text_field( $new_settings['ai_model'] )
			: ( $allowed_models[0] ?? 'gpt-4o-mini' );

		$allowed_tones  = [ 'professional', 'friendly', 'conversational', 'beginner-friendly', 'technical' ];
		$allowed_counts = [ 1000, 1500, 2000, 3000 ];

		$existing = $this->get_all();

		// Preserve existing key if the incoming value is masked or empty.
		$openai_key = sanitize_text_field( $new_settings['openai_api_key'] ?? '' );
		if ( empty( $openai_key ) || str_contains( $openai_key, '****' ) ) {
			$openai_key = (string) ( $existing['openai_api_key'] ?? '' );
		}

		$groq_key = sanitize_text_field( $new_settings['groq_api_key'] ?? '' );
		if ( empty( $groq_key ) || str_contains( $groq_key, '****' ) ) {
			$groq_key = (string) ( $existing['groq_api_key'] ?? '' );
		}

		$gemini_key = sanitize_text_field( $new_settings['gemini_api_key'] ?? '' );
		if ( empty( $gemini_key ) || str_contains( $gemini_key, '****' ) ) {
			$gemini_key = (string) ( $existing['gemini_api_key'] ?? '' );
		}

		$tone       = in_array( $new_settings['default_tone'] ?? '', $allowed_tones, true )
			? sanitize_text_field( $new_settings['default_tone'] )
			: 'professional';
		$word_count = in_array( (int) ( $new_settings['default_word_count'] ?? 0 ), $allowed_counts, true )
			? (int) $new_settings['default_word_count']
			: 1500;

		$sanitized = [
			'ai_provider'        => $provider,
			'openai_api_key'     => $openai_key,
			'groq_api_key'       => $groq_key,
			'gemini_api_key'     => $gemini_key,
			'ai_model'           => $model,
			'default_tone'       => $tone,
			'default_word_count' => $word_count,
			'default_category'   => (int) ( $new_settings['default_category'] ?? 0 ),
		];

		$result      = update_option( TZAW_OPTION_KEY, $sanitized );
		$this->cache = null;
		return $result;
	}

	/**
	 * Get settings safe to expose to the frontend (masks API keys, excludes raw keys).
	 *
	 * @return array<string, mixed>
	 */
	public function get_public_settings(): array {
		$all = $this->get_all();

		// Replace raw keys with presence flags.
		unset( $all['openai_api_key'], $all['groq_api_key'], $all['gemini_api_key'] );

		$all['has_openai_key'] = ! empty( $this->get( 'openai_api_key' ) );
		$all['has_groq_key']   = ! empty( $this->get( 'groq_api_key' ) );
		$all['has_gemini_key'] = ! empty( $this->get( 'gemini_api_key' ) );
		$all['provider_models'] = self::$provider_models;

		return $all;
	}
}
